<?php

namespace App\Services;

use App\Support\FarmContext;
use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CompraFornecedorService
{
    public function propertyId(): int
    {
        return app(FarmContext::class)->propertyId();
    }

    public function statusOptions(): array
    {
        return [
            'ativos' => 'Ativos',
            'inativos' => 'Inativos',
            'todos' => 'Todos',
        ];
    }

    public function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'ativos');

        return [
            'status' => array_key_exists($status, $this->statusOptions()) ? $status : 'ativos',
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    public function listSuppliers(int $propertyId, array $filters = [])
    {
        if (! Schema::hasTable('fornecedores')) {
            return collect();
        }

        $query = DB::table('fornecedores')
            ->where('propriedade_id', $propertyId);

        if (($filters['status'] ?? 'ativos') === 'ativos') {
            $query->where('ativo', true);
        } elseif (($filters['status'] ?? 'ativos') === 'inativos') {
            $query->where('ativo', false);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = (string) $filters['search'];
            $term = '%'.$search.'%';
            $digits = preg_replace('/\D+/', '', $search);

            $query->where(function ($inner) use ($term, $digits): void {
                $inner->where('nome', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('telefone', 'like', $term);

                if ($digits !== '') {
                    $inner->orWhere('documento', 'like', '%'.$digits.'%');
                }
            });
        }

        return $query
            ->orderByDesc('ativo')
            ->orderBy('nome')
            ->limit(500)
            ->get(['id', 'nome', 'documento', 'telefone', 'email', 'ativo', 'created_at'])
            ->map(fn (object $supplier): object => $this->prepareSupplier($supplier));
    }

    public function totals($fornecedores): array
    {
        return [
            'total' => $fornecedores->count(),
            'ativos' => $fornecedores->where('ativo', true)->count(),
        ];
    }

    public function store(array $dados, int $propertyId): int
    {
        if (! Schema::hasTable('fornecedores')) {
            throw new RuntimeException('Cadastro de fornecedores não está disponível. Execute as migrations.');
        }

        $document = $this->normalizeDocument($dados['documento'] ?? null);
        $this->assertDocumentAvailable($propertyId, $document);

        DB::table('fornecedores')->insert([
            'propriedade_id' => $propertyId,
            'nome' => trim($dados['nome']),
            'documento' => $document ?: null,
            'telefone' => trim((string) ($dados['telefone'] ?? '')) ?: null,
            'email' => trim((string) ($dados['email'] ?? '')) ?: null,
            'observacoes' => trim((string) ($dados['observacoes'] ?? '')) ?: null,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = (int) DB::getPdo()->lastInsertId();

        AuditService::log(
            action: 'salvar_fornecedor',
            table: 'fornecedores',
            recordId: $supplierId,
            propertyId: $propertyId,
            details: [
                'nome' => trim($dados['nome']),
                'documento' => $document,
                'email' => trim((string) ($dados['email'] ?? '')),
            ],
        );

        return $supplierId;
    }

    private function assertDocumentAvailable(int $propertyId, string $document): void
    {
        if ($document === '') {
            return;
        }

        $exists = DB::table('fornecedores')
            ->where('propriedade_id', $propertyId)
            ->where('documento', $document)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Já existe fornecedor cadastrado com este CNPJ/CPF nesta propriedade.');
        }
    }

    private function prepareSupplier(object $supplier): object
    {
        $supplier->documento_formatado = $this->formatDocument($supplier->documento ?? null);
        $supplier->telefone = FarmFormat::value($supplier->telefone ?? null);
        $supplier->email = FarmFormat::value($supplier->email ?? null);
        $supplier->status_label = (bool) $supplier->ativo ? 'Ativo' : 'Inativo';
        $supplier->status_tone = (bool) $supplier->ativo ? 'success' : 'warning';

        return $supplier;
    }

    private function normalizeDocument(?string $document): string
    {
        return preg_replace('/\D+/', '', (string) $document) ?? '';
    }

    private function formatDocument(?string $document): string
    {
        $digits = $this->normalizeDocument($document);

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
        }

        return $digits;
    }
}
