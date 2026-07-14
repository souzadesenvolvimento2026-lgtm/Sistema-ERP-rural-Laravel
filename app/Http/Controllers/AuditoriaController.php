<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
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

        return response()->streamDownload(function () use ($export): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export['headers'], ';');

            foreach ($export['rows'] as $row) {
                fputcsv($handle, [
                    $row->criado_em,
                    $row->usuario,
                    $row->propriedade,
                    $row->lancamento,
                    $row->acao_legivel,
                    $row->onde,
                    $row->tipo_despesa,
                    $row->registro,
                    $row->detalhes,
                    $row->ip,
                ], ';');
            }

            fclose($handle);
        }, $export['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
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
