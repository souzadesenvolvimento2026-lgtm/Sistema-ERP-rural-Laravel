<?php

namespace App\Http\Controllers;

use App\Services\TalhaoService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class TalhaoController extends Controller
{
    public function index(Request $request, TalhaoService $service): View
    {
        return view('talhoes.index', $service->pagina($this->propriedadeId(), $request));
    }

    public function mapa(TalhaoService $service): View
    {
        return view('talhoes.mapa', $service->mapa($this->propriedadeId()));
    }

    public function exportarKml(TalhaoService $service): Response
    {
        return $service->exportarKmlPropriedade($this->propriedadeId());
    }

    public function exportarTalhao(int $talhao, Request $request, TalhaoService $service): Response|BinaryFileResponse
    {
        return $service->exportarTalhao($talhao, $this->propriedadeId(), (string)$request->query('formato', 'kml'));
    }

    public function create(): View
    {
        return view('talhoes.create', [
            'activeModule' => 'talhoes',
        ]);
    }

    public function store(Request $request, TalhaoService $service): RedirectResponse
    {
        $service->criar($this->validated($request), $this->propriedadeId(), session('usuario_id'));

        return redirect()
            ->route('talhoes.index')
            ->with('success', 'Talhão criado.');
    }

    public function edit(int $talhao, TalhaoService $service): View
    {
        return view('talhoes.edit', [
            'activeModule' => 'talhoes',
            'talhao' => $service->buscar($talhao, $this->propriedadeId()),
        ]);
    }

    public function update(int $talhao, Request $request, TalhaoService $service): RedirectResponse
    {
        $service->atualizar($talhao, $this->validated($request), $this->propriedadeId(), session('usuario_id'));

        return redirect()
            ->route('talhoes.index', ['status' => 'todos'])
            ->with('success', 'Talhão atualizado.');
    }

    public function toggleStatus(int $talhao, TalhaoService $service): RedirectResponse
    {
        try {
            $service->alternarAtivo($talhao, $this->propriedadeId(), session('usuario_id'));
        } catch (RuntimeException $e) {
            return redirect()
                ->route('talhoes.index', ['status' => 'todos'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('talhoes.index', ['status' => 'todos'])
            ->with('success', 'Status do talhão atualizado.');
    }

    public function unificar(Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'talhao_destino_id' => ['required', 'integer'],
            'talhoes_origem' => ['required', 'array', 'min:1'],
            'talhoes_origem.*' => ['integer'],
            'somar_area' => ['nullable', 'boolean'],
        ]);

        $total = $service->unificar(
            $this->propriedadeId(),
            (int)$dados['talhao_destino_id'],
            $dados['talhoes_origem'],
            (bool)($dados['somar_area'] ?? false),
            session('usuario_id')
        );

        return redirect()
            ->route('talhoes.index', ['status' => 'todos'])
            ->with('success', $total.' talhao(es) unificado(s).');
    }

    public function importarGeo(Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'geo' => ['required', 'file', 'max:20480'],
            'nome_importacao' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $result = $service->importarArquivoGeo(
                $this->propriedadeId(),
                $dados['geo'],
                $dados['nome_importacao'] ?? null,
                session('usuario_id')
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('talhoes.index', ['status' => 'todos'])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('talhoes.index', ['status' => 'todos'])
            ->with('success', $result['imported'].' talhao(es) importado(s) de '.$result['source'].'.');
    }

    public function storePoligono(Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'talhao_id' => ['nullable', 'integer'],
            'nome' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string'],
            'coordenadas_json' => ['required', 'string'],
        ]);

        if (!empty($dados['talhao_id'])) {
            $service->atualizarPoligono((int)$dados['talhao_id'], $dados, $this->propriedadeId(), session('usuario_id'));

            return redirect()
                ->route('talhoes.mapa')
                ->with('success', 'Desenho do talhão atualizado pelo mapa.');
        }

        $service->criarPorPoligono($dados, $this->propriedadeId(), session('usuario_id'));

        return redirect()
            ->route('talhoes.mapa')
            ->with('success', 'Talhão criado pelo mapa.');
    }

    public function atualizarDadosMapa(int $talhao, Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'area' => ['nullable', 'string'],
            'descricao' => ['nullable', 'string'],
        ]);

        try {
            $service->atualizarDadosMapa($talhao, $this->propriedadeId(), $dados, session('usuario_id'));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('talhoes.mapa')->withErrors($e->getMessage());
        }

        return redirect()->route('talhoes.mapa')->with('success', 'Dados do talhao atualizados pelo mapa.');
    }

    public function salvarExclusao(int $talhao, Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'exclusao_json' => ['required', 'string'],
        ]);

        try {
            $service->salvarExclusao($talhao, $this->propriedadeId(), $dados['exclusao_json'], session('usuario_id'));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('talhoes.mapa')->withErrors($e->getMessage());
        }

        return redirect()->route('talhoes.mapa')->with('success', 'Area excluida salva e talhao recalculado.');
    }

    public function limparExclusoes(int $talhao, TalhaoService $service): RedirectResponse
    {
        try {
            $service->limparExclusoes($talhao, $this->propriedadeId(), session('usuario_id'));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('talhoes.mapa')->withErrors($e->getMessage());
        }

        return redirect()->route('talhoes.mapa')->with('success', 'Areas excluidas removidas do talhao.');
    }

    public function salvarPivo(int $talhao, Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'pivo_lat' => ['required', 'string'],
            'pivo_lng' => ['required', 'string'],
            'pivo_raio_m' => ['required', 'string'],
        ]);

        try {
            $service->salvarPivo($talhao, $this->propriedadeId(), $dados, session('usuario_id'));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('talhoes.mapa')->withErrors($e->getMessage());
        }

        return redirect()->route('talhoes.mapa')->with('success', 'Pivo salvo no talhao.');
    }

    public function criarPivo(Request $request, TalhaoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'pivo_lat' => ['required', 'string'],
            'pivo_lng' => ['required', 'string'],
            'pivo_raio_m' => ['required', 'string'],
        ]);

        try {
            $service->criarPivoComoTalhao($this->propriedadeId(), $dados, session('usuario_id'));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('talhoes.mapa')->withErrors($e->getMessage());
        }

        return redirect()->route('talhoes.mapa')->with('success', 'Pivo salvo como novo talhao.');
    }

    public function removerPivo(int $talhao, TalhaoService $service): RedirectResponse
    {
        try {
            $service->removerPivo($talhao, $this->propriedadeId(), session('usuario_id'));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('talhoes.mapa')->withErrors($e->getMessage());
        }

        return redirect()->route('talhoes.mapa')->with('success', 'Pivo removido do talhao.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'area' => ['nullable', 'string'],
            'area_bruta' => ['nullable', 'string'],
            'area_excluida_ha' => ['nullable', 'string'],
            'descricao' => ['nullable', 'string'],
            'latitude' => ['nullable', 'string'],
            'longitude' => ['nullable', 'string'],
            'geometria_tipo' => ['nullable', 'in:polygon,line,point'],
            'pivo_ativo' => ['nullable', 'boolean'],
            'pivo_lat' => ['nullable', 'string'],
            'pivo_lng' => ['nullable', 'string'],
            'pivo_raio_m' => ['nullable', 'string'],
            'pivo_area_ha' => ['nullable', 'string'],
        ]);
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}
