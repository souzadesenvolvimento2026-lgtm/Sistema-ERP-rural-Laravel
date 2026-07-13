<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\ContaBancariaService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ContaBancariaController extends Controller
{
    public function index(ContaBancariaService $service): View
    {
        return view('financeiro.contas.index', [
            ...$service->pagina($this->propriedadeId()),
            'canManageFinance' => $this->canManageFinance(),
        ]);
    }

    public function store(Request $request, ContaBancariaService $service): RedirectResponse
    {
        $this->authorizeManageFinance();
        $dados = $this->validated($request);

        try {
            $service->criar($dados, $this->propriedadeId());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Conta bancária criada.');
    }

    public function edit(int $conta, ContaBancariaService $service): View
    {
        $this->authorizeManageFinance();

        return view('financeiro.contas.edit', [
            ...$service->pagina($this->propriedadeId()),
            'conta' => $service->buscar($conta, $this->propriedadeId()),
            'canManageFinance' => $this->canManageFinance(),
        ]);
    }

    public function update(Request $request, int $conta, ContaBancariaService $service): RedirectResponse
    {
        $this->authorizeManageFinance();
        $dados = $this->validated($request);

        try {
            $service->atualizar($conta, $dados, $this->propriedadeId());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Conta bancária atualizada.');
    }

    public function toggleStatus(int $conta, ContaBancariaService $service): RedirectResponse
    {
        $this->authorizeManageFinance();

        try {
            $service->alternarStatus($conta, $this->propriedadeId());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Status da conta atualizado.');
    }

    public function transfer(Request $request, ContaBancariaService $service): RedirectResponse
    {
        $this->authorizeManageFinance();

        $dados = $request->validate([
            'origem' => ['required', 'integer'],
            'destino' => ['required', 'integer'],
            'valor' => ['required', 'string'],
            'data_transferencia' => ['nullable', 'date'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'return_route' => ['nullable', 'in:financeiro.index,financeiro.contas.index'],
        ]);

        try {
            $service->registrarTransferencia($dados, $this->propriedadeId(), session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['transferencia' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['transferencia' => $exception->getMessage()]);
        }

        return redirect()
            ->route($dados['return_route'] ?? 'financeiro.contas.index')
            ->with('success', 'Transferência registrada.');
    }

    public function editTransfer(int $transferencia, ContaBancariaService $service): View
    {
        $this->authorizeManageFinance();

        return view('financeiro.contas.index', [
            ...$service->pagina($this->propriedadeId()),
            'transferenciaEditando' => $service->buscarTransferencia($transferencia, $this->propriedadeId()),
            'canManageFinance' => $this->canManageFinance(),
        ]);
    }

    public function updateTransfer(Request $request, int $transferencia, ContaBancariaService $service): RedirectResponse
    {
        $this->authorizeManageFinance();

        $dados = $request->validate([
            'origem' => ['required', 'integer'],
            'destino' => ['required', 'integer'],
            'valor' => ['required', 'string'],
            'data_transferencia' => ['nullable', 'date'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->atualizarTransferencia($transferencia, $dados, $this->propriedadeId(), session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['transferencia' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['transferencia' => $exception->getMessage()]);
        }

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Transferência atualizada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'in:conta_corrente,conta_poupanca,caixa_interno,investimento'],
            'banco' => ['nullable', 'string', 'max:80'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero_conta' => ['nullable', 'string', 'max:30'],
            'saldo_inicial' => ['nullable', 'string'],
        ]);
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }

    private function canManageFinance(): bool
    {
        return app(ProfileAccess::class)->canManagePropertyFinance(session('perfil'));
    }

    private function authorizeManageFinance(): void
    {
        abort_unless($this->canManageFinance(), 403);
    }
}
