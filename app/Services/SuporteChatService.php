<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuporteChatService
{
    public function conversaCliente(int $usuarioId, ?int $propriedadeId): array
    {
        $conversaId = $this->conversaAbertaCliente($usuarioId, $propriedadeId);

        return $this->payload($conversaId);
    }

    public function enviarCliente(int $usuarioId, ?int $propriedadeId, ?string $mensagem, array $anexos = []): array
    {
        $mensagem = $this->mensagemValida($mensagem, $anexos);

        $conversaId = $this->conversaAbertaCliente($usuarioId, $propriedadeId, true, $mensagem);

        DB::transaction(function () use ($conversaId, $usuarioId, $mensagem, $anexos) {
            DB::table('suporte_mensagens')->insert([
                'conversa_id' => $conversaId,
                'autor_usuario_id' => $usuarioId,
                'autor_tipo' => 'cliente',
                'mensagem' => $mensagem,
                'lida_admin' => 0,
                'lida_cliente' => 1,
            ]);
            $mensagemId = (int)DB::getPdo()->lastInsertId();

            $this->salvarAnexos($conversaId, $mensagemId, $usuarioId, $anexos);

            DB::table('suporte_conversas')
                ->where('id', $conversaId)
                ->update([
                    'status' => 'aberta',
                    'atualizada_em' => now(),
                    'encerramento_solicitado_em' => null,
                    'encerramento_solicitado_por' => null,
                ]);
        });

        return $this->payload($conversaId);
    }

    public function responderAdmin(int $conversaId, int $usuarioId, ?string $mensagem, array $anexos = []): array
    {
        $mensagem = $this->mensagemValida($mensagem, $anexos);
        $this->conversa($conversaId);

        DB::transaction(function () use ($conversaId, $usuarioId, $mensagem, $anexos) {
            DB::table('suporte_mensagens')->insert([
                'conversa_id' => $conversaId,
                'autor_usuario_id' => $usuarioId,
                'autor_tipo' => 'admin',
                'mensagem' => $mensagem,
                'lida_admin' => 1,
                'lida_cliente' => 0,
            ]);
            $mensagemId = (int)DB::getPdo()->lastInsertId();

            $this->salvarAnexos($conversaId, $mensagemId, $usuarioId, $anexos);

            DB::table('suporte_conversas')
                ->where('id', $conversaId)
                ->update([
                    'atendente_usuario_id' => $usuarioId,
                    'atendimento_assumido_em' => now(),
                    'status' => 'respondida',
                    'atualizada_em' => now(),
                ]);
        });

        return $this->payload($conversaId);
    }

    public function baixarAnexo(int $anexoId, int $usuarioId, bool $podeAtender): BinaryFileResponse
    {
        $anexo = DB::table('suporte_anexos as sa')
            ->join('suporte_conversas as sc', 'sc.id', '=', 'sa.conversa_id')
            ->join('suporte_mensagens as sm', 'sm.id', '=', 'sa.mensagem_id')
            ->where('sa.id', $anexoId)
            ->first([
                'sa.id',
                'sa.nome_original',
                'sa.caminho_relativo',
                'sa.mime',
                'sa.baixado_em',
                'sa.expira_em',
                'sc.usuario_id as conversa_usuario_id',
                'sm.autor_tipo',
            ]);

        abort_unless($anexo, 404);
        abort_unless($podeAtender || (int)$anexo->conversa_usuario_id === $usuarioId, 403);

        if ($anexo->baixado_em || !$anexo->caminho_relativo || ($anexo->expira_em && strtotime((string)$anexo->expira_em) < time())) {
            $this->expirarAnexo($anexoId, (string)$anexo->caminho_relativo);
            abort(410, 'Anexo temporario indisponivel.');
        }

        $path = realpath(base_path('../'.$anexo->caminho_relativo));
        $base = realpath(base_path('../uploads/suporte_anexos'));
        abort_unless($path && $base && str_starts_with($path, $base) && is_file($path), 404);

        $downloadDoDestinatario = ((string)$anexo->autor_tipo === 'cliente' && $podeAtender)
            || ((string)$anexo->autor_tipo === 'admin' && !$podeAtender && (int)$anexo->conversa_usuario_id === $usuarioId);

        if ($downloadDoDestinatario) {
            app()->terminating(function () use ($path, $anexoId, $usuarioId) {
                @unlink($path);
                DB::table('suporte_anexos')
                    ->where('id', $anexoId)
                    ->update([
                        'baixado_por' => $usuarioId,
                        'baixado_em' => now(),
                        'caminho_relativo' => null,
                        'nome_arquivo' => null,
                    ]);
            });
        }

        return response()->download($path, preg_replace('/[\r\n"]+/', '', (string)$anexo->nome_original), [
            'Content-Type' => (string)($anexo->mime ?: 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function payload(?int $conversaId): array
    {
        if (!$conversaId) {
            return [
                'ok' => true,
                'conversa' => null,
                'messages' => [],
            ];
        }

        $conversa = $this->conversa($conversaId);

        return [
            'ok' => true,
            'conversa' => [
                'id' => (int)$conversa->id,
                'assunto' => (string)$conversa->assunto,
                'status' => (string)$conversa->status,
                'nivel_atendimento' => (string)$conversa->nivel_atendimento,
                'atendente_usuario_id' => $conversa->atendente_usuario_id ? (int)$conversa->atendente_usuario_id : null,
            ],
            'messages' => $this->mensagens($conversaId)->all(),
        ];
    }

    private function conversaAbertaCliente(int $usuarioId, ?int $propriedadeId, bool $criar = false, string $primeiraMensagem = ''): ?int
    {
        $query = DB::table('suporte_conversas')
            ->where('usuario_id', $usuarioId)
            ->whereIn('status', ['aberta', 'respondida', 'aguardando_encerramento'])
            ->orderByDesc('atualizada_em');

        if ($propriedadeId) {
            $query->where('propriedade_id', $propriedadeId);
        }

        $id = $query->value('id');
        if ($id || !$criar) {
            return $id ? (int)$id : null;
        }

        DB::table('suporte_conversas')->insert([
            'propriedade_id' => $propriedadeId,
            'usuario_id' => $usuarioId,
            'assunto' => $this->assunto($primeiraMensagem),
            'status' => 'aberta',
            'origem' => 'manual',
            'ia_status' => 'nao_aplicado',
            'nivel_atendimento' => 'colaborador',
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    private function conversa(int $conversaId): object
    {
        $conversa = DB::table('suporte_conversas')->where('id', $conversaId)->first();
        abort_unless($conversa, 404);

        return $conversa;
    }

    private function mensagens(int $conversaId): Collection
    {
        $mensagens = DB::table('suporte_mensagens')
            ->where('conversa_id', $conversaId)
            ->orderBy('id')
            ->get(['id', 'autor_usuario_id', 'autor_tipo', 'mensagem', 'criada_em'])
            ->map(fn ($row) => [
                'id' => (int)$row->id,
                'autor_usuario_id' => $row->autor_usuario_id ? (int)$row->autor_usuario_id : null,
                'autor_tipo' => (string)$row->autor_tipo,
                'mensagem' => (string)$row->mensagem,
                'criada_em' => (string)$row->criada_em,
            ]);

        $anexos = $this->anexosPorMensagens($mensagens->pluck('id')->all());

        return $mensagens->map(function (array $mensagem) use ($anexos) {
            $mensagem['anexos'] = $anexos[$mensagem['id']] ?? [];

            return $mensagem;
        });
    }

    private function mensagemValida(?string $mensagem, array $anexos = []): string
    {
        $mensagem = trim((string)$mensagem);
        if ($mensagem === '' && $anexos !== []) {
            return 'Enviou anexo temporario.';
        }

        abort_if($mensagem === '', 422, 'Digite uma mensagem.');

        return $this->cortar($mensagem, 4000);
    }

    private function salvarAnexos(int $conversaId, int $mensagemId, int $usuarioId, array $anexos): void
    {
        foreach ($anexos as $anexo) {
            if (!$anexo instanceof UploadedFile) {
                continue;
            }

            abort_unless($anexo->isValid(), 422, 'Nao foi possivel receber o anexo.');
            abort_if($anexo->getSize() <= 0 || $anexo->getSize() > 25 * 1024 * 1024, 422, 'Anexo invalido ou maior que 25 MB.');

            $ext = strtolower($anexo->getClientOriginalExtension());
            abort_unless(in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'pdf', 'xls', 'xlsx', 'csv', 'xml'], true), 422, 'Envie apenas print/imagem, PDF, Excel ou XML.');

            File::ensureDirectoryExists(base_path('../uploads/suporte_anexos'));
            $nomeArquivo = 'sup_'.$conversaId.'_'.$mensagemId.'_'.bin2hex(random_bytes(8)).'.'.$ext;
            $anexo->move(base_path('../uploads/suporte_anexos'), $nomeArquivo);

            DB::table('suporte_anexos')->insert([
                'mensagem_id' => $mensagemId,
                'conversa_id' => $conversaId,
                'usuario_id' => $usuarioId,
                'nome_original' => $this->cortar($anexo->getClientOriginalName(), 255),
                'nome_arquivo' => $nomeArquivo,
                'caminho_relativo' => 'uploads/suporte_anexos/'.$nomeArquivo,
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

        return DB::table('suporte_anexos')
            ->whereIn('mensagem_id', $mensagemIds)
            ->orderBy('id')
            ->get(['id', 'mensagem_id', 'nome_original', 'tamanho_bytes', 'baixado_em', 'caminho_relativo', 'expira_em'])
            ->groupBy('mensagem_id')
            ->map(fn ($rows) => $rows->map(function ($row) {
                $expiraEm = $row->expira_em ? strtotime((string)$row->expira_em) : null;

                return [
                    'id' => (int)$row->id,
                    'nome' => (string)$row->nome_original,
                    'tamanho' => (int)$row->tamanho_bytes,
                    'disponivel' => !$row->baixado_em && $row->caminho_relativo && ($expiraEm === null || $expiraEm >= time()),
                    'baixado_em' => $row->baixado_em ? (string)$row->baixado_em : null,
                    'download_url' => route('suporte.chat.anexo', $row->id),
                ];
            })->all())
            ->all();
    }

    private function expirarAnexo(int $anexoId, string $caminho): void
    {
        $path = $caminho !== '' ? realpath(base_path('../'.$caminho)) : false;
        $base = realpath(base_path('../uploads/suporte_anexos'));
        if ($path && $base && str_starts_with($path, $base) && is_file($path)) {
            @unlink($path);
        }

        DB::table('suporte_anexos')
            ->where('id', $anexoId)
            ->update(['caminho_relativo' => null, 'nome_arquivo' => null]);
    }

    private function assunto(string $mensagem): string
    {
        $assunto = trim(preg_replace('/\s+/', ' ', $mensagem) ?: '');
        if ($assunto === '') {
            return 'Duvida do cliente';
        }

        return $this->cortar($assunto, 120);
    }

    private function cortar(string $texto, int $limite): string
    {
        return substr($texto, 0, $limite);
    }
}
