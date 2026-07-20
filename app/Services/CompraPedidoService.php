<?php

namespace App\Services;

use App\Domain\Inventory\ProductIdentity;
use App\Domain\Purchasing\PurchaseOrderCapabilities;
use App\Support\FarmContext;
use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CompraPedidoService
{
    private const APPROVER_PROFILES = [
        'administrador',
        'administrador_sistema',
        'financeiro',
        'gerencia_sistema',
        'gestao',
        'gestor_financeiro',
        'gestor_propriedade',
    ];

    private const GLOBAL_APPROVER_PROFILES = [
        'administrador',
        'administrador_sistema',
        'gerencia_sistema',
    ];

    public array $units = ['Quilograma', 'Grama', 'Tonelada', 'Litro', 'Mililitro', 'Metros', 'Unidade'];

    public function __construct(
        private readonly PurchaseOrderCapabilities $capabilities,
        private readonly ProductIdentity $productIdentity,
    ) {
    }

    public function propertyId(): int
    {
        return app(FarmContext::class)->propertyId();
    }

    public function statusOptions(): array
    {
        return [
            'todos' => 'Todos',
            'em_aberto' => 'Em aberto',
            'aguardando_aprovacao' => 'Aguardando aprovação',
            'aprovado_baixado' => 'Aprovado/Baixado',
            'rejeitado' => 'Rejeitado',
            'cancelado' => 'Cancelado',
        ];
    }

    public function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'todos');

        return [
            'status' => array_key_exists($status, $this->statusOptions()) ? $status : 'todos',
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'supplier' => trim((string) $request->query('supplier', '')),
        ];
    }

    public function listOrders(int $propertyId, array $filters = [])
    {
        return $this->applyListFilters($this->baseOrderQuery($propertyId), $filters)
            ->orderByDesc('pedidos.issue_date')
            ->orderByDesc('pedidos.id')
            ->limit(500)
            ->get()
            ->map(fn (object $order): object => $this->prepareOrder($order));
    }

    public function totals($pedidos): array
    {
        return [
            'pedidos' => $pedidos->count(),
            'valor' => $pedidos->sum('total_value'),
            'pendentes' => $pedidos->whereIn('status', ['em_aberto', 'aguardando_aprovacao'])->count(),
            'aguardando_aprovacao' => $pedidos->where('status', 'aguardando_aprovacao')->count(),
            'aprovados' => $pedidos->where('status', 'aprovado_baixado')->count(),
        ];
    }

    public function formData(int $propertyId): array
    {
        return [
            'categorias' => $this->orderCategories(),
            'fornecedores' => $this->activeSuppliers($propertyId),
            'patrimonios' => $this->propertyAssets($propertyId),
            'units' => $this->units,
        ];
    }

    public function createOrder(Request $request, int $propertyId, bool $requiresApproval = false): int
    {
        $items = $this->normalizeItems($request, $propertyId);
        abort_if(! $items, 422, 'Informe pelo menos um item válido no pedido.');

        return DB::transaction(function () use ($request, $propertyId, $items, $requiresApproval): int {
            $orderNumber = trim((string) $request->input('order_number'));
            if ($orderNumber === '') {
                $orderNumber = 'PED-'.date('YmdHis');
            }

            $totalValue = array_sum(array_column($items, 'total_value'));
            $supplier = $this->supplierPayload($request, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => $orderNumber,
                'supplier_name' => $supplier['name'],
                'supplier_cnpj' => $supplier['document'],
                'order_type' => 'entrada',
                'issue_date' => $request->input('issue_date'),
                'total_value' => $totalValue,
                'status' => $requiresApproval ? 'aguardando_aprovacao' : 'em_aberto',
                'notes' => trim((string) $request->input('notes')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $orderId = (int) DB::getPdo()->lastInsertId();
            foreach ($items as $item) {
                DB::table('fiscal_order_items')->insert($item + [
                    'order_id' => $orderId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            AuditService::log(
                action: 'criar_pedido_fiscal',
                table: 'fiscal_orders',
                recordId: $orderId,
                propertyId: $propertyId,
                details: [
                    'numero' => $orderNumber,
                    'fornecedor' => $supplier['name'],
                    'status' => $requiresApproval ? 'aguardando_aprovacao' : 'em_aberto',
                    'total' => $totalValue,
                ],
            );

            return $orderId;
        });
    }

    public function canApproveOrders(int $propertyId, ?int $userId): bool
    {
        if ((int) $userId <= 0) {
            return false;
        }

        $usuario = DB::table('usuarios')
            ->where('id', (int) $userId)
            ->first(['id', 'perfil']);

        if (! $usuario) {
            return false;
        }

        $profile = (string) $usuario->perfil;
        if (! in_array($profile, self::APPROVER_PROFILES, true)) {
            return false;
        }

        if (in_array($profile, self::GLOBAL_APPROVER_PROFILES, true)) {
            return true;
        }

        return Schema::hasTable('usuario_propriedades')
            && DB::table('usuario_propriedades')
                ->where('usuario_id', (int) $userId)
                ->where('propriedade_id', $propertyId)
                ->exists();
    }

    public function updateOrder(Request $request, int $propertyId, int $pedido): void
    {
        $items = $this->normalizeItems($request, $propertyId);
        abort_if(! $items, 422, 'Informe pelo menos um item válido no pedido.');

        DB::transaction(function () use ($request, $propertyId, $pedido, $items): void {
            $order = DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('Pedido fiscal não encontrado.');
            }

            if (! $this->capabilities->for($order->status)['can_edit']) {
                throw new RuntimeException('Este pedido não pode ser alterado no status atual.');
            }

            $orderNumber = trim((string) $request->input('order_number'));
            if ($orderNumber === '') {
                $orderNumber = (string) $order->order_number;
            }

            $supplier = $this->supplierPayload($request, $propertyId);

            DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'order_number' => $orderNumber,
                    'supplier_name' => $supplier['name'],
                    'supplier_cnpj' => $supplier['document'],
                    'issue_date' => $request->input('issue_date'),
                    'total_value' => array_sum(array_column($items, 'total_value')),
                    'notes' => trim((string) $request->input('notes')),
                    'updated_at' => now(),
                ]);

            DB::table('fiscal_order_items')->where('order_id', $pedido)->delete();
            foreach ($items as $item) {
                DB::table('fiscal_order_items')->insert($item + [
                    'order_id' => $pedido,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function findOrder(int $propertyId, int $pedido)
    {
        return $this->prepareOrder(
            $this->baseOrderQuery($propertyId)->where('pedidos.id', $pedido)->firstOrFail(),
        );
    }

    public function showData(int $propertyId, int $pedido, mixed $invoiceLinkPreview): array
    {
        $order = $this->findOrder($propertyId, $pedido);
        $preview = $this->prepareInvoicePreview($pedido, $invoiceLinkPreview);
        $previewMatchCount = (int) ($preview['comparison']['match_count'] ?? 0);
        $order = $this->prepareOrder($order, $preview !== null, $previewMatchCount);
        $linkedInvoices = $this->linkedInvoices($pedido)->map(function (object $invoice) use ($order): object {
            $invoice->can_unlink_invoice = $order->can_unlink_invoice;

            return $invoice;
        });
        $availableInvoices = $this->availableInvoices($propertyId, $pedido);

        return [
            'activeModule' => 'compras',
            'order' => $order,
            'items' => $this->orderItems($pedido),
            'linkedInvoices' => $linkedInvoices,
            'linkedInvoiceCount' => $linkedInvoices->count(),
            'availableInvoices' => $availableInvoices,
            'hasAvailableInvoices' => $availableInvoices->isNotEmpty(),
            'invoiceComparison' => $this->invoiceComparison($pedido),
            'invoiceLinkPreview' => $preview,
            'previewInvoice' => $preview['invoice'] ?? [],
            'previewComparison' => $preview['comparison'] ?? [],
        ];
    }

    public function orderItems(int $pedido)
    {
        return DB::table('fiscal_order_items as itens')
            ->leftJoin('categorias', 'categorias.id', '=', 'itens.categoria_id')
            ->leftJoin('maquinas', 'maquinas.id', '=', 'itens.patrimonio_id')
            ->where('itens.order_id', $pedido)
            ->select([
                'itens.*',
                'categorias.nome as categoria_nome',
                'maquinas.nome as patrimonio_nome',
            ])
            ->orderBy('itens.id')
            ->get();
    }

    public function linkedInvoices(int $pedido)
    {
        return DB::table('fiscal_order_invoices as vinculos')
            ->join('fiscal_invoices as notas', 'notas.id', '=', 'vinculos.invoice_id')
            ->where('vinculos.order_id', $pedido)
            ->orderByDesc('notas.issue_date')
            ->orderByDesc('notas.id')
            ->get([
                'notas.id',
                'notas.invoice_number',
                'notas.series',
                'notas.issue_date',
                'notas.issuer_name',
                'notas.issuer_cnpj',
                'notas.total_value',
                'notas.status',
                'vinculos.match_status',
                'vinculos.match_summary',
                'vinculos.linked_at',
            ])
            ->map(function (object $invoice): object {
                $invoice->status_label = FarmFormat::statusLabel((string) $invoice->status);
                $invoice->status_tone = $invoice->status === 'aprovada' ? 'success' : 'warning';

                return $invoice;
            });
    }

    public function invoiceComparison(int $pedido): ?array
    {
        $linked = DB::table('fiscal_order_invoices')->where('order_id', $pedido)->pluck('invoice_id')->all();
        if (! $linked) {
            return null;
        }

        return $this->prepareComparison($this->compareOrderInvoiceItems(
            $this->orderItems($pedido)->all(),
            $this->invoiceItems($linked)->all()
        ));
    }

    public function availableInvoices(int $propertyId, int $pedido)
    {
        return DB::table('fiscal_invoices as notas')
            ->where('notas.propriedade_id', $propertyId)
            ->whereNotExists(function ($query) use ($pedido) {
                $query->selectRaw('1')
                    ->from('fiscal_order_invoices as vinculos')
                    ->whereColumn('vinculos.invoice_id', 'notas.id')
                    ->where('vinculos.order_id', '<>', $pedido);
            })
            ->whereNotExists(function ($query) use ($pedido) {
                $query->selectRaw('1')
                    ->from('fiscal_order_invoices as vinculos')
                    ->whereColumn('vinculos.invoice_id', 'notas.id')
                    ->where('vinculos.order_id', '=', $pedido);
            })
            ->orderByDesc('notas.issue_date')
            ->orderByDesc('notas.id')
            ->limit(80)
            ->get([
                'notas.id',
                'notas.invoice_number',
                'notas.series',
                'notas.issue_date',
                'notas.issuer_name',
                'notas.issuer_cnpj',
                'notas.total_value',
                'notas.status',
            ]);
    }

    public function linkInvoice(int $propertyId, int $pedido, int $invoiceId, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $pedido, $invoiceId, $userId): void {
            $order = DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('Pedido fiscal não encontrado.');
            }

            if (! $this->capabilities->for($order->status)['can_link_invoice']) {
                throw new RuntimeException('Este pedido não pode receber nova nota fiscal no status atual.');
            }

            $invoice = DB::table('fiscal_invoices')
                ->where('id', $invoiceId)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw new RuntimeException('Nota fiscal não encontrada para esta propriedade.');
            }

            $linkedOrder = DB::table('fiscal_order_invoices')
                ->where('invoice_id', $invoiceId)
                ->value('order_id');

            if ($linkedOrder && (int) $linkedOrder !== $pedido) {
                throw new RuntimeException('Esta nota fiscal já está vinculada a outro pedido.');
            }

            $comparison = $this->compareOrderInvoiceItems(
                $this->orderItems($pedido)->all(),
                $this->invoiceItems([$invoiceId])->all()
            );

            DB::table('fiscal_order_invoices')->updateOrInsert(
                ['order_id' => $pedido, 'invoice_id' => $invoiceId],
                [
                    'match_status' => $comparison['has_divergences'] ? 'divergente' : 'conferido',
                    'match_summary' => $this->comparisonSummary($comparison),
                    'linked_by' => $userId,
                    'linked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }

    public function invoiceLinkPreview(int $propertyId, int $pedido, int $invoiceId): array
    {
        $order = DB::table('fiscal_orders')
            ->where('id', $pedido)
            ->where('propriedade_id', $propertyId)
            ->first();

        if (! $order) {
            throw new RuntimeException('Pedido fiscal não encontrado.');
        }

        if (! $this->capabilities->for($order->status)['can_link_invoice']) {
            throw new RuntimeException('Este pedido não pode receber nova nota fiscal no status atual.');
        }

        $invoice = DB::table('fiscal_invoices')
            ->where('id', $invoiceId)
            ->where('propriedade_id', $propertyId)
            ->first();

        if (! $invoice) {
            throw new RuntimeException('Nota fiscal não encontrada para esta propriedade.');
        }

        $linkedOrder = DB::table('fiscal_order_invoices')
            ->where('invoice_id', $invoiceId)
            ->value('order_id');

        if ($linkedOrder && (int) $linkedOrder !== $pedido) {
            throw new RuntimeException('Esta nota fiscal já está vinculada a outro pedido.');
        }

        return [
            'order_id' => $pedido,
            'invoice_id' => $invoiceId,
            'invoice' => [
                'invoice_number' => $invoice->invoice_number,
                'series' => $invoice->series,
                'issuer_name' => $invoice->issuer_name,
                'issuer_cnpj' => $invoice->issuer_cnpj,
                'total_value' => (float) $invoice->total_value,
                'issue_date' => $invoice->issue_date,
            ],
            'comparison' => $this->compareOrderInvoiceItems(
                $this->orderItems($pedido)->all(),
                $this->invoiceItems([$invoiceId])->all()
            ),
            'created_at' => time(),
        ];
    }

    public function confirmInvoicePreview(int $propertyId, int $pedido, array $preview, ?int $userId): void
    {
        if ((int) ($preview['order_id'] ?? 0) !== $pedido || empty($preview['invoice_id'])) {
            throw new RuntimeException('Não há comparação de nota fiscal para confirmar.');
        }

        $order = DB::table('fiscal_orders')
            ->where('id', $pedido)
            ->where('propriedade_id', $propertyId)
            ->first(['id', 'status']);

        if (! $order) {
            throw new RuntimeException('Pedido fiscal não encontrado.');
        }

        $invoiceId = (int) $preview['invoice_id'];
        $comparison = $this->compareOrderInvoiceItems(
            $this->orderItems($pedido)->all(),
            $this->invoiceItems([$invoiceId])->all()
        );

        $canConfirm = $this->capabilities->for(
            (string) $order->status,
            true,
            (int) ($comparison['match_count'] ?? 0),
        )['can_confirm_invoice_link'];

        if (! $canConfirm) {
            if (! $this->capabilities->for($order->status)['can_link_invoice']) {
                throw new RuntimeException('Este pedido não pode confirmar vínculo de nota fiscal no status atual.');
            }

            throw new RuntimeException('Não foi encontrado nenhum item compatível entre o pedido e a nota fiscal. Edite os itens do pedido ou remova esta nota antes de continuar.');
        }

        $this->linkInvoice($propertyId, $pedido, $invoiceId, $userId);
    }

    public function unlinkInvoice(int $propertyId, int $pedido, int $invoiceId): void
    {
        DB::transaction(function () use ($propertyId, $pedido, $invoiceId): void {
            $order = DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('Pedido fiscal não encontrado.');
            }

            if (! $this->capabilities->for($order->status)['can_unlink_invoice']) {
                throw new RuntimeException('Este pedido não pode remover nota fiscal no status atual.');
            }

            DB::table('fiscal_order_invoices')
                ->where('order_id', $pedido)
                ->where('invoice_id', $invoiceId)
                ->delete();
        });
    }

    private function invoiceItems(array $invoiceIds)
    {
        return DB::table('fiscal_invoice_items')
            ->whereIn('invoice_id', $invoiceIds)
            ->orderBy('invoice_id')
            ->orderBy('id')
            ->get();
    }

    private function compareOrderInvoiceItems(array $orderItems, array $invoiceItems): array
    {
        $matches = [];
        $divergences = [];
        $missingInInvoice = [];
        $usedInvoices = [];

        foreach ($orderItems as $order) {
            $bestIndex = null;
            $bestReason = null;

            foreach ($invoiceItems as $index => $invoice) {
                if (isset($usedInvoices[$index])) {
                    continue;
                }

                $reason = $this->itemMatchReason($order, $invoice);
                if ($reason !== null) {
                    $bestIndex = $index;
                    $bestReason = $reason;
                    break;
                }
            }

            if ($bestIndex === null) {
                $missingInInvoice[] = $this->itemLabel($order);

                continue;
            }

            $usedInvoices[$bestIndex] = true;
            $invoice = $invoiceItems[$bestIndex];
            $issues = $this->itemMatchIssues($order, $invoice);
            $row = [
                'order' => $this->itemLabel($order),
                'invoice' => $this->itemLabel($invoice),
                'issues' => $issues,
                'match_reason' => $bestReason,
            ];

            if ($issues) {
                $divergences[] = $row;
            } else {
                $matches[] = $row;
            }
        }

        $missingInOrder = [];
        foreach ($invoiceItems as $index => $invoice) {
            if (! isset($usedInvoices[$index])) {
                $missingInOrder[] = $this->itemLabel($invoice);
            }
        }

        return [
            'matches' => $matches,
            'divergences' => $divergences,
            'missing_in_invoice' => $missingInInvoice,
            'missing_in_order' => $missingInOrder,
            'match_count' => count($matches) + count($divergences),
            'has_divergences' => (bool) ($divergences || $missingInInvoice || $missingInOrder),
        ];
    }

    private function itemMatchReason(object $order, object $invoice): ?string
    {
        $orderCode = strtolower(trim((string) ($order->product_code ?? '')));
        $invoiceCode = strtolower(trim((string) ($invoice->product_code ?? '')));
        if ($orderCode !== '' && $invoiceCode !== '' && $orderCode === $invoiceCode) {
            return 'Código do produto igual';
        }

        $orderDesc = $this->itemText((string) ($order->description ?? ''));
        $invoiceDesc = $this->itemText((string) ($invoice->description ?? ''));
        if ($orderDesc !== '' && $invoiceDesc !== '') {
            if ($orderDesc === $invoiceDesc) {
                return 'Descrição igual';
            }

            similar_text($orderDesc, $invoiceDesc, $percent);
            if ($percent >= 82) {
                return 'Descrição semelhante';
            }

            $orderUnit = strtolower(trim((string) ($order->unit ?? '')));
            $invoiceUnit = strtolower(trim((string) ($invoice->unit ?? '')));
            if ($orderUnit !== '' && $invoiceUnit !== '' && $orderUnit === $invoiceUnit && (str_contains($orderDesc, $invoiceDesc) || str_contains($invoiceDesc, $orderDesc))) {
                return 'Descrição e unidade compatíveis';
            }
        }

        return null;
    }

    private function itemMatchIssues(object $order, object $invoice): array
    {
        $issues = [];
        if ($this->differentText($order->product_code ?? '', $invoice->product_code ?? '')) {
            $issues[] = 'Código do produto divergente';
        }
        if (strcasecmp(trim((string) ($order->unit ?? '')), trim((string) ($invoice->unit ?? ''))) !== 0) {
            $issues[] = 'Unidade de medida divergente';
        }
        if ($this->valuesDiffer((float) ($order->quantity ?? 0), (float) ($invoice->quantity ?? 0), 0.0001)) {
            $issues[] = 'Quantidade divergente';
        }
        if ($this->valuesDiffer((float) ($order->unit_value ?? 0), (float) ($invoice->unit_value ?? 0), 0.01)) {
            $issues[] = 'Valor unitario divergente';
        }
        if ($this->valuesDiffer((float) ($order->total_value ?? 0), (float) ($invoice->total_value ?? 0), 0.01)) {
            $issues[] = 'Valor total divergente';
        }
        if (strcasecmp(trim((string) ($order->description ?? '')), trim((string) ($invoice->description ?? ''))) !== 0) {
            $issues[] = 'Descrição divergente';
        }

        return $issues;
    }

    private function comparisonSummary(array $comparison): string
    {
        return sprintf(
            'Itens conferidos: %d. Divergências: %d. Não encontrados na nota: %d. Não encontrados no pedido: %d.',
            $comparison['match_count'],
            count($comparison['divergences']),
            count($comparison['missing_in_invoice']),
            count($comparison['missing_in_order'])
        );
    }

    private function itemLabel(object $item): array
    {
        return [
            'code' => (string) ($item->product_code ?? ''),
            'description' => (string) ($item->description ?? ''),
            'unit' => (string) ($item->unit ?? ''),
            'quantity' => (float) ($item->quantity ?? 0),
            'unit_value' => (float) ($item->unit_value ?? 0),
            'total_value' => (float) ($item->total_value ?? 0),
        ];
    }

    private function itemText(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim((string) $value);
    }

    private function differentText($a, $b): bool
    {
        $a = trim((string) $a);
        $b = trim((string) $b);

        return $a !== '' && $b !== '' && strcasecmp($a, $b) !== 0;
    }

    private function valuesDiffer(float $a, float $b, float $tolerance): bool
    {
        return abs($a - $b) > $tolerance;
    }

    private function prepareOrder(
        object $order,
        bool $previewBelongsToOrder = false,
        int $previewMatchCount = 0,
    ): object {
        foreach ($this->capabilities->for(
            (string) $order->status,
            $previewBelongsToOrder,
            $previewMatchCount,
        ) as $capability => $allowed) {
            $order->{$capability} = $allowed;
        }

        $order->status_label = FarmFormat::statusLabel((string) $order->status);
        $order->status_tone = match ((string) $order->status) {
            'aprovado', 'aprovado_baixado', 'baixado' => 'success',
            'rejeitado', 'cancelado' => 'danger',
            default => 'warning',
        };
        $order->linked_invoice_count = (int) ($order->linked_invoice_count ?? 0);
        $order->divergent_invoice_count = (int) ($order->divergent_invoice_count ?? 0);
        $order->has_linked_invoices = $order->linked_invoice_count > 0;
        $order->has_invoice_divergences = $order->divergent_invoice_count > 0;

        return $order;
    }

    private function prepareInvoicePreview(int $pedido, mixed $preview): ?array
    {
        if (! is_array($preview) || (int) ($preview['order_id'] ?? 0) !== $pedido) {
            return null;
        }

        $preview['invoice'] = is_array($preview['invoice'] ?? null) ? $preview['invoice'] : [];
        $preview['comparison'] = $this->prepareComparison(
            is_array($preview['comparison'] ?? null) ? $preview['comparison'] : [],
        );

        return $preview;
    }

    private function prepareComparison(array $comparison): array
    {
        $comparison['divergences'] = $comparison['divergences'] ?? [];
        $comparison['missing_in_invoice'] = $comparison['missing_in_invoice'] ?? [];
        $comparison['missing_in_order'] = $comparison['missing_in_order'] ?? [];
        $comparison['match_count'] = (int) ($comparison['match_count'] ?? 0);
        $comparison['divergence_count'] = count($comparison['divergences']);
        $comparison['missing_in_invoice_count'] = count($comparison['missing_in_invoice']);
        $comparison['missing_in_order_count'] = count($comparison['missing_in_order']);
        $comparison['has_divergences'] = (bool) ($comparison['has_divergences'] ?? false);

        return $comparison;
    }

    public function approveOrder(
        int $propertyId,
        int $pedido,
        ?int $userId,
        bool $confirmed,
        bool $confirmWithoutInvoice = false,
        bool $confirmDivergences = false,
    ): int {
        if (! $confirmed) {
            throw new RuntimeException('Confirme explicitamente a aprovação do pedido.');
        }

        if (! $this->canApproveOrders($propertyId, $userId)) {
            throw new RuntimeException('Seu usuário não tem permissão para aprovar pedidos fiscais desta propriedade.');
        }

        return DB::transaction(function () use (
            $propertyId,
            $pedido,
            $userId,
            $confirmWithoutInvoice,
            $confirmDivergences,
        ): int {
            $order = DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('Pedido fiscal não encontrado.');
            }

            if (! $this->capabilities->for($order->status)['can_approve']) {
                throw new RuntimeException('Este pedido não está em status pendente para aprovação.');
            }

            $items = $this->orderItems($pedido);
            if ($items->isEmpty()) {
                throw new RuntimeException('Não é possível aprovar um pedido sem itens.');
            }

            $hasLinkedInvoices = DB::table('fiscal_order_invoices')
                ->where('order_id', $pedido)
                ->exists();
            $hasInvoiceDivergences = $this->hasInvoiceDivergences($pedido);

            if (! $hasLinkedInvoices && ! $confirmWithoutInvoice) {
                throw new RuntimeException('Confirme que deseja aprovar este pedido sem nota fiscal vinculada.');
            }

            if ($hasLinkedInvoices && $hasInvoiceDivergences && ! $confirmDivergences) {
                throw new RuntimeException('Existem divergências entre o pedido e a nota fiscal vinculada. Confira as divergências e confirme antes de aprovar.');
            }

            $metadata = [
                'approved_by' => $userId,
                'approved_at' => now()->toIso8601String(),
                'had_linked_invoices' => $hasLinkedInvoices,
                'had_invoice_divergences' => $hasInvoiceDivergences,
                'confirmed_without_invoice' => ! $hasLinkedInvoices && $confirmWithoutInvoice,
                'confirmed_with_divergences' => $hasLinkedInvoices && $hasInvoiceDivergences && $confirmDivergences,
            ];

            DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'previous_status' => $order->status,
                    'status' => 'aprovado_baixado',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'approval_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $approvedOrder = DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->first();

            $expenseId = $this->createFinancialExpense($approvedOrder, $userId);
            $stockItems = $this->syncStock($approvedOrder, $items, $userId);
            $patrimonioItems = $this->syncPatrimonio($approvedOrder, $items, $userId);

            $metadata['financial_expense_id'] = $expenseId;
            $metadata['stock_items'] = $stockItems;
            $metadata['patrimonio_items'] = $patrimonioItems;

            DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'financial_expense_id' => $expenseId,
                    'approval_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                ]);

            AuditService::log(
                action: 'aprovar_pedido_fiscal',
                table: 'fiscal_orders',
                recordId: $pedido,
                propertyId: $propertyId,
                details: [
                    'numero' => (string) $approvedOrder->order_number,
                    'despesa_financeira_id' => $expenseId,
                    'nota_fiscal_vinculada' => (bool) $metadata['had_linked_invoices'],
                    'aprovado_sem_nota_confirmado' => (bool) $metadata['confirmed_without_invoice'],
                    'aprovado_com_divergencias_confirmado' => (bool) $metadata['confirmed_with_divergences'],
                    'itens_estoque' => $stockItems,
                    'itens_patrimonio' => $patrimonioItems,
                ],
            );

            return $expenseId;
        });
    }

    public function rejectOrder(int $propertyId, int $pedido, ?int $userId, ?string $reason = null): void
    {
        if (! $this->canApproveOrders($propertyId, $userId)) {
            throw new RuntimeException('Seu usuário não tem permissão para rejeitar pedidos fiscais desta propriedade.');
        }

        DB::transaction(function () use ($propertyId, $pedido, $userId, $reason): void {
            $order = DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('Pedido fiscal não encontrado.');
            }

            if (! $this->capabilities->for($order->status)['can_reject']) {
                throw new RuntimeException('Este pedido não pode ser rejeitado no status atual.');
            }

            $cleanReason = trim((string) $reason);
            $metadata = [
                'rejected_by' => $userId,
                'rejected_at' => now()->toIso8601String(),
                'previous_status' => $order->status,
                'reason' => $cleanReason,
                'linked_invoice_count' => DB::table('fiscal_order_invoices')->where('order_id', $pedido)->count(),
            ];

            DB::table('fiscal_orders')
                ->where('id', $pedido)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'previous_status' => $order->status,
                    'status' => 'rejeitado',
                    'approval_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            AuditService::log(
                action: 'rejeitar_pedido_fiscal',
                table: 'fiscal_orders',
                recordId: $pedido,
                propertyId: $propertyId,
                details: [
                    'numero' => (string) $order->order_number,
                    'status_anterior' => (string) $order->status,
                    'motivo' => $cleanReason,
                ],
            );
        });
    }

    private function hasInvoiceDivergences(int $pedido): bool
    {
        if (DB::table('fiscal_order_invoices')
            ->where('order_id', $pedido)
            ->where('match_status', 'divergente')
            ->exists()) {
            return true;
        }

        return (bool) ($this->invoiceComparison($pedido)['has_divergences'] ?? false);
    }

    private function baseOrderQuery(int $propertyId)
    {
        return DB::table('fiscal_orders as pedidos')
            ->leftJoin('propriedades', 'propriedades.id', '=', 'pedidos.propriedade_id')
            ->where('pedidos.propriedade_id', $propertyId)
            ->select([
                'pedidos.id',
                'pedidos.order_number',
                'pedidos.financial_expense_id',
                'pedidos.supplier_name',
                'pedidos.supplier_cnpj',
                'pedidos.issue_date',
                'pedidos.total_value',
                'pedidos.status',
                'pedidos.notes',
                'propriedades.nome as propriedade_nome',
                DB::raw('(SELECT COUNT(*) FROM fiscal_order_invoices foi WHERE foi.order_id = pedidos.id) as linked_invoice_count'),
                DB::raw("(SELECT COUNT(*) FROM fiscal_order_invoices foi WHERE foi.order_id = pedidos.id AND foi.match_status = 'divergente') as divergent_invoice_count"),
            ]);
    }

    private function applyListFilters($query, array $filters)
    {
        $status = (string) ($filters['status'] ?? 'todos');
        if ($status !== 'todos' && array_key_exists($status, $this->statusOptions())) {
            $query->where('pedidos.status', $status);
        }

        $dtInicio = (string) ($filters['date_from'] ?? '');
        if ($dtInicio !== '') {
            $query->whereDate('pedidos.issue_date', '>=', $dtInicio);
        }

        $dtFim = (string) ($filters['date_to'] ?? '');
        if ($dtFim !== '') {
            $query->whereDate('pedidos.issue_date', '<=', $dtFim);
        }

        $supplier = trim((string) ($filters['supplier'] ?? ''));
        if ($supplier !== '') {
            $supplierDigits = preg_replace('/\D+/', '', $supplier);
            $query->where(function ($subQuery) use ($supplier, $supplierDigits): void {
                $subQuery->where('pedidos.supplier_name', 'like', '%'.$supplier.'%');

                if ($supplierDigits !== '') {
                    $subQuery->orWhere('pedidos.supplier_cnpj', 'like', '%'.$supplierDigits.'%');
                }
            });
        }

        return $query;
    }

    private function orderCategories()
    {
        return DB::table('categorias')
            ->where('ativo', 1)
            ->whereNotIn('tipo', ['bancario'])
            ->whereNotIn('nome', ['Financeiro', 'Financiamento', 'Taxas e juros', 'Pedidos Fiscais'])
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo']);
    }

    private function activeSuppliers(int $propertyId)
    {
        if (! Schema::hasTable('fornecedores')) {
            return collect();
        }

        return DB::table('fornecedores')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(300)
            ->get(['id', 'nome', 'documento'])
            ->map(function (object $supplier): object {
                $supplier->documento_formatado = $this->formatSupplierDocument($supplier->documento ?? null);

                return $supplier;
            });
    }

    private function supplierPayload(Request $request, int $propertyId): array
    {
        $supplierId = (int) $request->input('supplier_id', 0);
        $manualName = trim((string) $request->input('supplier_name'));
        $manualDocument = preg_replace('/\D+/', '', (string) $request->input('supplier_cnpj'));

        if ($supplierId <= 0) {
            return [
                'name' => $manualName,
                'document' => $manualDocument,
            ];
        }

        if (! Schema::hasTable('fornecedores')) {
            throw new RuntimeException('Cadastro de fornecedores não está disponível. Execute as migrations.');
        }

        $supplier = DB::table('fornecedores')
            ->where('id', $supplierId)
            ->where('propriedade_id', $propertyId)
            ->where('ativo', true)
            ->first(['nome', 'documento']);

        if (! $supplier) {
            throw new RuntimeException('Fornecedor selecionado não foi encontrado nesta propriedade.');
        }

        $document = preg_replace('/\D+/', '', (string) ($supplier->documento ?? '')) ?: $manualDocument;
        if ($document === '') {
            throw new RuntimeException(
                'Fornecedor selecionado não possui CNPJ/CPF. Cadastre o documento do fornecedor antes de criar o pedido.'
            );
        }

        return [
            'name' => trim((string) $supplier->nome),
            'document' => $document,
        ];
    }

    private function formatSupplierDocument(?string $document): string
    {
        $digits = preg_replace('/\D+/', '', (string) $document) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
        }

        return $digits;
    }

    private function createFinancialExpense(object $order, ?int $userId): int
    {
        $existingId = (int) ($order->financial_expense_id ?? 0);
        if ($existingId > 0 && DB::table('despesas')
            ->where('id', $existingId)
            ->where('propriedade_id', $order->propriedade_id)
            ->where('status_pagamento', '!=', 'cancelado')
            ->exists()) {
            return $existingId;
        }

        $marker = '[FISCAL_ORDER_ID:'.(int) $order->id.']';
        $duplicateId = (int) DB::table('despesas')
            ->where('propriedade_id', $order->propriedade_id)
            ->where('observacoes', 'like', '%'.$marker.'%')
            ->where('status_pagamento', '!=', 'cancelado')
            ->value('id');

        if ($duplicateId > 0) {
            return $duplicateId;
        }

        DB::table('despesas')->insert([
            'propriedade_id' => (int) $order->propriedade_id,
            'categoria_id' => $this->financialCategoryId(),
            'descricao' => substr('Pedido fiscal '.(string) $order->order_number, 0, 255),
            'fornecedor' => substr((string) ($order->supplier_name ?? ''), 0, 150),
            'quantidade' => null,
            'unidade' => '',
            'valor_unitario' => null,
            'valor_total' => (float) $order->total_value,
            'data_lancamento' => $order->issue_date ?: now()->toDateString(),
            'data_vencimento' => $order->issue_date ?: now()->toDateString(),
            'status_pagamento' => 'pendente',
            'status_aprovacao' => 'aprovada',
            'aprovado_por' => $userId,
            'aprovado_em' => now(),
            'forma_pagamento' => 'pix',
            'numero_parcelas' => 1,
            'parcela_atual' => 1,
            'nota_fiscal' => $this->linkedInvoiceNumbers((int) $order->id),
            'observacoes' => trim($marker."\nLançamento financeiro gerado automaticamente pela aprovação do pedido fiscal.".($order->notes ? "\n".$order->notes : '')),
            'usuario_id' => $userId,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function syncStock(object $order, $items, ?int $userId): int
    {
        $created = 0;
        foreach ($items as $item) {
            $quantity = max(0.0, (float) $item->quantity - $this->patrimonioUsedQuantity($item));
            if ((int) $item->id <= 0 || $quantity <= 0) {
                continue;
            }

            $productId = $this->findOrCreateStockProduct($order, $item);
            $existingId = DB::table('produto_estoque_movimentos')
                ->where('fiscal_order_item_id', $item->id)
                ->value('id');

            $payload = [
                'propriedade_id' => (int) $order->propriedade_id,
                'produto_id' => $productId,
                'origem_tipo' => 'pedido_fiscal',
                'origem_id' => (int) $order->id,
                'fiscal_order_id' => (int) $order->id,
                'fiscal_order_item_id' => (int) $item->id,
                'tipo' => 'entrada',
                'quantidade' => $quantity,
                'unidade' => trim((string) ($item->unit ?? 'un')) ?: 'un',
                'valor_unitario' => (float) $item->unit_value,
                'valor_total' => round($quantity * (float) $item->unit_value, 2),
                'data_movimento' => $order->approved_at ? date('Y-m-d', strtotime((string) $order->approved_at)) : ($order->issue_date ?: now()->toDateString()),
                'observacoes' => 'Entrada automatica pelo pedido fiscal '.(string) $order->order_number,
                'usuario_id' => $userId,
            ];

            if ($existingId) {
                DB::table('produto_estoque_movimentos')->where('id', $existingId)->update($payload);

                continue;
            }

            DB::table('produto_estoque_movimentos')->insert($payload);
            $created++;
        }

        return $created;
    }

    private function syncPatrimonio(object $order, $items, ?int $userId): int
    {
        $created = 0;
        $date = $order->approved_at ? date('Y-m-d', strtotime((string) $order->approved_at)) : ($order->issue_date ?: now()->toDateString());
        $markerBase = '[FISCAL_ORDER_ID:'.(int) $order->id.']';

        foreach ($items as $item) {
            $patrimonioId = (int) ($item->patrimonio_id ?? 0);
            $usedQuantity = $this->patrimonioUsedQuantity($item);
            if ($patrimonioId <= 0 || $usedQuantity <= 0 || (float) $item->unit_value < 0) {
                continue;
            }

            $marker = $markerBase.'[FISCAL_ORDER_ITEM_ID:'.(int) $item->id.']';
            $existingId = DB::table('maquina_lancamentos')
                ->where('propriedade_id', $order->propriedade_id)
                ->where('maquina_id', $patrimonioId)
                ->where('observacoes', 'like', '%'.$marker.'%')
                ->value('id');

            $payload = [
                'propriedade_id' => (int) $order->propriedade_id,
                'maquina_id' => $patrimonioId,
                'tipo' => $this->patrimonioLaunchType($item),
                'data_lancamento' => $date,
                'descricao' => substr('Pedido fiscal '.(string) $order->order_number.' - '.(string) $item->description, 0, 180),
                'fornecedor' => substr((string) ($order->supplier_name ?? ''), 0, 150),
                'quantidade' => $usedQuantity,
                'unidade' => trim((string) ($item->unit ?? 'un')) ?: 'un',
                'valor_unitario' => (float) $item->unit_value,
                'valor_total' => round($usedQuantity * (float) $item->unit_value, 2),
                'observacoes' => trim($marker."\nLançamento gerado automaticamente pela aprovação do pedido fiscal."),
                'usuario_id' => $userId,
            ];

            if ($existingId) {
                DB::table('maquina_lancamentos')->where('id', $existingId)->update($payload);

                continue;
            }

            DB::table('maquina_lancamentos')->insert($payload);
            $created++;
        }

        return $created;
    }

    private function propertyAssets(int $propertyId)
    {
        return DB::table('maquinas')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('ativo')
            ->orderBy('nome')
            ->get(['id', 'nome', 'marca_modelo', 'ativo']);
    }

    private function financialCategoryId(): ?int
    {
        $id = DB::table('categorias')->where('nome', 'Pedidos Fiscais')->value('id');
        if ($id) {
            return (int) $id;
        }

        DB::table('categorias')->insert([
            'nome' => 'Pedidos Fiscais',
            'tipo' => 'administrativo',
            'cor' => '#0f8d4d',
            'ativo' => 1,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function linkedInvoiceNumbers(int $orderId): ?string
    {
        $numbers = DB::table('fiscal_order_invoices as foi')
            ->join('fiscal_invoices as fi', 'fi.id', '=', 'foi.invoice_id')
            ->where('foi.order_id', $orderId)
            ->limit(5)
            ->pluck('fi.invoice_number')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $numbers ? substr(implode(', ', $numbers), 0, 50) : null;
    }

    private function findOrCreateStockProduct(object $order, object $item): int
    {
        $propertyId = (int) $order->propriedade_id;
        $code = trim((string) ($item->product_code ?? ''));
        $description = trim((string) ($item->description ?? ''));
        $unit = trim((string) ($item->unit ?? 'un')) ?: 'un';
        $categoryId = ! empty($item->categoria_id) ? (int) $item->categoria_id : null;

        $found = $this->findMatchingStockProduct($propertyId, $code, $description, $unit);

        if ($found) {
            $updates = [];
            if (empty($found->codigo_interno)) {
                $updates['codigo_interno'] = $this->productIdentity->internalCodeForId((int) $found->id);
            }
            if ($code !== '' && empty($found->codigo_fornecedor)) {
                $updates['codigo_fornecedor'] = $code;
            }
            if ($categoryId && empty($found->categoria_id)) {
                $updates['categoria_id'] = $categoryId;
            }
            if (strcasecmp(trim((string) $found->unidade_medida), $unit) !== 0) {
                $updates['unidade_medida'] = $unit;
            }
            if ($updates) {
                DB::table('produtos')->where('id', $found->id)->update($updates);
            }

            return (int) $found->id;
        }

        DB::table('produtos')->insert([
            'propriedade_id' => $propertyId,
            'codigo_interno' => null,
            'codigo_fornecedor' => $code ?: null,
            'descricao_original_nf' => $description,
            'descricao_generica' => $description,
            'unidade_medida' => $unit,
            'categoria_id' => $categoryId,
            'ativo' => 1,
            'informacoes_fiscais' => 'Criado automaticamente pela aprovação do pedido fiscal '.(string) ($order->order_number ?? ''),
        ]);

        $productId = (int) DB::getPdo()->lastInsertId();
        DB::table('produtos')
            ->where('id', $productId)
            ->update(['codigo_interno' => $this->productIdentity->internalCodeForId($productId)]);

        return $productId;
    }

    private function findMatchingStockProduct(
        int $propertyId,
        string $code,
        string $description,
        string $unit
    ): ?object {
        $products = DB::table('produtos')
            ->where('propriedade_id', $propertyId)
            ->when($code !== '', fn ($query) => $query->where(function ($innerQuery) use ($code) {
                $innerQuery->where('codigo_interno', $code)
                    ->orWhere('codigo_fornecedor', $code);
            }), fn ($query) => $query->where(function ($innerQuery) use ($description) {
                $innerQuery->where('descricao_generica', $description)
                    ->orWhere('descricao_original_nf', $description);
            }))
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->get([
                'id',
                'codigo_interno',
                'codigo_fornecedor',
                'descricao_generica',
                'descricao_original_nf',
                'categoria_id',
                'unidade_medida',
            ]);

        $found = $this->selectProductByUnit($products, $unit);
        if ($found) {
            return $found;
        }

        $normalizedProducts = DB::table('produtos')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->get([
                'id',
                'codigo_interno',
                'codigo_fornecedor',
                'descricao_generica',
                'descricao_original_nf',
                'categoria_id',
                'unidade_medida',
            ])
            ->filter(fn (object $product): bool => $this->productIdentity->descriptionsMatch(
                $description,
                (string) ($product->descricao_generica ?: $product->descricao_original_nf)
            ));

        return $this->selectProductByUnit($normalizedProducts, $unit);
    }

    private function selectProductByUnit(Collection $products, string $unit): ?object
    {
        return $products->first(fn (object $product): bool => strcasecmp(
            trim((string) $product->unidade_medida),
            $unit
        ) === 0) ?: $products->first();
    }

    private function patrimonioUsedQuantity(object $item): float
    {
        if ((int) ($item->patrimonio_id ?? 0) <= 0) {
            return 0.0;
        }

        $quantity = max(0.0, (float) ($item->quantity ?? 0));
        $usage = (string) ($item->patrimonio_uso ?? 'estoque');
        if ($usage === 'total') {
            return $quantity;
        }
        if ($usage === 'parcial') {
            return min($quantity, max(0.0, (float) ($item->patrimonio_quantidade ?? 0)));
        }

        return 0.0;
    }

    private function patrimonioLaunchType(object $item): string
    {
        $text = strtolower(trim((string) ($item->categoria_nome ?? '').' '.(string) ($item->description ?? '')));

        if (str_contains($text, 'combust') || str_contains($text, 'diesel') || str_contains($text, 'gasolina')) {
            return 'abastecimento';
        }
        if (str_contains($text, 'lubr') || str_contains($text, 'oleo')) {
            return 'troca_oleo';
        }
        if (str_contains($text, 'peca')) {
            return 'pecas';
        }
        if (str_contains($text, 'seguro')) {
            return 'seguro';
        }
        if (str_contains($text, 'manuten') || str_contains($text, 'servi') || str_contains($text, 'oficina')) {
            return 'manutencao_corretiva';
        }

        return 'outro';
    }

    private function normalizeItems(Request $request, int $propertyId): array
    {
        $codes = $request->input('item_product_code', []);
        $descriptions = $request->input('item_description', []);
        $categories = $request->input('item_categoria_id', []);
        $assets = $request->input('item_patrimonio_id', []);
        $assetUses = $request->input('item_patrimonio_uso', []);
        $assetQuantities = $request->input('item_patrimonio_quantidade', []);
        $units = $request->input('item_unit', []);
        $quantities = $request->input('item_quantity', []);
        $unitValues = $request->input('item_unit_value', []);
        $count = max(count($descriptions), count($codes), count($quantities), count($unitValues));
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $description = trim((string) ($descriptions[$i] ?? ''));
            $code = trim((string) ($codes[$i] ?? ''));
            $unit = trim((string) ($units[$i] ?? ''));
            $quantity = $this->decimal($quantities[$i] ?? '0');
            $unitValue = $this->money($unitValues[$i] ?? '0');

            if ($description === '' && $code === '' && $unit === '' && $quantity <= 0 && $unitValue <= 0) {
                continue;
            }

            abort_if($description === '' || $unit === '' || $quantity <= 0 || $unitValue < 0, 422, 'Confira descricao, unidade, quantidade e valor dos itens.');

            $assetId = $this->validAssetId($propertyId, $assets[$i] ?? null);
            [$assetUse, $assetQuantity] = $this->assetUsage(
                (string) ($assetUses[$i] ?? 'estoque'),
                $assetId,
                $quantity,
                $this->decimal($assetQuantities[$i] ?? '0')
            );

            $items[] = [
                'product_code' => $code ?: null,
                'description' => $description,
                'unit' => $unit,
                'categoria_id' => $this->validCategoryId($categories[$i] ?? null),
                'patrimonio_id' => $assetId,
                'patrimonio_uso' => $assetUse,
                'patrimonio_quantidade' => $assetQuantity,
                'quantity' => $quantity,
                'unit_value' => $unitValue,
                'total_value' => round($quantity * $unitValue, 2),
            ];
        }

        return $items;
    }

    private function validCategoryId($categoryId): ?int
    {
        $categoryId = (int) $categoryId;
        if ($categoryId <= 0) {
            return null;
        }

        return DB::table('categorias')
            ->where('id', $categoryId)
            ->where('ativo', 1)
            ->exists() ? $categoryId : null;
    }

    private function validAssetId(int $propertyId, $assetId): ?int
    {
        $assetId = (int) $assetId;
        if ($assetId <= 0) {
            return null;
        }

        return DB::table('maquinas')
            ->where('id', $assetId)
            ->where('propriedade_id', $propertyId)
            ->exists() ? $assetId : null;
    }

    private function assetUsage(string $usage, ?int $assetId, float $quantity, float $usedQuantity): array
    {
        if (! $assetId) {
            return ['estoque', 0.0];
        }

        if ($usage === 'total') {
            return ['total', $quantity];
        }

        if ($usage === 'parcial') {
            $usedQuantity = max(0.0, min($quantity, $usedQuantity));
            if ($usedQuantity > 0) {
                return ['parcial', $usedQuantity];
            }
        }

        return ['estoque', 0.0];
    }

    private function decimal($value): float
    {
        return max(0.0, (float) str_replace(',', '.', trim((string) $value)));
    }

    private function money($value): float
    {
        $value = trim((string) $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float) $value);
    }
}
