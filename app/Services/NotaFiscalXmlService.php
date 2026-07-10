<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;

class NotaFiscalXmlService
{
    public function importar(UploadedFile $file, int $propriedadeId, ?int $usuarioId): int
    {
        $xmlRaw = (string)file_get_contents($file->getRealPath());
        $parsed = $this->parse($xmlRaw);

        if (DB::table('fiscal_invoices')->where('access_key', $parsed['invoice']['access_key'])->exists()) {
            throw new RuntimeException('Esta nota fiscal já foi lançada no sistema.');
        }

        return $this->criarNota($parsed, $xmlRaw, $file->getClientOriginalName(), $propriedadeId, $usuarioId);
    }

    public function preview(UploadedFile $file, bool $permitirNotaExistente = false): array
    {
        $xmlRaw = (string)file_get_contents($file->getRealPath());
        $parsed = $this->parse($xmlRaw);

        $notaExistenteId = (int)DB::table('fiscal_invoices')
            ->where('access_key', $parsed['invoice']['access_key'])
            ->value('id');

        if ($notaExistenteId > 0 && !$permitirNotaExistente) {
            throw new RuntimeException('Esta nota fiscal já foi lançada no sistema.');
        }

        return [
            'invoice' => $parsed['invoice'],
            'items' => $parsed['items'],
            'xml_raw' => $xmlRaw,
            'original_name' => $file->getClientOriginalName(),
            'existing_invoice_id' => $notaExistenteId > 0 ? $notaExistenteId : null,
            'created_at' => time(),
        ];
    }

    public function confirmarPreview(array $preview, int $propriedadeId, ?int $usuarioId): int
    {
        if (empty($preview['invoice']) || empty($preview['items']) || empty($preview['xml_raw'])) {
            throw new RuntimeException('Nao ha XML processado para confirmar. Importe o XML novamente.');
        }

        $parsed = [
            'invoice' => $preview['invoice'],
            'items' => $preview['items'],
        ];

        if (DB::table('fiscal_invoices')->where('access_key', $parsed['invoice']['access_key'])->exists()) {
            throw new RuntimeException('Esta nota fiscal já foi lançada no sistema.');
        }

        return $this->criarNota(
            $parsed,
            (string)$preview['xml_raw'],
            (string)($preview['original_name'] ?? 'nota.xml'),
            $propriedadeId,
            $usuarioId
        );
    }

    private function criarNota(array $parsed, string $xmlRaw, string $originalName, int $propriedadeId, ?int $usuarioId): int
    {
        return DB::transaction(function () use ($xmlRaw, $parsed, $originalName, $propriedadeId, $usuarioId): int {
            $path = $this->storeXml($originalName, $parsed['invoice']['access_key'], $xmlRaw);
            $invoice = $parsed['invoice'];

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propriedadeId,
                'user_id' => $usuarioId,
                'access_key' => $invoice['access_key'],
                'invoice_number' => $invoice['invoice_number'],
                'series' => $invoice['series'] ?: null,
                'issue_date' => $invoice['issue_date'],
                'issuer_cnpj' => $invoice['issuer_cnpj'],
                'issuer_name' => $invoice['issuer_name'],
                'recipient_cnpj' => $invoice['recipient_cnpj'] ?: null,
                'recipient_name' => $invoice['recipient_name'] ?: null,
                'total_value' => $invoice['total_value'],
                'status' => 'aguardando_aprovacao',
                'xml_file_path' => $path,
                'created_by' => $usuarioId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invoiceId = (int)DB::getPdo()->lastInsertId();
            foreach ($parsed['items'] as $item) {
                DB::table('fiscal_invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'product_code' => $item['product_code'] ?: null,
                    'description' => $item['description'],
                    'unit' => $item['unit'] ?: null,
                    'quantity' => $item['quantity'],
                    'unit_value' => $item['unit_value'],
                    'total_value' => $item['total_value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->auditar($usuarioId, 'criar_nota_fiscal_xml', 'fiscal_invoices', $invoiceId, $propriedadeId, 'Nota fiscal via XML lancada com status aguardando_aprovacao');

            return $invoiceId;
        });
    }

    private function parse(string $xmlRaw): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlRaw);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Não foi possível ler o XML informado.');
        }

        $infNFe = $this->first($xml->xpath('//*[local-name()="infNFe"]'));
        $ide = $this->first($xml->xpath('//*[local-name()="ide"]'));
        $emit = $this->first($xml->xpath('//*[local-name()="emit"]'));
        $dest = $this->first($xml->xpath('//*[local-name()="dest"]'));
        $total = $this->first($xml->xpath('//*[local-name()="ICMSTot"]'));

        if (!$infNFe || !$ide || !$emit || !$total) {
            throw new RuntimeException('O XML não contém os dados obrigatórios da NF-e.');
        }

        $accessKey = preg_replace('/\D+/', '', (string)$infNFe['Id']);
        if (str_starts_with($accessKey, 'NFe')) {
            $accessKey = substr($accessKey, 3);
        }

        $items = [];
        foreach ($xml->xpath('//*[local-name()="det"]') ?: [] as $det) {
            $prod = $this->first($det->xpath('./*[local-name()="prod"]'));
            if (!$prod) {
                continue;
            }
            $items[] = [
                'product_code' => trim((string)$prod->cProd),
                'description' => trim((string)$prod->xProd),
                'unit' => trim((string)$prod->uCom),
                'quantity' => (float)str_replace(',', '.', (string)$prod->qCom),
                'unit_value' => (float)str_replace(',', '.', (string)$prod->vUnCom),
                'total_value' => (float)str_replace(',', '.', (string)$prod->vProd),
            ];
        }

        if (strlen($accessKey) !== 44 || !$items) {
            throw new RuntimeException('O XML está incompleto ou não possui itens válidos.');
        }

        return [
            'invoice' => [
                'access_key' => $accessKey,
                'invoice_number' => trim((string)$ide->nNF),
                'series' => trim((string)$ide->serie),
                'issue_date' => substr((string)($ide->dhEmi ?: $ide->dEmi), 0, 10),
                'issuer_cnpj' => preg_replace('/\D+/', '', (string)($emit->CNPJ ?: $emit->CPF)),
                'issuer_name' => trim((string)$emit->xNome),
                'recipient_cnpj' => $dest ? preg_replace('/\D+/', '', (string)($dest->CNPJ ?: $dest->CPF)) : '',
                'recipient_name' => $dest ? trim((string)$dest->xNome) : '',
                'total_value' => (float)str_replace(',', '.', (string)$total->vNF),
            ],
            'items' => $items,
        ];
    }

    private function storeXml(string $originalName, string $accessKey, string $xmlRaw): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '', $originalName) ?: 'nota.xml';
        $path = 'fiscal_invoices/fiscal_invoice_' . $accessKey . '_' . date('YmdHis') . '_' . $safeName;
        Storage::disk('local')->put($path, $xmlRaw);

        return 'storage/app/private/' . $path;
    }

    private function first($value): ?SimpleXMLElement
    {
        return is_array($value) && isset($value[0]) && $value[0] instanceof SimpleXMLElement ? $value[0] : null;
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
            // Auditoria nao deve impedir a importacao da nota.
        }
    }
}
