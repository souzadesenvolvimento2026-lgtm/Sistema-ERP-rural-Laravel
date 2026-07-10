<?php

namespace App\Http\Controllers;

use App\Services\CertificadoDigitalService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificadoDigitalController extends Controller
{
    public function index(CertificadoDigitalService $service): View
    {
        return view('fiscal.certificados.index', $service->pagina($this->propriedadeId()));
    }

    public function store(Request $request, CertificadoDigitalService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome_identificacao' => ['required', 'string', 'max:120'],
            'tipo_certificado' => ['required', 'in:A1,A3'],
            'ambiente' => ['required', 'in:homologacao,producao'],
            'titular' => ['nullable', 'string', 'max:180'],
            'cpf_cnpj' => ['nullable', 'string', 'max:20'],
            'numero_serie' => ['nullable', 'string', 'max:120'],
            'emissor' => ['nullable', 'string', 'max:180'],
            'validade_inicio' => ['nullable', 'date'],
            'validade_fim' => ['nullable', 'date'],
            'senha_certificado' => ['required_if:tipo_certificado,A1', 'nullable', 'string'],
            'principal' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string'],
            'certificado' => [
                Rule::requiredIf($request->input('tipo_certificado') === 'A1'),
                'nullable',
                'file',
                'max:8192',
                'mimes:pfx,p12',
            ],
        ], [
            'certificado.required' => 'Envie o arquivo do certificado A1.',
            'certificado.mimes' => 'O certificado A1 precisa estar em formato .pfx ou .p12.',
            'senha_certificado.required_if' => 'Informe a senha do certificado A1.',
        ]);

        try {
            $service->criar($dados, $this->propriedadeId(), session('usuario_id'), $request->file('certificado'));
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('fiscal.certificados.index')
                ->withErrors(['certificado' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('fiscal.certificados.index')
            ->with('success', 'Certificado digital cadastrado.');
    }

    public function principal(int $certificado, CertificadoDigitalService $service): RedirectResponse
    {
        $service->definirPrincipal($certificado, $this->propriedadeId());

        return redirect()
            ->route('fiscal.certificados.index')
            ->with('success', 'Certificado definido como principal.');
    }

    public function desativar(int $certificado, CertificadoDigitalService $service): RedirectResponse
    {
        $service->desativar($certificado, $this->propriedadeId(), session('usuario_id'));

        return redirect()
            ->route('fiscal.certificados.index')
            ->with('success', 'Certificado desativado.');
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}
