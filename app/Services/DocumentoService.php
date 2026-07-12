<?php

namespace App\Services;

use App\Domain\Fiscal\DocumentCapabilities;
use App\Support\FarmFormat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoService
{
    public function __construct(private readonly DocumentCapabilities $capabilities) {}

    public function pagina(int $propriedadeId): array
    {
        $documentos = DB::table('documentos as d')
            ->leftJoin('safras as s', 's.id', '=', 'd.safra_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'd.usuario_id')
            ->where('d.propriedade_id', $propriedadeId)
            ->orderByRaw('COALESCE(d.data_documento, DATE(d.criado_em)) DESC')
            ->orderByDesc('d.id')
            ->get([
                'd.id',
                'd.tipo',
                'd.titulo',
                'd.numero',
                'd.pessoa',
                'd.data_documento',
                'd.valor',
                'd.arquivo',
                'd.status',
                'd.observacoes',
                'd.criado_em',
                's.descricao as safra_nome',
                'u.nome as usuario_nome',
            ])
            ->map(function (object $documento): object {
                $prepared = $this->capabilities->for((string) $documento->status);
                $prepared['actions'] = array_map(
                    fn (array $action): array => $action + [
                        'route_name' => $action['action'] === 'conferir'
                            ? 'fiscal.documentos.conferir'
                            : 'fiscal.documentos.status',
                    ],
                    $prepared['actions'],
                );

                foreach ($prepared as $capability => $value) {
                    $documento->{$capability} = $value;
                }

                $documento->status_label = FarmFormat::statusLabel((string) $documento->status);
                $documento->has_file = ! empty($documento->arquivo);

                return $documento;
            });

        return [
            'activeModule' => 'fiscal',
            'documentos' => $documentos,
            'safras' => DB::table('safras')
                ->where('propriedade_id', $propriedadeId)
                ->orderByDesc('data_inicio')
                ->get(['id', 'descricao']),
            'tipos' => $this->tipos(),
            'totais' => [
                'documentos' => $documentos->count(),
                'pendentes' => $documentos->where('status', 'pendente')->count(),
                'valor' => (float) $documentos->sum('valor'),
                'com_arquivo' => $documentos->filter(fn ($documento) => ! empty($documento->arquivo))->count(),
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId, ?UploadedFile $arquivo = null): int
    {
        $arquivoNome = null;
        if ($arquivo) {
            File::ensureDirectoryExists(base_path('../uploads/comprovantes'));
            $arquivoNome = 'doc_'.uniqid().'.'.$arquivo->getClientOriginalExtension();
            $arquivo->move(base_path('../uploads/comprovantes'), $arquivoNome);
        }

        DB::table('documentos')->insert([
            'propriedade_id' => $propriedadeId,
            'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId),
            'tipo' => $dados['tipo'] ?? 'outro',
            'titulo' => trim($dados['titulo']),
            'numero' => trim($dados['numero'] ?? '') ?: null,
            'pessoa' => trim($dados['pessoa'] ?? '') ?: null,
            'data_documento' => ($dados['data_documento'] ?? null) ?: null,
            'valor' => $this->money($dados['valor'] ?? 0),
            'arquivo' => $arquivoNome,
            'status' => $dados['status'] ?? 'pendente',
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    public function atualizarStatus(int $id, int $propriedadeId, string $status): void
    {
        $documento = DB::table('documentos')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->first(['id', 'status']);

        if (! $documento) {
            return;
        }

        if (! $this->capabilities->canTransition((string) $documento->status, $status)) {
            throw new \RuntimeException('A transicao de status solicitada nao e permitida para este documento.');
        }

        DB::table('documentos')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->update(['status' => $status]);
    }

    public function baixarArquivo(int $id, int $propriedadeId): BinaryFileResponse
    {
        $documento = DB::table('documentos')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->first(['arquivo', 'titulo']);

        abort_unless($documento && $documento->arquivo, 404);

        $base = realpath(base_path('../uploads/comprovantes'));
        $path = realpath(base_path('../uploads/comprovantes/'.$documento->arquivo));
        abort_unless($base && $path && str_starts_with($path, $base) && is_file($path), 404);

        $nome = preg_replace('/[\r\n"]+/', '', (string) $documento->titulo) ?: (string) $documento->arquivo;

        return response()->download($path, $nome.'.'.pathinfo((string) $documento->arquivo, PATHINFO_EXTENSION), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function tipos(): array
    {
        return ['nota_fiscal', 'contrato', 'receituario', 'boleto', 'comprovante', 'analise_solo', 'mapa', 'outro'];
    }

    private function money($value): float
    {
        $value = trim((string) $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float) $value);
    }

    private function idDaPropriedade(string $table, mixed $id, int $propriedadeId): ?int
    {
        if (! $id) {
            return null;
        }

        $exists = DB::table($table)
            ->where('id', (int) $id)
            ->where('propriedade_id', $propriedadeId)
            ->exists();

        return $exists ? (int) $id : null;
    }
}
