<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotaFiscalListagemService
{
    public function pagina(int $propertyId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($propertyId, $filtros);

        return [
            'activeModule' => 'fiscal',
            'title' => 'Notas Fiscais',
            'subtitle' => 'Consulta das notas fiscais importadas por XML e seus vinculos fiscais.',
            'filtros' => $filtros,
            'rows' => $rows,
            'cards' => [
                ['label' => 'Notas fiscais', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Aguardando aprovacao', 'value' => (string) $rows->where('status_key', 'aguardando_aprovacao')->count(), 'tone' => 'warning'],
                ['label' => 'Aprovadas', 'value' => (string) $rows->whereIn('status_key', ['aprovada', 'aprovado'])->count(), 'tone' => 'success'],
                ['label' => 'Valor total', 'value' => FarmFormat::money((float) $rows->sum('total_raw')), 'tone' => 'warning'],
            ],
            'statusOptions' => [
                'aguardando_aprovacao' => 'Aguardando aprovacao',
                'aprovada' => 'Aprovada',
                'rejeitada' => 'Rejeitada',
                'cancelada' => 'Cancelada',
            ],
        ];
    }

    public function aprovar(int $propertyId, int $invoiceId, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $invoiceId, $userId): void {
            $invoice = DB::table('fiscal_invoices')
                ->where('id', $invoiceId)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw new RuntimeException('Nota fiscal nao encontrada.');
            }

            if ((string) $invoice->status === 'aprovada') {
                throw new RuntimeException('Esta nota fiscal ja foi aprovada.');
            }

            if ((string) $invoice->status !== 'aguardando_aprovacao') {
                throw new RuntimeException('Esta nota fiscal nao esta aguardando aprovacao.');
            }

            DB::table('fiscal_invoices')
                ->where('id', $invoiceId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'previous_status' => $invoice->status,
                    'status' => 'aprovada',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'approval_metadata' => json_encode([
                        'approved_by' => $userId,
                        'approved_at' => now()->toIso8601String(),
                        'previous_status' => $invoice->status,
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $this->auditar($userId, 'aprovar_nota_fiscal', 'fiscal_invoices', $invoiceId, $propertyId, 'Nota fiscal aprovada no modulo fiscal');
        });
    }

    public function rejeitar(int $propertyId, int $invoiceId, ?int $userId, ?string $reason = null): void
    {
        DB::transaction(function () use ($propertyId, $invoiceId, $userId, $reason): void {
            $invoice = DB::table('fiscal_invoices')
                ->where('id', $invoiceId)
                ->where('propriedade_id', $propertyId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw new RuntimeException('Nota fiscal não encontrada.');
            }

            if ((string) $invoice->status !== 'aguardando_aprovacao') {
                throw new RuntimeException('Esta nota fiscal não está aguardando aprovação.');
            }

            $cleanReason = trim((string) $reason);

            DB::table('fiscal_invoices')
                ->where('id', $invoiceId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'previous_status' => $invoice->status,
                    'status' => 'rejeitada',
                    'approval_metadata' => json_encode([
                        'rejected_by' => $userId,
                        'rejected_at' => now()->toIso8601String(),
                        'previous_status' => $invoice->status,
                        'reason' => $cleanReason,
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $this->auditar(
                $userId,
                'rejeitar_nota_fiscal',
                'fiscal_invoices',
                $invoiceId,
                $propertyId,
                'Nota fiscal rejeitada no módulo fiscal'.($cleanReason !== '' ? ': '.$cleanReason : '')
            );
        });
    }

    public function detalhe(int $propertyId, int $invoiceId): array
    {
        $nota = DB::table('fiscal_invoices')
            ->where('id', $invoiceId)
            ->where('propriedade_id', $propertyId)
            ->first();

        abort_if(! $nota, 404);

        $itens = DB::table('fiscal_invoice_items')
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => (object) [
                'product_code' => FarmFormat::value($item->product_code),
                'description' => FarmFormat::value($item->description),
                'unit' => FarmFormat::value($item->unit),
                'quantity' => FarmFormat::decimal($item->quantity),
                'unit_value' => FarmFormat::money($item->unit_value),
                'total_value' => FarmFormat::money($item->total_value),
            ]);

        return [
            'activeModule' => 'fiscal',
            'title' => 'Nota Fiscal '.$this->documentNumber($nota->invoice_number, $nota->series),
            'subtitle' => 'Detalhe da nota fiscal importada no sistema.',
            'nota' => (object) [
                'id' => (int) $nota->id,
                'access_key' => FarmFormat::value($nota->access_key),
                'number' => $this->documentNumber($nota->invoice_number, $nota->series),
                'invoice_number' => FarmFormat::value($nota->invoice_number),
                'series' => FarmFormat::value($nota->series),
                'issue_date' => FarmFormat::date($nota->issue_date),
                'issuer_name' => FarmFormat::value($nota->issuer_name),
                'issuer_cnpj' => FarmFormat::value($nota->issuer_cnpj),
                'recipient_name' => FarmFormat::value($nota->recipient_name),
                'recipient_cnpj' => FarmFormat::value($nota->recipient_cnpj),
                'total' => FarmFormat::money($nota->total_value),
                'status_key' => (string) $nota->status,
                'status' => $this->statusLabel((string) $nota->status),
                'status_tone' => $this->statusTone((string) $nota->status),
                'can_approve' => (string) $nota->status === 'aguardando_aprovacao',
                'can_reject' => (string) $nota->status === 'aguardando_aprovacao',
                'item_count' => $itens->count(),
                'tem_xml' => ! empty($nota->xml_file_path),
            ],
            'itens' => $itens,
            'cards' => [
                ['label' => 'Status', 'value' => $this->statusLabel((string) $nota->status), 'tone' => 'success'],
                ['label' => 'Total', 'value' => FarmFormat::money($nota->total_value), 'tone' => 'warning'],
                ['label' => 'Fornecedor', 'value' => FarmFormat::value($nota->issuer_name), 'tone' => 'success'],
                ['label' => 'Itens', 'value' => (string) $itens->count(), 'tone' => 'warning'],
            ],
        ];
    }

    public function baixarXml(int $propertyId, int $invoiceId): BinaryFileResponse
    {
        $nota = DB::table('fiscal_invoices')
            ->where('id', $invoiceId)
            ->where('propriedade_id', $propertyId)
            ->first(['xml_file_path', 'access_key', 'invoice_number']);

        abort_unless($nota && $nota->xml_file_path, 404);

        $relative = preg_replace('#^storage/app/private/#', '', (string) $nota->xml_file_path);
        $base = realpath(storage_path('app/private'));
        $path = realpath(storage_path('app/private/'.$relative));
        abort_unless($base && $path && str_starts_with($path, $base) && is_file($path), 404);

        $nome = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string) ($nota->invoice_number ?: $nota->access_key ?: 'nota'));

        return response()->download($path, $nome.'.xml', [
            'Content-Type' => 'application/xml',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filtros(Request $request): array
    {
        return [
            'status' => trim((string) $request->query('status', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'supplier' => trim((string) $request->query('supplier', '')),
        ];
    }

    private function rows(int $propertyId, array $filtros): Collection
    {
        $query = DB::table('fiscal_invoices as fi')
            ->where('fi.propriedade_id', $propertyId);

        if ($filtros['status'] !== '') {
            $query->where('fi.status', $filtros['status']);
        }

        if ($filtros['date_from'] !== '') {
            $query->whereDate('fi.issue_date', '>=', $filtros['date_from']);
        }

        if ($filtros['date_to'] !== '') {
            $query->whereDate('fi.issue_date', '<=', $filtros['date_to']);
        }

        if ($filtros['supplier'] !== '') {
            $term = '%'.$filtros['supplier'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('fi.issuer_name', 'like', $term)
                    ->orWhere('fi.issuer_cnpj', 'like', $term);
            });
        }

        return $query
            ->select([
                'fi.id',
                'fi.invoice_number',
                'fi.series',
                'fi.issue_date',
                'fi.issuer_name',
                'fi.issuer_cnpj',
                'fi.total_value',
                'fi.status',
                'fi.xml_file_path',
                DB::raw('(SELECT COUNT(*) FROM fiscal_invoice_items fii WHERE fii.invoice_id = fi.id) as item_count'),
                DB::raw('(SELECT COUNT(*) FROM fiscal_order_invoices foi WHERE foi.invoice_id = fi.id) as linked_orders'),
            ])
            ->orderByDesc('fi.issue_date')
            ->orderByDesc('fi.id')
            ->limit(180)
            ->get()
            ->map(fn ($nota) => (object) [
                'id' => (int) $nota->id,
                'number' => $this->documentNumber($nota->invoice_number, $nota->series),
                'issuer_name' => FarmFormat::value($nota->issuer_name),
                'issuer_cnpj' => FarmFormat::value($nota->issuer_cnpj),
                'issue_date' => FarmFormat::date($nota->issue_date),
                'total_raw' => (float) $nota->total_value,
                'total' => FarmFormat::money($nota->total_value),
                'status_key' => (string) $nota->status,
                'status' => $this->statusLabel((string) $nota->status),
                'status_tone' => $this->statusTone((string) $nota->status),
                'can_approve' => (string) $nota->status === 'aguardando_aprovacao',
                'can_reject' => (string) $nota->status === 'aguardando_aprovacao',
                'item_count' => (int) $nota->item_count,
                'linked_orders' => (int) $nota->linked_orders,
                'tem_xml' => ! empty($nota->xml_file_path),
                'consolidated_url' => route('fiscal.consolidado.index', ['supplier' => $nota->issuer_cnpj ?: $nota->issuer_name]),
            ]);
    }

    private function documentNumber(?string $number, ?string $series): string
    {
        $number = FarmFormat::value($number);
        if (! $series) {
            return $number;
        }

        return $number.' / Serie '.$series;
    }

    private function statusLabel(string $status): string
    {
        return FarmFormat::statusLabel($status);
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'aprovada', 'aprovado' => 'success',
            'rejeitada', 'cancelada' => 'danger',
            default => 'warning',
        };
    }

    private function auditar(?int $usuarioId, string $acao, string $tabela, int $registroId, int $propriedadeId, string $detalhes): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => $detalhes,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
