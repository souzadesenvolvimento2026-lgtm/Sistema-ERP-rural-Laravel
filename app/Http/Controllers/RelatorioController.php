<?php

namespace App\Http\Controllers;

use App\Services\RelatorioFinanceiroService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RelatorioController extends Controller
{
    public function index(): View
    {
        return view('relatorios.index', ['activeModule' => 'relatorios']);
    }

    public function dre(Request $request, RelatorioFinanceiroService $service): View
    {
        return view('relatorios.dre', $service->dre([
            'safras' => $request->array('safras'),
            'safra_id' => $request->integer('safra_id') ?: null,
            'data_inicio' => $request->query('data_inicio'),
            'data_fim' => $request->query('data_fim'),
        ]));
    }

    public function fluxoCaixa(Request $request, RelatorioFinanceiroService $service): View
    {
        return view('relatorios.fluxo-caixa', $service->fluxoCaixa([
            'safras' => $request->array('safras'),
            'safra_id' => $request->integer('safra_id') ?: null,
            'data_inicio' => $request->query('data_inicio'),
            'data_fim' => $request->query('data_fim'),
        ]));
    }

    public function orcadoRealizado(Request $request, RelatorioFinanceiroService $service): View
    {
        $filtros = [
            'safra_id' => $request->integer('safra_id') ?: null,
            'categoria_id' => $request->integer('categoria_id') ?: null,
            'tipo' => $request->query('tipo'),
            'data_inicio' => $request->query('data_inicio'),
            'data_fim' => $request->query('data_fim'),
        ];
        $propertyId = app(FarmContext::class)->propertyId();

        return view('relatorios.orcado-realizado', [
            ...$service->orcadoRealizado($filtros),
            'filtros' => [
                'safra_id' => $filtros['safra_id'],
                'categoria_id' => $filtros['categoria_id'],
                'tipo' => in_array($filtros['tipo'], ['receita', 'custos_despesas'], true) ? $filtros['tipo'] : 'todos',
                'data_inicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filtros['data_inicio']) ? $filtros['data_inicio'] : null,
                'data_fim' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filtros['data_fim']) ? $filtros['data_fim'] : null,
            ],
            'safras' => DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->orderByDesc('data_inicio')
                ->get(['id', 'descricao']),
            'categoriasFiltro' => DB::table('categorias')
                ->where('ativo', 1)
                ->whereNull('categoria_pai_id')
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    public function categorias(Request $request, RelatorioFinanceiroService $service): View
    {
        return view('relatorios.categorias', $service->categorias([
            'tipo' => $request->query('tipo'),
            'safra_id' => $request->integer('safra_id') ?: null,
            'categoria_id' => $request->integer('categoria_id') ?: null,
            'talhao_id' => $request->integer('talhao_id') ?: null,
            'data_inicio' => $request->query('data_inicio'),
            'data_fim' => $request->query('data_fim'),
        ]));
    }

    public function safra(Request $request, RelatorioFinanceiroService $service): View
    {
        return view('relatorios.safra', $service->safra($request->integer('safra_id') ?: null));
    }

    public function talhao(Request $request, RelatorioFinanceiroService $service): View
    {
        return view('relatorios.talhao', $service->talhao($request->integer('safra_id') ?: null));
    }

    public function kpis(Request $request, RelatorioFinanceiroService $service): View
    {
        return view('relatorios.kpis.index', $service->kpis($request->integer('safra_id') ?: null));
    }
}
