<?php

namespace App\Http\Controllers;

use App\Services\ProdutorService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProdutorController extends Controller
{
    public function index(ProdutorService $service): View
    {
        return view('fiscal.produtores.index', $service->pagina($this->propriedadeId()));
    }

    public function store(Request $request, ProdutorService $service): RedirectResponse
    {
        $service->criar($this->validated($request), $this->propriedadeId());

        return redirect()
            ->route('fiscal.produtores.index')
            ->with('success', 'Produtor cadastrado.');
    }

    public function update(int $produtor, Request $request, ProdutorService $service): RedirectResponse
    {
        $service->atualizar($produtor, $this->validated($request), $this->propriedadeId());

        return redirect()
            ->route('fiscal.produtores.index')
            ->with('success', 'Produtor atualizado.');
    }

    public function toggle(int $produtor, ProdutorService $service): RedirectResponse
    {
        $service->alternarAtivo($produtor, $this->propriedadeId());

        return redirect()
            ->route('fiscal.produtores.index')
            ->with('success', 'Status do produtor atualizado.');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'participacao_percentual' => $this->normalizarPercentual($request->input('participacao_percentual')),
        ]);

        return $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:30'],
            'participacao_percentual' => ['nullable', 'numeric', 'between:0,100'],
        ], [
            'participacao_percentual.between' => 'A participação deve ficar entre 0 e 100%.',
            'participacao_percentual.numeric' => 'Informe uma participação válida.',
        ]);
    }

    private function normalizarPercentual($value): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $value = trim((string)$value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}
