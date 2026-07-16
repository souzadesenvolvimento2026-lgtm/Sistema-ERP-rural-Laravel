<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    public function index(Request $request, AuditoriaService $service): View
    {
        abort_unless($this->podeVerAuditoria(), 403);

        return view('auditoria.index', $service->dados($request));
    }

    public function export(Request $request, AuditoriaService $service): StreamedResponse
    {
        abort_unless($this->podeVerAuditoria(), 403);

        $export = $service->exportar($request);

        return response()->streamDownload(function () use ($export, $service): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export['headers'], ';');

            foreach ($export['rows'] as $row) {
                fputcsv($handle, [
                    $service->valorExportacao($row->criado_em_legivel),
                    $service->valorExportacao($row->usuario.' - '.$row->usuario_email),
                    $service->valorExportacao($row->propriedade),
                    $service->valorExportacao($row->lancamento),
                    $service->valorExportacao($row->acao_legivel),
                    $service->valorExportacao($row->onde.' ('.$row->tabela_tecnica.')'),
                    $service->valorExportacao($row->registro),
                    $service->valorExportacao($row->tipo_despesa),
                    $service->valorExportacao($row->detalhes),
                    $service->valorExportacao($row->ip_cliente),
                    $service->valorExportacao($row->ip_proxy),
                    $service->valorExportacao($row->cf_ray),
                ], ';');
            }

            fclose($handle);
        }, $export['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function details(int $log, AuditoriaService $service): JsonResponse
    {
        abort_unless($this->podeVerAuditoria(), 403);

        $detail = $service->detalhar($log);

        return response()->json([
            'id' => $detail->id,
            'criado_em' => $detail->criado_em_legivel,
            'usuario_nome' => $detail->usuario_nome,
            'usuario_email' => $detail->usuario_email,
            'usuario_perfil' => $detail->usuario_perfil,
            'propriedade' => $detail->propriedade,
            'lancamento' => $detail->lancamento,
            'acao_legivel' => $detail->acao_legivel,
            'acao_tecnica' => $detail->acao_tecnica,
            'onde' => $detail->onde,
            'tabela_tecnica' => $detail->tabela_tecnica,
            'tipo_despesa' => $detail->tipo_despesa,
            'categoria_despesa' => $detail->categoria_despesa,
            'registro' => $detail->registro,
            'detalhes' => $detail->detalhes,
            'ip_cliente' => $detail->ip_cliente,
            'ip_proxy' => $detail->ip_proxy,
            'cf_ray' => $detail->cf_ray,
            'host' => $detail->host,
            'rota' => $detail->rota,
            'metodo' => $detail->metodo,
            'user_agent' => $detail->user_agent,
        ]);
    }

    private function podeVerAuditoria(): bool
    {
        return in_array((string)session('perfil'), [
            'administrador_sistema',
            'gerencia_sistema',
            'colaborador_sistema',
            'gestor_propriedade',
            'administrador',
        ], true);
    }
}
