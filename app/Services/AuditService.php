<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditService
{
    public function __construct(private readonly RequestContextService $context)
    {
    }

    public static function log(
        string $action,
        string $table,
        ?int $recordId = null,
        ?int $propertyId = null,
        mixed $details = null,
        ?Request $request = null,
        ?int $userId = null,
    ): void {
        app(self::class)->registrar(
            $userId,
            $action,
            $table,
            $recordId,
            $propertyId,
            $details,
            $request,
        );
    }

    public function registrar(
        ?int $usuarioId,
        string $acao,
        string $tabela,
        ?int $registroId = null,
        ?int $propriedadeId = null,
        mixed $detalhes = null,
        ?Request $request = null,
    ): void {
        try {
            $usuarioId ??= (int) session('usuario_id') ?: null;
            $contexto = $this->context->auditContext($request);
            $payload = [
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => $this->formatarDetalhes($detalhes),
                'ip' => $contexto['ip_cliente'] ?? request()->ip(),
                'criado_em' => now(),
            ];

            foreach ($contexto as $column => $value) {
                if (Schema::hasColumn('logs_auditoria', $column)) {
                    $payload[$column] = $value;
                }
            }

            DB::table('logs_auditoria')->insert($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function formatarDetalhes(mixed $detalhes): string
    {
        if ($detalhes === null || $detalhes === '') {
            return 'Ação registrada pelo FarmFort.';
        }

        if (is_array($detalhes)) {
            return json_encode($this->sanitizarArray($detalhes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Ação registrada pelo FarmFort.';
        }

        return $this->sanitizarTexto((string) $detalhes);
    }

    /**
     * @param array<mixed> $dados
     * @return array<mixed>
     */
    private function sanitizarArray(array $dados): array
    {
        $limpo = [];

        foreach ($dados as $chave => $valor) {
            $chaveTexto = strtolower((string) $chave);
            if ($this->chaveSensivel($chaveTexto)) {
                $limpo[$chave] = '[removido]';
                continue;
            }

            $limpo[$chave] = is_array($valor) ? $this->sanitizarArray($valor) : $valor;
        }

        return $limpo;
    }

    private function sanitizarTexto(string $texto): string
    {
        $texto = preg_replace('/(senha|password|token|cookie|authorization|secret|api_key|chave|certificado)\s*[:=]\s*[^;\n\r]+/iu', '$1: [removido]', $texto) ?? $texto;

        return mb_substr($texto, 0, 5000);
    }

    private function chaveSensivel(string $chave): bool
    {
        foreach (['senha', 'password', 'token', 'cookie', 'authorization', 'secret', 'api_key', 'chave', 'certificado'] as $termo) {
            if (str_contains($chave, $termo)) {
                return true;
            }
        }

        return false;
    }
}
