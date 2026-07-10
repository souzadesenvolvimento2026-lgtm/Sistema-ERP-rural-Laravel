<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CertificadoDigitalService
{
    public function pagina(int $propriedadeId): array
    {
        $this->atualizarVencidos($propriedadeId);
        $certificados = DB::table('certificados_digitais as c')
            ->leftJoin('usuarios as u', 'u.id', '=', 'c.usuario_id')
            ->where('c.propriedade_id', $propriedadeId)
            ->orderByDesc('c.principal')
            ->orderByDesc('c.criado_em')
            ->get([
                'c.id',
                'c.tipo_certificado',
                'c.ambiente',
                'c.nome_identificacao',
                'c.titular',
                'c.cpf_cnpj',
                'c.numero_serie',
                'c.emissor',
                'c.validade_inicio',
                'c.validade_fim',
                'c.principal',
                'c.status',
                'c.observacoes',
                'c.criado_em',
                'u.nome as usuario_nome',
            ]);

        $principal = $certificados->firstWhere('principal', 1);

        return [
            'activeModule' => 'fiscal',
            'propriedade' => DB::table('propriedades')
                ->where('id', $propriedadeId)
                ->first(['id', 'nome', 'cnpj_cpf']),
            'certificados' => $certificados,
            'principal' => $principal,
            'totais' => [
                'certificados' => $certificados->count(),
                'ativos' => $certificados->where('status', 'ativo')->count(),
                'vencidos' => $certificados->where('status', 'vencido')->count(),
                'vencendo' => $certificados->filter(fn ($cert) => $this->diasValidade($cert->validade_fim) !== null && $this->diasValidade($cert->validade_fim) <= 30 && $cert->status !== 'inativo')->count(),
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId, ?UploadedFile $arquivo = null): int
    {
        $parsed = [];
        if (($dados['tipo_certificado'] ?? 'A1') === 'A1') {
            if (!$arquivo) {
                throw new RuntimeException('Envie o arquivo do certificado A1 (.pfx ou .p12).');
            }

            $parsed = $this->parsePfx($arquivo, (string)($dados['senha_certificado'] ?? ''));
        }

        $arquivoPath = $arquivo ? $arquivo->store('certificados', 'local') : null;
        $validadeFim = $parsed['validade_fim'] ?? (($dados['validade_fim'] ?? null) ?: null);

        return DB::transaction(function () use ($dados, $propriedadeId, $usuarioId, $parsed, $arquivoPath, $validadeFim) {
            if (!empty($dados['principal'])) {
                DB::table('certificados_digitais')->where('propriedade_id', $propriedadeId)->update(['principal' => 0]);
            }

            DB::table('certificados_digitais')->insert([
                'propriedade_id' => $propriedadeId,
                'tipo_certificado' => $dados['tipo_certificado'] ?? 'A1',
                'ambiente' => $dados['ambiente'] ?? 'homologacao',
                'nome_identificacao' => trim($dados['nome_identificacao']),
                'titular' => $parsed['titular'] ?? (trim($dados['titular'] ?? '') ?: null),
                'cpf_cnpj' => preg_replace('/\D+/', '', (string)($dados['cpf_cnpj'] ?? '')) ?: null,
                'numero_serie' => $parsed['numero_serie'] ?? (trim($dados['numero_serie'] ?? '') ?: null),
                'emissor' => $parsed['emissor'] ?? (trim($dados['emissor'] ?? '') ?: null),
                'validade_inicio' => $parsed['validade_inicio'] ?? (($dados['validade_inicio'] ?? null) ?: null),
                'validade_fim' => $validadeFim,
                'arquivo_path' => $arquivoPath,
                'senha_criptografada' => !empty($dados['senha_certificado']) ? Crypt::encryptString($dados['senha_certificado']) : null,
                'principal' => !empty($dados['principal']) ? 1 : 0,
                'status' => $this->statusValidade($validadeFim),
                'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
                'usuario_id' => $usuarioId,
            ]);

            $certificadoId = (int)DB::getPdo()->lastInsertId();
            $this->auditar($usuarioId, 'vincular_certificado_digital', 'certificados_digitais', $certificadoId, $propriedadeId, 'Certificado digital vinculado');

            return $certificadoId;
        });
    }

    public function definirPrincipal(int $id, int $propriedadeId): void
    {
        $certificado = DB::table('certificados_digitais')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->where('status', '!=', 'inativo')
            ->first(['id']);

        abort_unless($certificado, 404);

        DB::transaction(function () use ($id, $propriedadeId) {
            DB::table('certificados_digitais')->where('propriedade_id', $propriedadeId)->update(['principal' => 0]);

            DB::table('certificados_digitais')
                ->where('id', $id)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'principal' => 1,
                    'status' => DB::raw("IF(validade_fim IS NOT NULL AND DATE(validade_fim) < CURDATE(), 'vencido', 'ativo')"),
                ]);
        });
    }

    public function desativar(int $id, int $propriedadeId, ?int $usuarioId): void
    {
        $alterados = DB::table('certificados_digitais')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->update(['status' => 'inativo', 'principal' => 0]);

        if ($alterados > 0) {
            $this->auditar($usuarioId, 'desativar_certificado_digital', 'certificados_digitais', $id, $propriedadeId, 'Certificado digital desativado');
        }
    }

    public function diasValidade($validadeFim): ?int
    {
        if (!$validadeFim) {
            return null;
        }

        return now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($validadeFim)->startOfDay(), false);
    }

    public function validadeTexto($validadeFim): string
    {
        $dias = $this->diasValidade($validadeFim);
        if ($dias === null) {
            return 'Sem validade informada';
        }

        if ($dias < 0) {
            return 'Vencido ha '.abs($dias).' dia(s)';
        }

        if ($dias === 0) {
            return 'Vence hoje';
        }

        return 'Vence em '.$dias.' dia(s)';
    }

    private function atualizarVencidos(int $propriedadeId): void
    {
        DB::table('certificados_digitais')
            ->where('propriedade_id', $propriedadeId)
            ->whereNotNull('validade_fim')
            ->whereDate('validade_fim', '<', now()->toDateString())
            ->where('status', 'ativo')
            ->update(['status' => 'vencido']);
    }

    private function statusValidade(?string $validadeFim): string
    {
        return $validadeFim && \Illuminate\Support\Carbon::parse($validadeFim)->startOfDay()->lt(now()->startOfDay()) ? 'vencido' : 'ativo';
    }

    private function parsePfx(UploadedFile $arquivo, string $senha): array
    {
        if ($senha === '') {
            throw new RuntimeException('Informe a senha do certificado para validar o arquivo.');
        }

        $raw = file_get_contents($arquivo->getRealPath());
        $certs = [];
        if (!$raw || !openssl_pkcs12_read($raw, $certs, $senha)) {
            throw new RuntimeException('Não foi possível abrir o certificado. Confira se o arquivo e a senha estão corretos.');
        }

        $parsed = openssl_x509_parse($certs['cert'] ?? '');
        if (!$parsed) {
            throw new RuntimeException('Certificado lido, mas não foi possível interpretar os dados X509.');
        }

        return [
            'titular' => $this->nomeCertificado($parsed['subject'] ?? []),
            'emissor' => $this->nomeCertificado($parsed['issuer'] ?? []),
            'numero_serie' => (string)($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? ''),
            'validade_inicio' => !empty($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validFrom_time_t']) : null,
            'validade_fim' => !empty($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validTo_time_t']) : null,
        ];
    }

    private function nomeCertificado(array $parts): ?string
    {
        foreach (['CN', 'OU', 'O'] as $key) {
            $value = $parts[$key] ?? null;
            if (is_array($value)) {
                $value = reset($value);
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function auditar(?int $usuarioId, string $acao, string $tabela, int $registroId, int $propriedadeId, string $detalhes): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => $detalhes,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable) {
            // Auditoria nao deve impedir a gestao do certificado.
        }
    }
}
