<?php

namespace App\Http\Controllers;

use App\Services\AtividadeCampoService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AtividadeCampoController extends Controller
{
    public function index(Request $request, AtividadeCampoService $service): View
    {
        return view('talhoes.atividades.index', $service->pagina(
            app(FarmContext::class)->propertyId(),
            $request->integer('safra_id') ?: null
        ));
    }

    public function store(Request $request, AtividadeCampoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['nullable', 'integer'],
            'talhao_id' => ['nullable', 'integer'],
            'area_executada' => ['nullable', 'string'],
            'tipo' => ['required', 'in:preparo_solo,plantio,manejo,colheita,monitoramento,recomendacao,outro'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date'],
            'status' => ['required', 'in:planejada,em_execucao,concluida,cancelada'],
            'descricao' => ['required', 'string', 'max:180'],
            'responsavel' => ['nullable', 'string', 'max:120'],
            'servico' => ['nullable', 'string', 'max:180'],
            'produto' => ['nullable', 'string', 'max:120'],
            'dose' => ['nullable', 'string', 'max:60'],
            'custo_estimado' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $propriedadeId = app(FarmContext::class)->propertyId();
        $service->criar($dados, $propriedadeId, session('usuario_id'));
        $safraId = $this->safraValidaParaRedirect($dados['safra_id'] ?? null, $propriedadeId);

        return redirect()
            ->route('talhoes.atividades.index', $safraId ? ['safra_id' => $safraId] : [])
            ->with('success', 'Atividade registrada pelo Laravel.');
    }

    public function status(int $atividade, Request $request, AtividadeCampoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:planejada,em_execucao,concluida,cancelada'],
        ]);

        $service->atualizarStatus($atividade, app(FarmContext::class)->propertyId(), $dados['status']);

        return redirect()
            ->route('talhoes.atividades.index')
            ->with('success', 'Status atualizado.');
    }

    private function safraValidaParaRedirect($safraId, int $propriedadeId): ?int
    {
        $safraId = (int)($safraId ?: 0);
        if ($safraId <= 0) {
            return null;
        }

        return DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->exists()
            ? $safraId
            : null;
    }
}
