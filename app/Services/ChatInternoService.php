<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatInternoService
{
    private const TIMEOUT_ONLINE_SEGUNDOS = 75;

    public function online(int $usuarioId, ?string $sessionId = null, ?string $sessionToken = null): void
    {
        DB::table('chat_usuarios_online')->updateOrInsert(
            ['usuario_id' => $usuarioId],
            [
                'sessao_id' => $sessionId,
                'sessao_token' => $sessionToken ?: null,
                'atualizado_em' => now(),
            ]
        );
    }

    public function offline(int $usuarioId): void
    {
        DB::table('chat_usuarios_online')->where('usuario_id', $usuarioId)->delete();
    }

    public function contatos(int $usuarioId, int $propriedadeId): array
    {
        $this->online($usuarioId);

        $rows = DB::table('usuarios as u')
            ->leftJoin('usuario_propriedades as up', function ($join) use ($propriedadeId) {
                $join->on('up.usuario_id', '=', 'u.id')
                    ->where('up.propriedade_id', '=', $propriedadeId);
            })
            ->leftJoin('usuario_grupos_fazendas as ugf', 'ugf.usuario_id', '=', 'u.id')
            ->leftJoin('grupos_fazendas as gf', function ($join) {
                $join->on('gf.id', '=', 'ugf.grupo_id')
                    ->where('gf.ativo', '=', 1);
            })
            ->leftJoin('grupo_fazenda_propriedades as gfp', function ($join) use ($propriedadeId) {
                $join->on('gfp.grupo_id', '=', 'gf.id')
                    ->where('gfp.propriedade_id', '=', $propriedadeId);
            })
            ->leftJoin('chat_usuarios_online as online', 'online.usuario_id', '=', 'u.id')
            ->where('u.ativo', 1)
            ->where('u.id', '<>', $usuarioId)
            ->whereNotIn('u.perfil', $this->perfisSistema())
            ->where(function ($query) {
                $query->whereNotNull('up.usuario_id')
                    ->orWhereNotNull('gfp.propriedade_id');
            })
            ->groupBy('u.id', 'u.nome', 'u.email', 'u.perfil', 'online.atualizado_em')
            ->orderBy('u.nome')
            ->get([
                'u.id',
                'u.nome',
                'u.email',
                'u.perfil',
                'online.atualizado_em as online_em',
            ]);

        $unread = DB::table('chat_mensagens')
            ->where('destinatario_usuario_id', $usuarioId)
            ->whereNull('lida_em')
            ->groupBy('remetente_usuario_id')
            ->pluck(DB::raw('COUNT(*)'), 'remetente_usuario_id')
            ->mapWithKeys(fn ($total, $id) => [(int)$id => (int)$total])
            ->all();

        $contatos = $rows->map(function ($row) use ($unread) {
            $onlineTs = $row->online_em ? strtotime((string)$row->online_em) : 0;

            return [
                'id' => (int)$row->id,
                'nome' => (string)$row->nome,
                'email' => (string)$row->email,
                'perfil' => (string)$row->perfil,
                'online' => $onlineTs > 0 && $onlineTs >= time() - self::TIMEOUT_ONLINE_SEGUNDOS,
                'ultima_atividade' => $onlineTs ? date('d/m H:i', $onlineTs) : null,
                'unread' => $unread[(int)$row->id] ?? 0,
            ];
        })->sortBy([
            ['online', 'desc'],
            ['nome', 'asc'],
        ])->values();

        return [
            'ok' => true,
            'peers' => $contatos->all(),
            'total_unread' => array_sum($unread),
        ];
    }

    public function mensagens(int $usuarioId, int $destinatarioId, int $propriedadeId): array
    {
        $this->exigirDestinatario($usuarioId, $destinatarioId, $propriedadeId);

        DB::table('chat_mensagens')
            ->where('remetente_usuario_id', $destinatarioId)
            ->where('destinatario_usuario_id', $usuarioId)
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);

        return [
            'ok' => true,
            'messages' => $this->mensagensConversa($usuarioId, $destinatarioId)->all(),
        ];
    }

    public function enviar(int $usuarioId, int $destinatarioId, int $propriedadeId, ?string $mensagem, array $anexos = []): array
    {
        $this->exigirDestinatario($usuarioId, $destinatarioId, $propriedadeId);
        $mensagem = $this->mensagemValida($mensagem, $anexos);

        DB::transaction(function () use ($usuarioId, $destinatarioId, $mensagem, $anexos) {
            DB::table('chat_mensagens')->insert([
                'remetente_usuario_id' => $usuarioId,
                'destinatario_usuario_id' => $destinatarioId,
                'mensagem' => $mensagem,
            ]);
            $mensagemId = (int)DB::getPdo()->lastInsertId();

            $this->salvarAnexos($mensagemId, $usuarioId, $destinatarioId, $anexos);
        });

        return $this->mensagens($usuarioId, $destinatarioId, $propriedadeId);
    }

    public function baixarAnexo(int $anexoId, int $usuarioId): BinaryFileResponse
    {
        $anexo = DB::table('chat_anexos')->where('id', $anexoId)->first();
        abort_unless($anexo, 404);
        abort_unless(in_array($usuarioId, [(int)$anexo->remetente_usuario_id, (int)$anexo->destinatario_usuario_id], true), 403);

        if ($anexo->baixado_em || !$anexo->caminho_relativo || strtotime((string)$anexo->expira_em) < time()) {
            $this->expirarAnexo($anexoId, (string)$anexo->caminho_relativo);
            abort(410, 'Anexo temporario indisponivel.');
        }

        $path = realpath(base_path('../'.$anexo->caminho_relativo));
        $base = realpath(base_path('../uploads/chat_anexos'));
        abort_unless($path && $base && str_starts_with($path, $base) && is_file($path), 404);

        app()->terminating(function () use ($path, $anexoId, $usuarioId) {
            @unlink($path);
            DB::table('chat_anexos')
                ->where('id', $anexoId)
                ->update([
                    'baixado_por' => $usuarioId,
                    'baixado_em' => now(),
                    'caminho_relativo' => null,
                    'nome_arquivo' => null,
                ]);
        });

        return response()->download($path, preg_replace('/[\r\n"]+/', '', (string)$anexo->nome_original), [
            'Content-Type' => (string)($anexo->mime ?: 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function mensagensConversa(int $usuarioId, int $destinatarioId): Collection
    {
        $rows = DB::table('chat_mensagens as cm')
            ->join('usuarios as u', 'u.id', '=', 'cm.remetente_usuario_id')
            ->where(function ($query) use ($usuarioId, $destinatarioId) {
                $query->where('cm.remetente_usuario_id', $usuarioId)
                    ->where('cm.destinatario_usuario_id', $destinatarioId);
            })
            ->orWhere(function ($query) use ($usuarioId, $destinatarioId) {
                $query->where('cm.remetente_usuario_id', $destinatarioId)
                    ->where('cm.destinatario_usuario_id', $usuarioId);
            })
            ->orderBy('cm.id')
            ->limit(120)
            ->get([
                'cm.id',
                'cm.remetente_usuario_id',
                'cm.destinatario_usuario_id',
                'cm.mensagem',
                'cm.lida_em',
                'cm.criada_em',
                'u.nome as autor_nome',
            ]);

        $anexos = $this->anexosPorMensagens($rows->pluck('id')->all());

        return $rows->map(fn ($row) => [
            'id' => (int)$row->id,
            'mine' => (int)$row->remetente_usuario_id === $usuarioId,
            'autor_nome' => (string)$row->autor_nome,
            'mensagem' => (string)$row->mensagem,
            'lida' => !empty($row->lida_em),
            'hora' => date('d/m H:i', strtotime((string)$row->criada_em)),
            'anexos' => $anexos[(int)$row->id] ?? [],
        ]);
    }

    private function exigirDestinatario(int $usuarioId, int $destinatarioId, int $propriedadeId): void
    {
        abort_if($destinatarioId <= 0 || $destinatarioId === $usuarioId, 422, 'Destinatario invalido.');

        $destinatario = DB::table('usuarios')
            ->where('id', $destinatarioId)
            ->where('ativo', 1)
            ->whereNotIn('perfil', $this->perfisSistema())
            ->first(['id']);
        abort_unless($destinatario, 404, 'Usuario indisponivel.');

        $compartilhaPropriedade = DB::table('usuario_propriedades')
            ->where('usuario_id', $destinatarioId)
            ->where('propriedade_id', $propriedadeId)
            ->exists();

        $compartilhaGrupo = DB::table('usuario_grupos_fazendas as ugf')
            ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'ugf.grupo_id')
            ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
            ->where('ugf.usuario_id', $destinatarioId)
            ->where('gfp.propriedade_id', $propriedadeId)
            ->where('gf.ativo', 1)
            ->exists();

        abort_unless($compartilhaPropriedade || $compartilhaGrupo, 403, 'Voce so pode conversar com usuarios da mesma fazenda ou grupo.');
    }

    private function mensagemValida(?string $mensagem, array $anexos): string
    {
        $mensagem = trim((string)$mensagem);
        if ($mensagem === '' && $anexos !== []) {
            return 'Enviou anexo temporario.';
        }

        abort_if($mensagem === '', 422, 'Digite uma mensagem ou anexe um arquivo.');

        return $this->cortar($mensagem, 4000);
    }

    private function salvarAnexos(int $mensagemId, int $usuarioId, int $destinatarioId, array $anexos): void
    {
        foreach ($anexos as $anexo) {
            if (!$anexo instanceof UploadedFile) {
                continue;
            }

            abort_unless($anexo->isValid(), 422, 'Nao foi possivel receber o anexo.');
            abort_if($anexo->getSize() <= 0 || $anexo->getSize() > 25 * 1024 * 1024, 422, 'Anexo invalido ou maior que 25 MB.');

            $ext = strtolower($anexo->getClientOriginalExtension());
            abort_unless(in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'pdf', 'xls', 'xlsx', 'csv', 'xml'], true), 422, 'Envie apenas print/imagem, PDF, Excel ou XML.');

            File::ensureDirectoryExists(base_path('../uploads/chat_anexos'));
            $nomeArquivo = 'chat_'.$mensagemId.'_'.bin2hex(random_bytes(10)).'.'.$ext;
            $anexo->move(base_path('../uploads/chat_anexos'), $nomeArquivo);

            DB::table('chat_anexos')->insert([
                'mensagem_id' => $mensagemId,
                'remetente_usuario_id' => $usuarioId,
                'destinatario_usuario_id' => $destinatarioId,
                'nome_original' => $this->cortar($anexo->getClientOriginalName(), 255),
                'nome_arquivo' => $nomeArquivo,
                'caminho_relativo' => 'uploads/chat_anexos/'.$nomeArquivo,
                'mime' => $this->cortar($anexo->getClientMimeType() ?: 'application/octet-stream', 120),
                'tamanho_bytes' => (int)$anexo->getSize(),
                'expira_em' => now()->addDay(),
            ]);
        }
    }

    private function anexosPorMensagens(array $mensagemIds): array
    {
        $mensagemIds = array_values(array_filter(array_map('intval', $mensagemIds)));
        if (!$mensagemIds) {
            return [];
        }

        return DB::table('chat_anexos')
            ->whereIn('mensagem_id', $mensagemIds)
            ->orderBy('id')
            ->get(['id', 'mensagem_id', 'nome_original', 'tamanho_bytes', 'baixado_em', 'caminho_relativo', 'expira_em'])
            ->groupBy('mensagem_id')
            ->map(fn ($rows) => $rows->map(function ($row) {
                $expiraEm = strtotime((string)$row->expira_em);

                return [
                    'id' => (int)$row->id,
                    'nome' => (string)$row->nome_original,
                    'tamanho' => (int)$row->tamanho_bytes,
                    'disponivel' => !$row->baixado_em && $row->caminho_relativo && $expiraEm >= time(),
                    'baixado_em' => $row->baixado_em ? (string)$row->baixado_em : null,
                    'download_url' => route('chat-interno.anexo', $row->id),
                ];
            })->all())
            ->all();
    }

    private function expirarAnexo(int $anexoId, string $caminho): void
    {
        $path = $caminho !== '' ? realpath(base_path('../'.$caminho)) : false;
        $base = realpath(base_path('../uploads/chat_anexos'));
        if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
            @unlink($path);
        }

        DB::table('chat_anexos')
            ->where('id', $anexoId)
            ->update(['caminho_relativo' => null, 'nome_arquivo' => null]);
    }

    private function perfisSistema(): array
    {
        return ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'];
    }

    private function cortar(string $texto, int $limite): string
    {
        return substr($texto, 0, $limite);
    }
}
