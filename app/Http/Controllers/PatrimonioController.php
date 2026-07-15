<?php

namespace App\Http\Controllers;

use App\Services\PatrimonioService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatrimonioController extends Controller
{
    public function index(Request $request, PatrimonioService $service): View
    {
        return view('patrimonio.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function show(int $patrimonio, PatrimonioService $service): View
    {
        return view('patrimonio.show', $service->detalhe(app(FarmContext::class)->propertyId(), $patrimonio));
    }

    public function storeLancamento(Request $request, int $patrimonio, PatrimonioService $service): RedirectResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:abastecimento,manutencao_preventiva,manutencao_corretiva,troca_oleo,pecas,seguro,outro'],
            'data_lancamento' => ['required', 'date'],
            'descricao' => ['required', 'string', 'max:180'],
            'fornecedor' => ['nullable', 'string', 'max:150'],
            'safra_id' => ['nullable', 'integer'],
            'talhao_id' => ['nullable', 'integer'],
            'quantidade' => ['nullable', 'string'],
            'unidade' => ['nullable', 'string', 'max:30'],
            'valor_unitario' => ['nullable', 'string'],
            'valor_total' => ['nullable', 'string'],
            'horimetro' => ['nullable', 'string'],
            'odometro' => ['nullable', 'string'],
            'proxima_revisao_horas' => ['nullable', 'string'],
            'comprovante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->criarLancamento($dados, app(FarmContext::class)->propertyId(), $patrimonio, session('usuario_id'), $request->file('comprovante'));

        return redirect()
            ->route('patrimonio.index', ['patrimonio' => $patrimonio])
            ->with('success', 'Lançamento do patrimônio salvo.');
    }

    public function create(PatrimonioService $service): View
    {
        return view('patrimonio.create', [
            'activeModule' => 'patrimonio',
            'tipos' => $service->tipos(),
            'patrimonio' => null,
        ]);
    }

    public function store(Request $request, PatrimonioService $service): RedirectResponse
    {
        $dados = $this->validarPatrimonio($request);

        $patrimonioId = $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'), $request->file('nota_fiscal_arquivo'));

        return redirect()
            ->route('patrimonio.index', ['patrimonio' => $patrimonioId])
            ->with('success', 'Patrimônio criado.');
    }

    public function edit(int $patrimonio, PatrimonioService $service): View
    {
        return view('patrimonio.edit', [
            'activeModule' => 'patrimonio',
            'tipos' => $service->tipos(),
            'patrimonio' => $service->paraEdicao(app(FarmContext::class)->propertyId(), $patrimonio),
        ]);
    }

    public function update(Request $request, int $patrimonio, PatrimonioService $service): RedirectResponse
    {
        $dados = $this->validarPatrimonio($request);

        $service->atualizar($dados, app(FarmContext::class)->propertyId(), $patrimonio, session('usuario_id'), $request->file('nota_fiscal_arquivo'));

        return redirect()
            ->route('patrimonio.index', ['patrimonio' => $patrimonio])
            ->with('success', 'Patrimônio atualizado.');
    }

    public function toggleStatus(int $patrimonio, PatrimonioService $service): RedirectResponse
    {
        $ativo = $service->alternarStatus(app(FarmContext::class)->propertyId(), $patrimonio, session('usuario_id'));

        return redirect()
            ->route('patrimonio.index')
            ->with('success', $ativo ? 'Patrimônio reativado.' : 'Patrimônio inativado.');
    }

    public function updateValue(Request $request, int $patrimonio, PatrimonioService $service): RedirectResponse
    {
        $dados = $request->validate([
            'valor_aquisicao' => ['nullable', 'string'],
        ]);

        $service->atualizarValor(app(FarmContext::class)->propertyId(), $patrimonio, $dados['valor_aquisicao'] ?? '0', session('usuario_id'));

        return redirect()
            ->route('patrimonio.index', ['patrimonio' => $patrimonio])
            ->with('success', 'Valor do patrimônio atualizado.');
    }

    private function validarPatrimonio(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:trator,colheitadeira,plantadeira,pulverizador,caminhao,implemento,outro'],
            'tipo_outro' => ['nullable', 'string', 'max:120'],
            'marca_modelo' => ['nullable', 'string', 'max:150'],
            'identificacao' => ['nullable', 'string', 'max:80'],
            'descricao_patrimonio' => ['nullable', 'string'],
            'ano' => ['nullable', 'integer'],
            'valor_aquisicao' => ['nullable', 'string'],
            'data_aquisicao' => ['nullable', 'date'],
            'fornecedor' => ['nullable', 'string', 'max:180'],
            'fornecedor_doc' => ['nullable', 'string', 'max:20'],
            'nota_fiscal_numero' => ['nullable', 'string', 'max:80'],
            'nota_fiscal_serie' => ['nullable', 'string', 'max:30'],
            'nota_fiscal_chave' => ['nullable', 'string', 'max:60'],
            'nota_fiscal_arquivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'controla_horimetro' => ['nullable', 'boolean'],
            'controla_odometro' => ['nullable', 'boolean'],
            'horimetro_atual' => ['nullable', 'string'],
            'odometro_atual' => ['nullable', 'string'],
        ]);
    }
}
