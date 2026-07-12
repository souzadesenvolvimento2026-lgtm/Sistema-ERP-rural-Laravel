<?php

namespace App\Http\Controllers;

use App\Services\ColheitaService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ColheitaController extends Controller
{
    public function index(Request $request, ColheitaService $service): View
    {
        return view('colheita.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function create(ColheitaService $service): View
    {
        $propertyId = app(FarmContext::class)->propertyId();

        return view('colheita.create', $service->formData($propertyId));
    }

    public function store(Request $request, ColheitaService $service): RedirectResponse
    {
        $dados = $this->validated($request);

        $propriedadeId = app(FarmContext::class)->propertyId();

        try {
            $service->criar($dados, $propriedadeId, session('usuario_id'));
        } catch (\RuntimeException $e) {
            report($e);

            return back()->withInput()->with('error', $e->getMessage());
        }

        $safraId = $service->validSafraId($dados['safra_id'] ?? null, $propriedadeId);

        return redirect()
            ->route('colheita.index', $safraId ? ['safra_id' => $safraId] : [])
            ->with('success', 'Colheita criada.');
    }

    public function edit(int $colheita, ColheitaService $service): View
    {
        $propertyId = app(FarmContext::class)->propertyId();

        return view('colheita.edit', [
            ...$service->formData($propertyId),
            'carga' => $service->buscar($colheita, $propertyId),
        ]);
    }

    public function update(Request $request, int $colheita, ColheitaService $service): RedirectResponse
    {
        $dados = $this->validated($request);
        $propriedadeId = app(FarmContext::class)->propertyId();

        try {
            $service->atualizar($colheita, $dados, $propriedadeId, session('usuario_id'));
        } catch (\RuntimeException $e) {
            report($e);

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('colheita.index', [
                'safra_id' => $service->validSafraId($dados['safra_id'] ?? null, $propriedadeId),
                'talhao_id' => $dados['talhao_id'] ?? null,
            ])
            ->with('success', 'Carga de colheita atualizada.');
    }

    public function destroy(int $colheita, ColheitaService $service): RedirectResponse
    {
        $service->excluir($colheita, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return back()->with('success', 'Carga de colheita excluida.');
    }

    public function finalizarTalhao(Request $request, ColheitaService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'talhao_id' => ['required', 'integer'],
        ]);

        try {
            $service->finalizarTalhao(
                app(FarmContext::class)->propertyId(),
                (int) $dados['safra_id'],
                (int) $dados['talhao_id'],
                session('usuario_id')
            );
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('colheita.index', ['safra_id' => $dados['safra_id'], 'talhao_id' => $dados['talhao_id']])
            ->with('success', 'Talhao finalizado nesta safra.');
    }

    public function reabrirTalhao(Request $request, ColheitaService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'talhao_id' => ['required', 'integer'],
        ]);

        try {
            $service->reabrirTalhao(
                app(FarmContext::class)->propertyId(),
                (int) $dados['safra_id'],
                (int) $dados['talhao_id'],
                session('usuario_id')
            );
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('colheita.index', ['safra_id' => $dados['safra_id'], 'talhao_id' => $dados['talhao_id']])
            ->with('success', 'Talhao reaberto para ajustes.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'safra_id' => ['required', 'integer'],
            'talhao_id' => ['required', 'integer'],
            'ticket_numero' => ['nullable', 'string', 'max:40'],
            'motorista' => ['nullable', 'string', 'max:120'],
            'veiculo_placa' => ['nullable', 'string', 'max:80'],
            'destino_producao' => ['nullable', 'string', 'max:40'],
            'local_destino' => ['nullable', 'string', 'max:160'],
            'data_colheita' => ['required', 'date'],
            'peso_bruto_kg' => ['nullable', 'string'],
            'tara_kg' => ['nullable', 'string'],
            'desconto_kg' => ['nullable', 'string'],
            'peso_final_kg' => ['nullable', 'string'],
            'area_colhida' => ['nullable', 'string'],
            'umidade' => ['nullable', 'string'],
            'impureza_pct' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }
}
