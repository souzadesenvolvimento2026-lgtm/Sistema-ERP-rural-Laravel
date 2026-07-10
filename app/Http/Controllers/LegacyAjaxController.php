<?php

namespace App\Http\Controllers;

use App\Services\ChatInternoService;
use App\Services\SuporteChatService;
use App\Support\FarmContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LegacyAjaxController extends Controller
{
    public function chatInterno(Request $request, ChatInternoService $service): JsonResponse
    {
        $usuarioId = $this->usuarioId();
        $propriedadeId = app(FarmContext::class)->propertyId();
        $action = (string)$request->input('action', $request->query('action', ''));

        return match ($action) {
            'heartbeat' => $this->jsonTap(fn () => $service->online($usuarioId, $request->session()->getId(), (string)session('sessao_token', ''))),
            'offline' => $this->jsonTap(fn () => $service->offline($usuarioId)),
            'peers' => response()->json($service->contatos($usuarioId, $propriedadeId)),
            'messages' => response()->json($service->mensagens($usuarioId, (int)$request->query('usuario_id'), $propriedadeId)),
            'send' => response()->json($service->enviar(
                $usuarioId,
                (int)$request->input('destinatario_id', $request->input('usuario_id', 0)),
                $propriedadeId,
                $request->input('mensagem'),
                $request->file('anexos', [])
            )),
            default => response()->json(['ok' => false, 'erro' => 'Acao invalida.'], 400),
        };
    }

    public function chatAnexo(Request $request, ChatInternoService $service): BinaryFileResponse
    {
        return $service->baixarAnexo((int)$request->query('id'), $this->usuarioId());
    }

    public function suporteChat(Request $request, SuporteChatService $service): JsonResponse
    {
        $usuarioId = $this->usuarioId();
        $propriedadeId = app(FarmContext::class)->propertyId();
        $action = (string)$request->input('action', $request->query('action', ''));

        return match ($action) {
            'client_boot', 'client_summary' => response()->json($service->conversaCliente($usuarioId, $propriedadeId)),
            'client_messages' => response()->json($service->payload((int)$request->query('conversa_id'))),
            'client_send' => response()->json($service->enviarCliente($usuarioId, $propriedadeId, $request->input('mensagem'), $request->file('anexos', []))),
            'client_keep_open' => $this->clienteKeepOpen($request),
            'client_close' => $this->clienteClose($request),
            'admin_summary' => $this->suporteAdminSummary(),
            'admin_threads' => $this->suporteAdminThreads(),
            'admin_online_assignees' => response()->json(['ok' => true, 'atendentes' => []]),
            'admin_assign', 'admin_assume', 'admin_forward' => $this->suporteAdminAssign($request),
            'admin_messages' => response()->json($service->payload((int)$request->query('conversa_id'))),
            'admin_send' => response()->json($service->responderAdmin((int)$request->input('conversa_id'), $usuarioId, $request->input('mensagem'), $request->file('anexos', []))),
            'admin_request_close' => $this->suporteAdminRequestClose($request),
            'admin_close' => $this->suporteAdminClose($request),
            default => response()->json(['ok' => false, 'erro' => 'Acao invalida.'], 400),
        };
    }

    public function suporteAnexo(Request $request, SuporteChatService $service): BinaryFileResponse
    {
        return $service->baixarAnexo((int)$request->query('id'), $this->usuarioId(), $this->podeAtenderSuporte());
    }

    private function clienteKeepOpen(Request $request): JsonResponse
    {
        DB::table('suporte_conversas')
            ->where('id', (int)$request->input('conversa_id'))
            ->where('usuario_id', $this->usuarioId())
            ->update([
                'status' => 'aberta',
                'encerramento_solicitado_em' => null,
                'encerramento_solicitado_por' => null,
                'atualizada_em' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function clienteClose(Request $request): JsonResponse
    {
        DB::table('suporte_conversas')
            ->where('id', (int)$request->input('conversa_id'))
            ->where('usuario_id', $this->usuarioId())
            ->update(['status' => 'encerrada', 'encerrada_em' => now(), 'atualizada_em' => now()]);

        return response()->json(['ok' => true]);
    }

    private function suporteAdminSummary(): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $totais = DB::table('suporte_conversas')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int)$total)
            ->all();

        return response()->json(['ok' => true, 'summary' => $totais]);
    }

    private function suporteAdminThreads(): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $threads = DB::table('suporte_conversas as c')
            ->leftJoin('usuarios as u', 'u.id', '=', 'c.usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'c.propriedade_id')
            ->orderByDesc('c.atualizada_em')
            ->limit(80)
            ->get([
                'c.id',
                'c.assunto',
                'c.status',
                'c.nivel_atendimento',
                'c.atendente_usuario_id',
                'c.atualizada_em',
                'u.nome as cliente_nome',
                'p.nome as propriedade_nome',
            ]);

        return response()->json(['ok' => true, 'threads' => $threads]);
    }

    private function suporteAdminAssign(Request $request): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $conversaId = (int)$request->input('conversa_id');
        $atendenteId = (int)$request->input('atendente_usuario_id', $this->usuarioId());
        $nivel = (string)$request->input('nivel_atendimento', $request->input('nivel', 'colaborador'));
        $nivel = in_array($nivel, ['colaborador', 'gerencia', 'admin'], true) ? $nivel : 'colaborador';

        DB::table('suporte_conversas')
            ->where('id', $conversaId)
            ->update([
                'atendente_usuario_id' => $atendenteId ?: $this->usuarioId(),
                'atendimento_assumido_em' => now(),
                'nivel_atendimento' => $nivel,
                'atualizada_em' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function suporteAdminRequestClose(Request $request): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        DB::table('suporte_conversas')
            ->where('id', (int)$request->input('conversa_id'))
            ->update([
                'status' => 'aguardando_encerramento',
                'encerramento_solicitado_em' => now(),
                'encerramento_solicitado_por' => 'admin',
                'atualizada_em' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function suporteAdminClose(Request $request): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        DB::table('suporte_conversas')
            ->where('id', (int)$request->input('conversa_id'))
            ->update([
                'status' => 'encerrada',
                'encerrada_em' => now(),
                'encerramento_solicitado_em' => null,
                'encerramento_solicitado_por' => null,
                'atualizada_em' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function jsonTap(callable $callback): JsonResponse
    {
        $callback();

        return response()->json(['ok' => true]);
    }

    private function usuarioId(): int
    {
        return (int)session('usuario_id');
    }

    private function podeAtenderSuporte(): bool
    {
        return in_array((string)session('perfil'), ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'], true);
    }
}
