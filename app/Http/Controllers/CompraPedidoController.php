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

    public function index(): View
    {
        $propertyId = $this->pedidos->propertyId();
        $pedidos = $this->pedidos->listOrders($propertyId);

        return view('compras.pedidos.index', [
            'activeModule' => 'compras',
            'pedidos' => $pedidos,
            'totais' => $this->pedidos->totals($pedidos),
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
        ]);

        $orderId = $this->pedidos->createOrder($request, $this->pedidos->propertyId());

        return redirect()
            ->route('compras.pedidos.show', $orderId)
            ->with('success', 'Pedido criado no banco atual pelo Laravel.');
    }

    public function show(int $pedido): View
    {
        return view('compras.pedidos.show', $this->pedidos->showData(
            $this->pedidos->propertyId(),
            $pedido,
            session('fiscal_order_invoice_preview'),
        ));
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
            ->with('success', 'Pedido atualizado pelo Laravel.');
    }

    public function approve(Request $request, int $pedido): RedirectResponse
    {
        try {
            $this->pedidos->approveOrder(
                $this->pedidos->propertyId(),
                $pedido,
                session('usuario_id'),
                $request->boolean('confirmar_aprovacao')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('compras.pedidos.show', $pedido)
            ->with('success', 'Pedido aprovado/baixado, lancado no financeiro e incorporado ao estoque.');
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
            ->with('success', 'Comparacao gerada. Confira antes de confirmar o vinculo.');
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
            ->with('success', 'XML importado e comparacao gerada. Confira antes de confirmar o vinculo.');
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
            ->with('success', 'Comparacao de nota fiscal cancelada.');
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
            ->with('success', 'Vinculo com nota fiscal removido.');
    }
}
