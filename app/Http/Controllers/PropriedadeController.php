<?php

namespace App\Http\Controllers;

use App\Services\PropriedadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PropriedadeController extends Controller
{
    public function index(Request $request, PropriedadeService $service): View
    {
        return view('propriedades.index', $service->pagina($request));
    }

    public function create(): View
    {
        return view('propriedades.create', [
            'activeModule' => 'propriedades',
            'aprovadores' => PropriedadeService::aprovadores(),
        ]);
    }

    public function edit(int $propriedade, PropriedadeService $service): View
    {
        return view('propriedades.edit', [
            'activeModule' => 'propriedades',
            'propriedade' => $service->buscar($propriedade),
            'aprovadores' => PropriedadeService::aprovadores(),
        ]);
    }

    public function store(Request $request, PropriedadeService $service): RedirectResponse
    {
        $dados = $request->validate($this->validationRules());

        $service->criar($dados, $request->file('kml_area'), session('usuario_id'));

        return redirect()
            ->route('propriedades.index')
            ->with('success', 'Propriedade criada pelo Laravel.');
    }

    public function update(Request $request, int $propriedade, PropriedadeService $service): RedirectResponse
    {
        $dados = $request->validate($this->validationRules());

        try {
            $service->atualizar($propriedade, $dados, $request->file('kml_area'), session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('propriedades.index')
            ->with('success', 'Propriedade atualizada.');
    }

    public function toggleStatus(int $propriedade, PropriedadeService $service): RedirectResponse
    {
        $ativo = $service->alternarStatus($propriedade);

        return redirect()
            ->route('propriedades.index', ['status' => $ativo ? 'ativas' : 'inativas'])
            ->with('success', $ativo ? 'Propriedade reativada.' : 'Propriedade inativada.');
    }

    private function validationRules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'size:2'],
            'area_total' => ['nullable', 'string'],
            'responsavel' => ['nullable', 'string', 'max:100'],
            'inscricao_estadual' => ['nullable', 'string', 'max:50'],
            'cnpj_cpf' => ['nullable', 'string', 'max:20'],
            'plano' => ['required', 'in:basico,avancado,premium'],
            'pecuaria_ativa' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'string'],
            'longitude' => ['nullable', 'string'],
            'regiao_cotacao' => ['nullable', 'string', 'max:160'],
            'aprovador_usuario_id' => ['nullable', 'integer'],
            'kml_area' => ['nullable', 'file', 'mimes:kml,kmz,shp,zip'],
        ];
    }
}
