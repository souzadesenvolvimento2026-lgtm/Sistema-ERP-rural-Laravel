<?php

namespace App\Http\Controllers;

use App\Services\PlanejamentoFinanceiroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanejamentoFinanceiroController extends Controller
{
    public function index(PlanejamentoFinanceiroService $service): View
    {
        return view('orcamento.index', $service->indexData());
    }

    public function planejamentoSafra(Request $request, PlanejamentoFinanceiroService $service): View
    {
        return view('orcamento.planejamento', $service->planejamentoSafraData(
            $request->query('safra_planejamento')
        ));
    }

    public function create(PlanejamentoFinanceiroService $service): View
    {
        return view('orcamento.create', $service->formData());
    }

    public function store(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $service->criar($this->validated($request), session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Projeção financeira criada pelo Laravel.');
    }

    public function edit(int $projecao, PlanejamentoFinanceiroService $service): View
    {
        return view('orcamento.edit', $service->formData($projecao));
    }

    public function update(Request $request, int $projecao, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $service->atualizar($projecao, $this->validated($request), session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Projeção financeira atualizada pelo Laravel.');
    }

    public function destroy(int $projecao, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $service->excluir($projecao);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Projeção financeira excluída pelo Laravel.');
    }

    public function atualizarPlanejamentoEmLote(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'projecao_id' => ['required', 'array'],
            'projecao_id.*' => ['required', 'integer'],
            'categoria_id' => ['required', 'array'],
            'categoria_id.*' => ['required', 'integer'],
            'subcategoria_id' => ['nullable', 'array'],
            'mes_referencia' => ['required', 'array'],
            'mes_referencia.*' => ['required', 'string'],
            'safra_id' => ['nullable', 'array'],
            'cultura_id' => ['nullable', 'array'],
            'tipo_lancamento' => ['nullable', 'array'],
            'tipo_safra' => ['nullable', 'array'],
            'ano_safra' => ['nullable', 'array'],
            'quantidade' => ['nullable', 'array'],
            'unidade' => ['nullable', 'array'],
            'valor_unitario' => ['nullable', 'array'],
            'valor_projetado' => ['required', 'array'],
            'valor_projetado.*' => ['required', 'string'],
            'observacoes' => ['nullable', 'array'],
        ]);

        $total = $service->atualizarPlanejamentoEmLote($dados, session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', $total.' projecao(oes) atualizada(s).');
    }

    public function recorrente(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['nullable', 'integer'],
            'cultura_id' => ['nullable', 'integer'],
            'tipo_safra' => ['required', 'in:principal,safrinha'],
            'ano_safra' => ['required', 'string', 'max:40'],
            'categoria_id' => ['required', 'integer'],
            'subcategoria_id' => ['nullable', 'integer'],
            'mes_inicial' => ['required', 'date_format:Y-m'],
            'mes_final' => ['required', 'date_format:Y-m'],
            'valor_projetado' => ['required', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $total = $service->criarRecorrente($dados, session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', $total.' projecao(oes) recorrente(s) criada(s).');
    }

    public function atualizarBaseSafra(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'area_plantada' => ['nullable', 'string'],
            'producao_estimada' => ['nullable', 'string'],
            'preco_estimado' => ['nullable', 'string'],
        ]);

        $service->atualizarBaseSafra($dados);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Dados base da safra atualizados.');
    }

    public function criarCategoriaPlanejamento(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
        ]);

        $service->criarCategoriaPlanejamento($dados);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Categoria cadastrada para uso no planejamento.');
    }

    public function criarCulturaPlanejamento(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
        ]);

        $service->criarCulturaPlanejamento($dados);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Cultura cadastrada para uso no planejamento.');
    }

    public function criarSafraRetroativa(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:120'],
            'cultura_id' => ['nullable', 'integer'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'area_plantada' => ['nullable', 'string'],
            'producao_estimada' => ['nullable', 'string'],
            'producao_realizada' => ['nullable', 'string'],
            'preco_estimado' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->criarSafraRetroativa($dados);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Safra retroativa cadastrada e arquivada para consulta historica.');
    }

    public function salvarAnoAgricola(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'ano_inicio' => ['required', 'integer', 'between:2000,2100'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $anoInicio = $service->salvarAnoAgricola($dados);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Ano agricola '.$anoInicio.' salvo.');
    }

    public function criarAtividadePlanejada(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'talhao_id' => ['nullable', 'integer'],
            'area_executada' => ['nullable', 'string'],
            'tipo' => ['required', 'in:preparo_solo,plantio,manejo,colheita,monitoramento,recomendacao,outro'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'descricao' => ['nullable', 'string', 'max:180'],
            'responsavel' => ['nullable', 'string', 'max:120'],
            'servico' => ['nullable', 'string', 'max:180'],
            'produto' => ['nullable', 'string', 'max:120'],
            'custo_estimado' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->criarAtividadePlanejada($dados, session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Atividade planejada cadastrada.');
    }

    public function excluirAtividadePlanejada(int $atividade, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $service->excluirAtividadePlanejada($atividade);

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Atividade planejada removida.');
    }

    public function adicionarDespesaPlanejada(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'cultura_id' => ['nullable', 'integer'],
            'categoria_id' => ['required', 'integer'],
            'subcategoria_id' => ['nullable', 'integer'],
            'mes_referencia' => ['required', 'date_format:Y-m'],
            'valor_projetado' => ['required', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->adicionarDespesaPlanejada($dados, session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Despesa planejada adicionada.');
    }

    public function adicionarInsumoPlanejado(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'cultura_id' => ['nullable', 'integer'],
            'categoria_id' => ['required', 'integer'],
            'subcategoria_id' => ['nullable', 'integer'],
            'data_utilizacao' => ['required', 'date'],
            'quantidade' => ['required', 'string'],
            'unidade' => ['nullable', 'string', 'max:20'],
            'valor_unitario' => ['required', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->adicionarInsumoPlanejado($dados, session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', 'Insumo planejado adicionado.');
    }

    public function copiarSafraAnterior(Request $request, PlanejamentoFinanceiroService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['required', 'integer'],
            'cultura_id' => ['nullable', 'integer'],
        ]);

        $total = $service->copiarSafraAnterior($dados, session('usuario_id'));

        return redirect()
            ->route('orcamento.index')
            ->with('success', $total.' item(ns) copiado(s) da safra anterior.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo_lancamento' => ['required', 'in:receita,despesa'],
            'tipo_safra' => ['required', 'in:principal,safrinha'],
            'ano_safra' => ['required', 'string', 'max:40'],
            'mes_referencia' => ['required', 'date'],
            'safra_id' => ['nullable', 'integer'],
            'cultura_id' => ['nullable', 'integer'],
            'categoria_id' => ['required', 'integer'],
            'subcategoria_id' => ['nullable', 'integer'],
            'quantidade' => ['nullable', 'string'],
            'unidade' => ['nullable', 'string', 'max:20'],
            'valor_unitario' => ['nullable', 'string'],
            'valor_projetado' => ['required', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }
}
