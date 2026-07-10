<?php

namespace App\Http\Controllers;

use App\Services\SafraService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class SafraController extends Controller
{
    public function index(Request $request, SafraService $service): View
    {
        return view('safras.index', $service->pagina($this->propriedadeId(), $request));
    }

    public function create(): View
    {
        return view('safras.create', $this->formData());
    }

    public function store(Request $request, SafraService $service): RedirectResponse
    {
        $service->criar($this->validated($request), $this->propriedadeId(), session('usuario_id'));

        return redirect()
            ->route('safras.index')
            ->with('success', 'Safra criada.');
    }

    public function edit(int $safra, SafraService $service): View
    {
        return view('safras.edit', [
            ...$this->formData(),
            'safra' => $service->buscar($safra, $this->propriedadeId()),
        ]);
    }

    public function update(int $safra, Request $request, SafraService $service): RedirectResponse
    {
        $service->atualizar($safra, $this->validated($request), $this->propriedadeId(), session('usuario_id'));

        return redirect()
            ->route('safras.index', ['status' => 'todas'])
            ->with('success', 'Safra atualizada.');
    }

    public function status(int $safra, Request $request, SafraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:planejamento,em_andamento,colhida,encerrada'],
        ]);

        $service->atualizarStatus($safra, $this->propriedadeId(), $dados['status'], session('usuario_id'));

        return redirect()
            ->route('safras.index', ['status' => 'todas'])
            ->with('success', 'Status da safra atualizado.');
    }

    public function destroy(int $safra, Request $request, SafraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'senha_exclusao' => ['required', 'string'],
        ]);

        try {
            $service->excluirDefinitivo($safra, $this->propriedadeId(), session('usuario_id'), $dados['senha_exclusao']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('safras.index', ['status' => 'todas'])
                ->withErrors($exception->getMessage());
        }

        if ((int)session('safra_id') === $safra) {
            session()->forget('safra_id');
        }

        return redirect()
            ->route('safras.index', ['status' => 'todas'])
            ->with('success', 'Safra excluida definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'descricao' => ['required', 'string', 'max:120'],
            'cultura_id' => ['nullable', 'integer'],
            'safra_referencia' => ['required', 'in:primeira,segunda,terceira'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date'],
            'area_plantada' => ['nullable', 'string'],
            'producao_estimada' => ['nullable', 'string'],
            'preco_estimado' => ['nullable', 'string'],
            'status' => ['required', 'in:planejamento,em_andamento,colhida,encerrada'],
            'observacoes' => ['nullable', 'string'],
            'talhoes' => ['nullable', 'array'],
            'talhoes.*' => ['integer'],
        ]);
    }

    private function formData(): array
    {
        $propertyId = $this->propriedadeId();

        return [
            'activeModule' => 'safras',
            'culturas' => DB::table('culturas')->orderBy('nome')->get(['id', 'nome']),
            'talhoes' => DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome', 'area']),
        ];
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}
