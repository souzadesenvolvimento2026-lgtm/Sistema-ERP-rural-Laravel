<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\ChatInternoService;
use App\Services\SuporteChatService;
use App\Support\FarmContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LegacyAjaxController extends Controller
{
    public function __construct(private readonly ProfileAccess $access) {}

    public function chatInterno(Request $request, ChatInternoService $service): JsonResponse
    {
        $usuarioId = $this->usuarioId();
        $propriedadeId = app(FarmContext::class)->propertyId();
        $action = (string) $request->input('action', $request->query('action', ''));

        return match ($action) {
            'heartbeat' => $this->jsonTap(fn () => $service->online($usuarioId, $request->session()->getId(), (string) session('sessao_token', ''))),
            'offline' => $this->jsonTap(fn () => $service->offline($usuarioId)),
            'peers' => response()->json($service->contatos($usuarioId, $propriedadeId)),
            'messages' => response()->json($service->mensagens($usuarioId, (int) $request->query('usuario_id'), $propriedadeId)),
            'send' => response()->json($service->enviar(
                $usuarioId,
                (int) $request->input('destinatario_id', $request->input('usuario_id', 0)),
                $propriedadeId,
                $request->input('mensagem'),
                $request->file('anexos', [])
            )),
            default => response()->json(['ok' => false, 'erro' => 'Acao invalida.'], 400),
        };
    }

    public function chatAnexo(Request $request, ChatInternoService $service): BinaryFileResponse
    {
        return $service->baixarAnexo((int) $request->query('id'), $this->usuarioId());
    }

    public function suporteChat(Request $request, SuporteChatService $service): JsonResponse
    {
        $usuarioId = $this->usuarioId();
        $propriedadeId = app(FarmContext::class)->propertyId();
        $action = (string) $request->input('action', $request->query('action', ''));

        return match ($action) {
            'client_boot', 'client_summary' => response()->json($service->conversaCliente($usuarioId, $propriedadeId)),
            'client_messages' => $this->suporteClientMessages($request, $service),
            'client_send' => response()->json($service->enviarCliente($usuarioId, $propriedadeId, $request->input('mensagem'), $request->file('anexos', []))),
            'client_keep_open' => $this->clienteKeepOpen($request, $service),
            'client_close' => $this->clienteClose($request, $service),
            'admin_summary' => $this->suporteAdminSummary(),
            'admin_threads' => $this->suporteAdminThreads(),
            'admin_online_assignees' => $this->suporteAdminOnlineAssignees(),
            'admin_assign', 'admin_assume', 'admin_forward' => $this->suporteAdminAssign($request, $service),
            'admin_messages' => $this->suporteAdminMessages($request, $service),
            'admin_send' => $this->suporteAdminSend($request, $service),
            'admin_request_close' => $this->suporteAdminRequestClose($request, $service),
            'admin_close' => $this->suporteAdminClose($request, $service),
            default => response()->json(['ok' => false, 'erro' => 'Acao invalida.'], 400),
        };
    }

    public function suporteAnexo(Request $request, SuporteChatService $service): BinaryFileResponse
    {
        return $service->baixarAnexo((int) $request->query('id'), $this->usuarioId(), $this->podeAtenderSuporte());
    }

    private function clienteKeepOpen(Request $request, SuporteChatService $service): JsonResponse
    {
        $conversaId = (int) $request->input('conversa_id');
        DB::table('suporte_conversas')
            ->where('id', $conversaId)
            ->where('usuario_id', $this->usuarioId())
            ->update([
                'status' => 'aberta',
                'encerramento_solicitado_em' => null,
                'encerramento_solicitado_por' => null,
                'atualizada_em' => now(),
            ]);

        return response()->json($service->payload($conversaId));
    }

    private function clienteClose(Request $request, SuporteChatService $service): JsonResponse
    {
        $conversaId = (int) $request->input('conversa_id');
        DB::table('suporte_conversas')
            ->where('id', $conversaId)
            ->where('usuario_id', $this->usuarioId())
            ->update(['status' => 'encerrada', 'encerrada_em' => now(), 'atualizada_em' => now()]);

        return response()->json($service->payload($conversaId));
    }

    private function suporteClientMessages(Request $request, SuporteChatService $service): JsonResponse
    {
        $conversaId = (int) $request->query('conversa_id');
        DB::table('suporte_mensagens')
            ->where('conversa_id', $conversaId)
            ->where('autor_tipo', 'admin')
            ->where('lida_cliente', 0)
            ->update(['lida_cliente' => 1]);

        return response()->json($service->payload($conversaId));
    }

    private function suporteAdminSummary(): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $unread = (int) DB::table('suporte_mensagens as sm')
            ->join('suporte_conversas as c', 'c.id', '=', 'sm.conversa_id')
            ->where('sm.autor_tipo', 'cliente')
            ->where('sm.lida_admin', 0)
            ->where('c.status', '!=', 'encerrada')
            ->count();

        $pending = (int) DB::table('suporte_conversas')
            ->whereIn('status', ['aberta', 'respondida', 'aguardando_encerramento'])
            ->count();

        $lastId = (int) DB::table('suporte_mensagens as sm')
            ->join('suporte_conversas as c', 'c.id', '=', 'sm.conversa_id')
            ->where('sm.autor_tipo', 'cliente')
            ->where('c.status', '!=', 'encerrada')
            ->max('sm.id');

        return response()->json(['ok' => true, 'unread' => $unread, 'pending' => $pending, 'last_id' => $lastId]);
    }

    private function suporteAdminThreads(): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $threads = DB::table('suporte_conversas as c')
            ->leftJoin('usuarios as u', 'u.id', '=', 'c.usuario_id')
            ->leftJoin('usuarios as atendente', 'atendente.id', '=', 'c.atendente_usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'c.propriedade_id')
            ->leftJoin('suporte_mensagens as ultima', function ($join) {
                $join->on('ultima.conversa_id', '=', 'c.id')
                    ->whereRaw('ultima.id = (SELECT MAX(sm2.id) FROM suporte_mensagens sm2 WHERE sm2.conversa_id = c.id)');
            })
            ->orderByDesc('c.atualizada_em')
            ->limit(80)
            ->get([
                'c.id',
                'c.assunto',
                'c.status',
                'c.nivel_atendimento',
                'c.atendente_usuario_id',
                'c.atualizada_em',
                'u.nome as usuario_nome',
                'u.nome as cliente_nome',
                'atendente.nome as atendente_nome',
                'p.nome as propriedade_nome',
                'ultima.mensagem as ultima_mensagem',
                DB::raw('(SELECT COUNT(*) FROM suporte_mensagens sm WHERE sm.conversa_id = c.id AND sm.autor_tipo = "cliente" AND sm.lida_admin = 0) as nao_lidas'),
                DB::raw('CASE WHEN c.atendente_usuario_id = '.(int) $this->usuarioId().' THEN 1 ELSE 0 END as assumido_por_mim'),
            ]);

        return response()->json(['ok' => true, 'threads' => $threads]);
    }

    private function suporteAdminOnlineAssignees(): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $perfilAtual = (string) session('perfil');
        $perfis = match ($perfilAtual) {
            'administrador_sistema' => ['gerencia_sistema', 'colaborador_sistema'],
            'gerencia_sistema' => ['colaborador_sistema'],
            default => [],
        };

        $users = $perfis
            ? DB::table('usuarios as u')
                ->join('chat_usuarios_online as online', 'online.usuario_id', '=', 'u.id')
                ->where('u.ativo', 1)
                ->whereIn('u.perfil', $perfis)
                ->where('online.atualizado_em', '>=', now()->subSeconds(90))
                ->orderBy('u.nome')
                ->get(['u.id', 'u.nome', 'u.perfil'])
                ->map(fn ($user) => [
                    'id' => (int) $user->id,
                    'nome' => (string) $user->nome,
                    'perfil' => (string) $user->perfil,
                    'nivel_label' => match ((string) $user->perfil) {
                        'gerencia_sistema' => 'gerência',
                        'colaborador_sistema' => 'colaborador',
                        default => 'suporte',
                    },
                ])
                ->values()
                ->all()
            : [];

        return response()->json(['ok' => true, 'users' => $users]);
    }

    private function suporteAdminAssign(Request $request, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $conversaId = (int) $request->input('conversa_id');
        $destino = (string) $request->input('destino', '');
        $atendenteId = (int) $request->input('atendente_usuario_id', $request->input('usuario_id', $this->usuarioId()));
        $nivel = (string) $request->input('nivel_atendimento', $request->input('nivel', $destino ?: 'colaborador'));
        $nivel = in_array($nivel, ['colaborador', 'gerencia', 'admin'], true) ? $nivel : 'colaborador';
        if ($destino !== '') {
            $atendenteId = null;
        }

        DB::table('suporte_conversas')
            ->where('id', $conversaId)
            ->update([
                'atendente_usuario_id' => $atendenteId ?: $this->usuarioId(),
                'atendimento_assumido_em' => now(),
                'nivel_atendimento' => $nivel,
                'atualizada_em' => now(),
            ]);

        return $this->suportePayload($service, $conversaId);
    }

    private function suporteAdminMessages(Request $request, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $conversaId = (int) $request->query('conversa_id');
        DB::table('suporte_mensagens')
            ->where('conversa_id', $conversaId)
            ->where('autor_tipo', 'cliente')
            ->where('lida_admin', 0)
            ->update(['lida_admin' => 1]);

        return $this->suportePayload($service, $conversaId);
    }

    private function suporteAdminSend(Request $request, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $conversaId = (int) $request->input('conversa_id');
        $service->responderAdmin($conversaId, $this->usuarioId(), $request->input('mensagem'), $request->file('anexos', []));

        return $this->suportePayload($service, $conversaId);
    }

    private function suporteAdminRequestClose(Request $request, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $conversaId = (int) $request->input('conversa_id');
        DB::table('suporte_conversas')
            ->where('id', $conversaId)
            ->update([
                'status' => 'aguardando_encerramento',
                'encerramento_solicitado_em' => now(),
                'encerramento_solicitado_por' => 'admin',
                'atualizada_em' => now(),
            ]);

        return $this->suportePayload($service, $conversaId);
    }

    private function suporteAdminClose(Request $request, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $conversaId = (int) $request->input('conversa_id');
        DB::table('suporte_conversas')
            ->where('id', $conversaId)
            ->update([
                'status' => 'encerrada',
                'encerrada_em' => now(),
                'encerramento_solicitado_em' => null,
                'encerramento_solicitado_por' => null,
                'atualizada_em' => now(),
            ]);

        return $this->suportePayload($service, $conversaId);
    }

    private function suportePayload(SuporteChatService $service, int $conversaId): JsonResponse
    {
        $payload = $service->payload($conversaId);
        $conversa = DB::table('suporte_conversas as c')
            ->leftJoin('usuarios as u', 'u.id', '=', 'c.usuario_id')
            ->leftJoin('usuarios as atendente', 'atendente.id', '=', 'c.atendente_usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'c.propriedade_id')
            ->where('c.id', $conversaId)
            ->first([
                'c.propriedade_id',
                'c.atendente_usuario_id',
                'c.nivel_atendimento',
                'c.status',
                'u.nome as usuario_nome',
                'atendente.nome as atendente_nome',
                'p.nome as propriedade_nome',
            ]);

        if ($conversa) {
            $payload['propriedade_id'] = $conversa->propriedade_id ? (int) $conversa->propriedade_id : null;
            $payload['propriedade_nome'] = $conversa->propriedade_nome ?: null;
            $payload['usuario_nome'] = $conversa->usuario_nome ?: null;
            $payload['atendente_usuario_id'] = $conversa->atendente_usuario_id ? (int) $conversa->atendente_usuario_id : null;
            $payload['atendente_nome'] = $conversa->atendente_nome ?: null;
            $payload['assumido_por_mim'] = $payload['atendente_usuario_id'] === $this->usuarioId();
            $payload['nivel_atendimento'] = (string) $conversa->nivel_atendimento;
            $payload['status'] = (string) $conversa->status;
        }

        return response()->json($payload);
    }

    private function jsonTap(callable $callback): JsonResponse
    {
        $callback();

        return response()->json(['ok' => true]);
    }

    private function usuarioId(): int
    {
        return (int) session('usuario_id');
    }

    private function podeAtenderSuporte(): bool
    {
        return $this->access->canHandleSupport((string) session('perfil'));
    }
}
