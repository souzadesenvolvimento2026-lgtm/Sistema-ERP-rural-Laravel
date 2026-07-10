<?php

namespace App\Http\Controllers;

use App\Services\NotaFiscalXmlService;
use App\Services\NotaFiscalListagemService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotaFiscalController extends Controller
{
    public function index(Request $request, NotaFiscalListagemService $service): View
    {
        return view('fiscal.notas.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function create(): View
    {
        return view('fiscal.notas.create', [
            'activeModule' => 'fiscal',
            'preview' => session('fiscal_invoice_preview'),
        ]);
    }

    public function store(Request $request, NotaFiscalXmlService $service): RedirectResponse
    {
        $dados = $request->validate([
            'xml' => ['required', 'file', 'mimes:xml'],
        ]);

        try {
            $preview = $service->preview($dados['xml']);
            session(['fiscal_invoice_preview' => $preview]);
        } catch (\Throwable $e) {
            return back()->withErrors(['xml' => $e->getMessage()]);
        }

        return redirect()
            ->route('fiscal.notas.create')
            ->with('success', 'XML processado. Confira os dados antes de confirmar o lancamento.');
    }

    public function confirm(NotaFiscalXmlService $service): RedirectResponse
    {
        $preview = session('fiscal_invoice_preview');

        try {
            $invoiceId = $service->confirmarPreview(
                is_array($preview) ? $preview : [],
                app(FarmContext::class)->propertyId(),
                session('usuario_id')
            );
            session()->forget('fiscal_invoice_preview');
        } catch (\Throwable $e) {
            return redirect()
                ->route('fiscal.notas.create')
                ->withErrors(['xml' => $e->getMessage()]);
        }

        return redirect()
            ->route('fiscal.notas.show', ['nota' => $invoiceId])
            ->with('success', 'Nota fiscal lancada com status Aguardando aprovacao.');
    }

    public function cancelPreview(): RedirectResponse
    {
        session()->forget('fiscal_invoice_preview');

        return redirect()
            ->route('fiscal.notas.index')
            ->with('success', 'Conferencia da nota fiscal cancelada.');
    }

    public function show(int $nota, NotaFiscalListagemService $service): View
    {
        return view('fiscal.notas.show', $service->detalhe(app(FarmContext::class)->propertyId(), $nota));
    }

    public function approve(int $nota, NotaFiscalListagemService $service): RedirectResponse
    {
        try {
            $service->aprovar(app(FarmContext::class)->propertyId(), $nota, session('usuario_id'));
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('fiscal.notas.index')
            ->with('success', 'Nota fiscal aprovada pelo Laravel.');
    }

    public function xml(int $nota, NotaFiscalListagemService $service): BinaryFileResponse
    {
        return $service->baixarXml(app(FarmContext::class)->propertyId(), $nota);
    }
}
