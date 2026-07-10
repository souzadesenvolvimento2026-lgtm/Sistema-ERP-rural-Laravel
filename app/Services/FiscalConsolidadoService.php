<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FiscalConsolidadoService
{
    public function pagina(int $propertyId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($propertyId, $filtros);

        return [
            'activeModule' => 'fiscal',
            'title' => 'Fiscal',
            'subtitle' => 'Fiscal Consolidado · Listagem consolidada de pedidos e notas fiscais aprovados.',
            'filtros' => $filtros,
            'rows' => $rows,
            'cards' => [
                ['label' => 'Registros', 'value' => (string)$rows->count(), 'tone' => 'success'],
                ['label' => 'Pedidos', 'value' => (string)$rows->where('type', 'pedido')->count(), 'tone' => 'success'],
                ['label' => 'Notas fiscais', 'value' => (string)$rows->where('type', 'nota_fiscal')->count(), 'tone' => 'success'],
                ['label' => 'Total', 'value' => FarmFormat::money((float)$rows->sum('total_raw')), 'tone' => 'warning'],
            ],
            'statusOptions' => [
                'aprovado' => 'Pedido aprovado',
                'aprovado_baixado' => 'Pedido aprovado/baixado',
                'baixado' => 'Pedido baixado',
                'aprovada' => 'Nota fiscal aprovada',
            ],
        ];
    }

    private function filtros(Request $request): array
    {
        return [
            'status' => trim((string)$request->query('status', '')),
            'date_from' => trim((string)$request->query('date_from', '')),
            'date_to' => trim((string)$request->query('date_to', '')),
            'supplier' => trim((string)$request->query('supplier', '')),
        ];
    }

    private function rows(int $propertyId, array $filtros): Collection
    {
        return $this->orders($propertyId, $filtros)
            ->merge($this->invoices($propertyId, $filtros))
            ->sortByDesc('date_raw')
            ->values();
    }

    private function orders(int $propertyId, array $filtros): Collection
    {
        return $this->applyCommonFilters(
            DB::table('fiscal_orders as fo')
                ->where('fo.propriedade_id', $propertyId)
                ->whereIn('fo.status', ['aprovado', 'aprovado_baixado', 'baixado']),
            'fo.approved_at',
            'fo.issue_date',
            'fo.supplier_name',
            'fo.supplier_cnpj',
            $filtros
        )
            ->select([
                'fo.id',
                'fo.order_number',
                'fo.supplier_name',
                'fo.supplier_cnpj',
                'fo.issue_date',
                'fo.approved_at',
                'fo.total_value',
                'fo.status',
                DB::raw('(SELECT COUNT(*) FROM fiscal_order_invoices foi WHERE foi.order_id = fo.id) as linked_count'),
            ])
            ->orderByDesc('fo.issue_date')
            ->limit(160)
            ->get()
            ->map(function ($order) {
                $date = $order->approved_at ?: $order->issue_date;

                return (object)[
                    'type' => 'pedido',
                    'type_label' => 'Pedido',
                    'document_number' => $order->order_number,
                    'supplier_name' => $order->supplier_name,
                    'supplier_cnpj' => $order->supplier_cnpj,
                    'date_raw' => $date,
                    'date' => FarmFormat::date($date),
                    'total_raw' => (float)$order->total_value,
                    'total' => FarmFormat::money($order->total_value),
                    'status' => $this->statusLabel((string)$order->status),
                    'origin' => (int)$order->linked_count > 0 ? 'Pedido com nota vinculada' : 'Pedido sem nota vinculada',
                    'detail_url' => route('compras.pedidos.show', $order->id),
                ];
            });
    }

    private function invoices(int $propertyId, array $filtros): Collection
    {
        return $this->applyCommonFilters(
            DB::table('fiscal_invoices as fi')
                ->where('fi.propriedade_id', $propertyId)
                ->whereIn('fi.status', ['aprovada', 'aprovado']),
            'fi.approved_at',
            'fi.issue_date',
            'fi.issuer_name',
            'fi.issuer_cnpj',
            $filtros
        )
            ->select([
                'fi.id',
                'fi.invoice_number',
                'fi.series',
                'fi.issuer_name',
                'fi.issuer_cnpj',
                'fi.issue_date',
                'fi.approved_at',
                'fi.total_value',
                'fi.status',
                DB::raw('(SELECT COUNT(*) FROM fiscal_order_invoices foi WHERE foi.invoice_id = fi.id) as linked_count'),
            ])
            ->orderByDesc('fi.issue_date')
            ->limit(160)
            ->get()
            ->map(function ($invoice) {
                $number = trim((string)$invoice->invoice_number);
                if ($invoice->series) {
                    $number .= ' / Serie '.$invoice->series;
                }
                $date = $invoice->approved_at ?: $invoice->issue_date;

                return (object)[
                    'type' => 'nota_fiscal',
                    'type_label' => 'Nota Fiscal',
                    'document_number' => $number,
                    'supplier_name' => $invoice->issuer_name,
                    'supplier_cnpj' => $invoice->issuer_cnpj,
                    'date_raw' => $date,
                    'date' => FarmFormat::date($date),
                    'total_raw' => (float)$invoice->total_value,
                    'total' => FarmFormat::money($invoice->total_value),
                    'status' => $this->statusLabel((string)$invoice->status),
                    'origin' => (int)$invoice->linked_count > 0 ? 'Nota fiscal vinculada a pedido' : 'Nota fiscal avulsa',
                    'detail_url' => route('modules.show', ['module' => 'fiscal']),
                ];
            });
    }

    private function applyCommonFilters($query, string $approvedColumn, string $dateColumn, string $supplierColumn, string $cnpjColumn, array $filtros)
    {
        return $query
            ->when($filtros['status'] !== '', fn ($q) => $q->where('status', $filtros['status']))
            ->when($filtros['date_from'] !== '', fn ($q) => $q->whereRaw('COALESCE('.$approvedColumn.', '.$dateColumn.') >= ?', [$filtros['date_from']]))
            ->when($filtros['date_to'] !== '', fn ($q) => $q->whereRaw('COALESCE('.$approvedColumn.', '.$dateColumn.') <= ?', [$filtros['date_to']]))
            ->when($filtros['supplier'] !== '', function ($q) use ($supplierColumn, $cnpjColumn, $filtros) {
                $term = '%'.$filtros['supplier'].'%';
                $q->where(function ($inner) use ($supplierColumn, $cnpjColumn, $term) {
                    $inner->where($supplierColumn, 'like', $term)
                        ->orWhere($cnpjColumn, 'like', $term);
                });
            });
    }

    private function statusLabel(string $status): string
    {
        return [
            'aprovado' => 'Aprovado',
            'aprovado_baixado' => 'Aprovado/Baixado',
            'baixado' => 'Baixado',
            'aprovada' => 'Aprovada',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
