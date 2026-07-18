<?php

namespace App\Http\Controllers;

use App\Services\CompraFornecedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CompraFornecedorController extends Controller
{
    public function __construct(private CompraFornecedorService $fornecedores)
    {
    }

    public function index(Request $request): View
    {
        $propertyId = $this->fornecedores->propertyId();
        $filters = $this->fornecedores->filters($request);
        $fornecedores = $this->fornecedores->listSuppliers($propertyId, $filters);

        return view('compras.fornecedores.index', [
            'activeModule' => 'compras',
            'filters' => $filters,
            'fornecedores' => $fornecedores,
            'statusOptions' => $this->fornecedores->statusOptions(),
            'totais' => $this->fornecedores->totals($fornecedores),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:160'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->fornecedores->store($dados, $this->fornecedores->propertyId());
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors('Não foi possível cadastrar o fornecedor.');
        }

        return redirect()
            ->route('compras.fornecedores.index')
            ->with('success', 'Fornecedor cadastrado com sucesso.');
    }
}
