<?php

namespace App\Http\Controllers;

use App\Services\CompraPedidoService;
use App\Services\NotaFiscalXmlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CompraPedidoController extends Controller
{
    public function __construct(private CompraPedidoService $pedidos) {}

    public function index(Request $request): View
    {
        $propertyId = $this->pedidos->propertyId();
        $filters = $this->pedidos->filters($request);
        $pedidos = $this->pedidos->listOrders($propertyId, $filters);

        return view('compras.pedidos.index', [
            'activeModule' => 'compras',
            'filters' => $filters,
            'pedidos' => $pedidos,
            'statusOptions' => $this->pedidos->statusOptions(),
            'totais' => $this->pedidos->totals($pedidos),
            'canApproveOrders' => $this->pedidos->canApproveOrders($propertyId, session('usuario_id')),
            ...$this->pedidos->formData($propertyId),
        ]);
    }

    public function create(): View
    {
        return view('compras.pedidos.create', [
            'activeModule' => 'compras',
            ...$this->pedidos->formData($this->pedidos->propertyId()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'issue_date' => ['required', 'date'],
            'supplier_name' => ['required', 'string', 'max:160'],
            'supplier_cnpj' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'item_description' => ['required', 'array'],
            'item_description.*' => ['nullable', 'string', 'max:255'],
            'vincular_nota_antes_aprovar' => ['nullable', 'boolean'],
        ]);

        try {
            $propertyId = $this->pedidos->propertyId();
            $userId = session('usuario_id');
            $canApproveOrders = $this->pedidos->canApproveOrders($propertyId, $userId);
            $linkInvoiceBeforeApproval = $request->boolean('vincular_nota_antes_aprovar');
            $orderId = $this->pedidos->createOrder($request, $propertyId, ! $canApproveOrders || $linkInvoiceBeforeApproval);

            if ($canApproveOrders && ! $linkInvoiceBeforeApproval) {
                $expenseId = $this->pedidos->approveOrder($propertyId, $orderId, $userId, true, true);

                return $this->redirectToFinancialPanel($expenseId)
                    ->with('success', 'Pedido fiscal criado, aprovado e lançado no financeiro. Confira o lançamento no painel e informe a conta real na baixa do pagamento.');
            }

            if ($linkInvoiceBeforeApproval) {
                return redirect()
                    ->route('compras.pedidos.show', $orderId)
                    ->with('success', 'Pedido fiscal criado. Vincule ou importe a nota fiscal antes de aprovar e lançar no financeiro.');
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.index', ['status' => 'aguardando_aprovacao'])
            ->with('success', 'Pedido fiscal criado e enviado para aprovação do gestor.');
    }

    public function show(int $pedido): View
    {
        $propertyId = $this->pedidos->propertyId();

        return view('compras.pedidos.show', [
            ...$this->pedidos->showData(
                $propertyId,
                $pedido,
                session('fiscal_order_invoice_preview'),
            ),
            'canApproveOrders' => $this->pedidos->canApproveOrders($propertyId, session('usuario_id')),
        ]);
    }

    public function edit(int $pedido): View
    {
        $propertyId = $this->pedidos->propertyId();

        return view('compras.pedidos.edit', [
            'activeModule' => 'compras',
            'order' => $this->pedidos->findOrder($propertyId, $pedido),
            'items' => $this->pedidos->orderItems($pedido),
            ...$this->pedidos->formData($propertyId),
        ]);
    }

    public function update(Request $request, int $pedido): RedirectResponse
    {
        $request->validate([
            'issue_date' => ['required', 'date'],
            'supplier_name' => ['required', 'string', 'max:160'],
            'supplier_cnpj' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'item_description' => ['required', 'array'],
            'item_description.*' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->pedidos->updateOrder($request, $this->pedidos->propertyId(), $pedido);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'Pedido fiscal atualizado com sucesso.');
    }

    public function approve(Request $request, int $pedido): RedirectResponse
    {
        try {
            $expenseId = $this->pedidos->approveOrder(
                $this->pedidos->propertyId(),
                $pedido,
                session('usuario_id'),
                $request->boolean('confirmar_aprovacao'),
                $request->boolean('confirmar_sem_nota'),
                $request->boolean('confirmar_divergencias')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return $this->redirectToFinancialPanel($expenseId)
            ->with('success', 'Pedido aprovado, lançado no financeiro e incorporado ao estoque. Confira o lançamento no painel e informe a conta real na baixa do pagamento.');
    }

    public function reject(Request $request, int $pedido): RedirectResponse
    {
        $dados = $request->validate([
            'motivo_rejeicao' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->pedidos->rejectOrder(
                $this->pedidos->propertyId(),
                $pedido,
                session('usuario_id'),
                $dados['motivo_rejeicao'] ?? null,
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.index', ['status' => 'rejeitado'])
            ->with('success', 'Pedido fiscal rejeitado com sucesso.');
    }

    public function linkInvoice(Request $request, int $pedido): RedirectResponse
    {
        $request->validate([
            'invoice_id' => ['required', 'integer'],
        ]);

        try {
            $preview = $this->pedidos->invoiceLinkPreview(
                $this->pedidos->propertyId(),
                $pedido,
                $request->integer('invoice_id')
            );
            session(['fiscal_order_invoice_preview' => $preview]);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'Comparação gerada. Confira antes de confirmar o vínculo.');
    }

    public function importInvoice(Request $request, int $pedido, NotaFiscalXmlService $xmlService): RedirectResponse
    {
        $dados = $request->validate([
            'xml' => ['required', 'file', 'mimes:xml'],
        ]);

        try {
            $propertyId = $this->pedidos->propertyId();
            $previewXml = $xmlService->preview($dados['xml'], true);
            $invoiceId = (int) ($previewXml['existing_invoice_id'] ?? 0);

            if ($invoiceId <= 0) {
                $invoiceId = $xmlService->confirmarPreview($previewXml, $propertyId, session('usuario_id'));
            }

            $preview = $this->pedidos->invoiceLinkPreview($propertyId, $pedido, $invoiceId);
            session(['fiscal_order_invoice_preview' => $preview]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'XML importado e comparação gerada. Confira antes de confirmar o vínculo.');
    }

    public function confirmInvoiceLink(int $pedido): RedirectResponse
    {
        $preview = session('fiscal_order_invoice_preview');

        try {
            $this->pedidos->confirmInvoicePreview(
                $this->pedidos->propertyId(),
                $pedido,
                is_array($preview) ? $preview : [],
                session('usuario_id')
            );
            session()->forget('fiscal_order_invoice_preview');
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'Nota fiscal vinculada ao pedido.');
    }

    public function cancelInvoicePreview(int $pedido): RedirectResponse
    {
        session()->forget('fiscal_order_invoice_preview');

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'Comparação de nota fiscal cancelada.');
    }

    public function unlinkInvoice(int $pedido, int $nota): RedirectResponse
    {
        try {
            $this->pedidos->unlinkInvoice($this->pedidos->propertyId(), $pedido, $nota);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'Vínculo com nota fiscal removido.');
    }

    private function redirectToFinancialPanel(int $expenseId): RedirectResponse
    {
        return redirect()->route('financeiro.index', [
            'filtro' => 'despesas',
            'todos' => 1,
            'lancamento_id' => $expenseId,
        ]);
    }
}
