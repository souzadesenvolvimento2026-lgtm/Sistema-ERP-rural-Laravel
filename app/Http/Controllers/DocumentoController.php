<?php

namespace App\Http\Controllers;

use App\Services\DocumentoService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoController extends Controller
{
    public function index(DocumentoService $service): View
    {
        return view('fiscal.documentos.index', $service->pagina($this->propriedadeId()));
    }

    public function store(Request $request, DocumentoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['nullable', 'integer'],
            'tipo' => ['required', 'in:nota_fiscal,contrato,receituario,boleto,comprovante,analise_solo,mapa,outro'],
            'titulo' => ['required', 'string', 'max:180'],
            'numero' => ['nullable', 'string', 'max:80'],
            'pessoa' => ['nullable', 'string', 'max:150'],
            'data_documento' => ['nullable', 'date'],
            'valor' => ['nullable', 'string'],
            'status' => ['required', 'in:pendente,conferido,arquivado'],
            'observacoes' => ['nullable', 'string'],
            'arquivo' => ['nullable', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $service->criar($dados, $this->propriedadeId(), session('usuario_id'), $request->file('arquivo'));

        return redirect()
            ->route('fiscal.documentos.index')
            ->with('success', 'Documento arquivado.');
    }

    public function status(int $documento, Request $request, DocumentoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:pendente,conferido,arquivado'],
        ]);

        $service->atualizarStatus($documento, $this->propriedadeId(), $dados['status']);

        return redirect()
            ->route('fiscal.documentos.index')
            ->with('success', 'Status do documento atualizado.');
    }

    public function conferir(int $documento, DocumentoService $service): RedirectResponse
    {
        $service->atualizarStatus($documento, $this->propriedadeId(), 'conferido');

        return redirect()
            ->route('fiscal.documentos.index')
            ->with('success', 'Documento conferido.');
    }

    public function arquivo(int $documento, DocumentoService $service): BinaryFileResponse
    {
        return $service->baixarArquivo($documento, $this->propriedadeId());
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}
