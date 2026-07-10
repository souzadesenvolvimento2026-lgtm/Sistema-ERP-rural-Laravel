<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelatorioLancamentosService
{
    public function dados(int $propertyId, Request $request): array
    {
        $filtros = $this->filtros($propertyId, $request);
        $linhas = $this->linhas($propertyId, $filtros);
        $totais = $this->totais($linhas);

        return [
            'activeModule' => 'financeiro',
            'title' => 'Relatorio de Lancamentos',
            'subtitle' => 'Consolidacao de despesas, receitas e transferencias para conferencia financeira.',
            'filtros' => $filtros,
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'banco']),
            'cards' => [
                ['label' => 'Receitas', 'value' => FarmFormat::money($totais['receitas']), 'tone' => 'success'],
                ['label' => 'Despesas', 'value' => FarmFormat::money($totais['despesas']), 'tone' => 'danger'],
                ['label' => 'Transferencias', 'value' => FarmFormat::money($totais['transferencias']), 'tone' => 'warning'],
                ['label' => 'Resultado', 'value' => FarmFormat::money($totais['resultado']), 'tone' => $totais['resultado'] >= 0 ? 'success' : 'danger'],
            ],
            'periodo' => $this->periodoTexto($filtros),
            'linhas' => $linhas,
        ];
    }

    public function exportar(int $propertyId, Request $request)
    {
        $filtros = $this->filtros($propertyId, $request);
        $linhas = $this->linhas($propertyId, $filtros);
        $totais = $this->totais($linhas);
        $periodo = $this->periodoTexto($filtros);
        $formato = (string)$request->query('formato', 'csv');

        if ($formato === 'excel' || $formato === 'xls') {
            return $this->exportarExcel($linhas, $totais, $periodo);
        }

        if ($formato === 'pdf') {
            return $this->exportarPdf($linhas, $totais, $periodo);
        }

        return $this->exportarCsv($linhas, $totais, $periodo);
    }

    private function exportarCsv(Collection $linhas, array $totais, string $periodo)
    {
        $nome = 'farmfort_lancamentos_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($linhas, $totais, $periodo) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['FarmFort - Relatorio de Lancamentos'], ';');
            fputcsv($out, ['Periodo', $periodo], ';');
            fputcsv($out, ['Total receitas', FarmFormat::money($totais['receitas'])], ';');
            fputcsv($out, ['Total despesas', FarmFormat::money($totais['despesas'])], ';');
            fputcsv($out, ['Total transferencias', FarmFormat::money($totais['transferencias'])], ';');
            fputcsv($out, ['Resultado', FarmFormat::money($totais['resultado'])], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Data', 'Tipo', 'Descricao', 'Pessoa', 'Categoria', 'Safra', 'Conta', 'Valor', 'Vencimento/Recebimento', 'Status'], ';');

            foreach ($linhas as $linha) {
                fputcsv($out, [
                    $linha->data,
                    $linha->tipo,
                    $linha->descricao,
                    $linha->pessoa,
                    $linha->categoria,
                    $linha->safra,
                    $linha->conta,
                    $linha->valor,
                    $linha->vencimento,
                    $linha->status,
                ], ';');
            }

            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportarExcel(Collection $linhas, array $totais, string $periodo)
    {
        $nome = 'farmfort_lancamentos_'.date('Ymd_His').'.xls';

        return response()->streamDownload(function () use ($linhas, $totais, $periodo) {
            echo "\xEF\xBB\xBF";
            echo '<!doctype html><html><head><meta charset="utf-8">';
            echo '<style>table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px}th{background:#0f5132;color:#fff}th,td{border:1px solid #999;padding:6px}.money{white-space:nowrap}</style>';
            echo '</head><body>';
            echo '<h2>FarmFort - Relatorio de Lancamentos</h2>';
            echo '<p>Periodo: '.e($periodo).'</p>';
            echo '<table><thead><tr>';
            foreach (['Total receitas', 'Total despesas', 'Total transferencias', 'Resultado'] as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr></thead><tbody><tr>';
            foreach ([$totais['receitas'], $totais['despesas'], $totais['transferencias'], $totais['resultado']] as $total) {
                echo '<td class="money">'.e(FarmFormat::money($total)).'</td>';
            }
            echo '</tr></tbody></table><br>';
            echo '<table><thead><tr>';
            $headers = ['Data', 'Tipo', 'Descricao', 'Pessoa', 'Categoria', 'Safra', 'Conta', 'Valor', 'Vencimento/Recebimento', 'Status'];
            foreach ($headers as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($linhas as $linha) {
                echo '<tr>';
                foreach ([
                    $linha->data,
                    $linha->tipo,
                    $linha->descricao,
                    $linha->pessoa,
                    $linha->categoria,
                    $linha->safra,
                    $linha->conta,
                    $linha->valor,
                    $linha->vencimento,
                    $linha->status,
                ] as $value) {
                    echo '<td>'.e($value).'</td>';
                }
                echo '</tr>';
            }

            if ($linhas->isEmpty()) {
                echo '<tr><td colspan="10">Nenhum lancamento encontrado.</td></tr>';
            }

            echo '</tbody></table></body></html>';
        }, $nome, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function exportarPdf(Collection $linhas, array $totais, string $periodo)
    {
        $nome = 'farmfort_lancamentos_'.date('Ymd_His').'.pdf';
        $pdf = $this->pdf($linhas, $totais, $periodo);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    private function pdf(Collection $linhas, array $totais, string $periodo): string
    {
        $chunks = $linhas->isEmpty() ? collect([collect()]) : $linhas->chunk(32)->values();
        $totalPaginas = $chunks->count();
        $conteudos = [];

        foreach ($chunks as $pagina => $linhasPagina) {
            $stream = '';
            $this->pdfText($stream, 'FarmFort - Relatorio de Lancamentos', 40, 800, 16);
            $this->pdfText($stream, 'Periodo: '.$periodo, 40, 778, 10);
            $this->pdfText($stream, 'Pagina '.($pagina + 1).' de '.$totalPaginas, 455, 800, 9);

            if ($pagina === 0) {
                $this->pdfText($stream, 'Receitas: '.FarmFormat::money($totais['receitas']), 40, 750, 10);
                $this->pdfText($stream, 'Despesas: '.FarmFormat::money($totais['despesas']), 190, 750, 10);
                $this->pdfText($stream, 'Transferencias: '.FarmFormat::money($totais['transferencias']), 340, 750, 10);
                $this->pdfText($stream, 'Resultado: '.FarmFormat::money($totais['resultado']), 40, 732, 10);
                $headerY = 700;
            } else {
                $headerY = 750;
            }

            $this->pdfText($stream, 'Data', 40, $headerY, 9);
            $this->pdfText($stream, 'Tipo', 95, $headerY, 9);
            $this->pdfText($stream, 'Descricao', 160, $headerY, 9);
            $this->pdfText($stream, 'Valor', 455, $headerY, 9);
            $this->pdfText($stream, 'Status', 515, $headerY, 9);

            $y = $headerY - 18;
            foreach ($linhasPagina as $linha) {
                $this->pdfText($stream, $linha->data, 40, $y, 8);
                $this->pdfText($stream, $linha->tipo, 95, $y, 8);
                $this->pdfText($stream, $this->cortar($linha->descricao, 48), 160, $y, 8);
                $this->pdfText($stream, $linha->valor, 455, $y, 8);
                $this->pdfText($stream, $this->cortar($linha->status, 16), 515, $y, 8);
                $y -= 18;
            }

            if ($linhasPagina->isEmpty()) {
                $this->pdfText($stream, 'Nenhum lancamento encontrado.', 40, $y, 9);
            }

            $this->pdfText($stream, 'Documento gerado automaticamente pelo FarmFort ERP Rural.', 40, 40, 8);
            $conteudos[] = $stream;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
        ];
        $kids = [];
        $fontObject = 3 + count($conteudos) * 2;

        foreach ($conteudos as $index => $stream) {
            $pageObject = 3 + ($index * 2);
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject.' 0 R';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontObject.' 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        array_splice($objects, 1, 0, '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>');
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfText(string &$stream, string $text, int $x, int $y, int $size): void
    {
        $stream .= "BT /F1 ".$size." Tf ".$x." ".$y." Td (".$this->pdfEscape($text).") Tj ET\n";
    }

    private function pdfEscape(string $text): string
    {
        $text = $this->cortar($text, 120);
        $latin = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
        $text = $latin !== false ? $latin : $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function cortar(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, max(0, $limit - 1), 'UTF-8').'.' : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, max(0, $limit - 1)).'.' : $text;
    }

    private function filtros(int $propertyId, Request $request): array
    {
        $filtro = (string)$request->query('filtro', 'todos');
        if (!in_array($filtro, ['todos', 'despesas', 'pagar', 'receber', 'receitas', 'transferencias'], true)) {
            $filtro = 'todos';
        }

        $mes = preg_match('/^\d{4}-\d{2}$/', (string)$request->query('mes', '')) ? (string)$request->query('mes') : date('Y-m');
        $dataInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('data_inicio', '')) ? (string)$request->query('data_inicio') : '';
        $dataFim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('data_fim', '')) ? (string)$request->query('data_fim') : '';
        if ($dataInicio !== '' && $dataFim !== '' && $dataFim < $dataInicio) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }

        $contaId = $request->integer('conta_id') ?: null;
        if ($contaId && !DB::table('contas')->where('id', $contaId)->where('propriedade_id', $propertyId)->where('ativo', 1)->exists()) {
            $contaId = null;
        }

        return [
            'filtro' => $filtro,
            'mes' => $mes,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'todos' => $request->query('todos') === '1',
            'conta_id' => $contaId,
            'usa_periodo' => $dataInicio !== '' || $dataFim !== '',
        ];
    }

    private function linhas(int $propertyId, array $filtros): Collection
    {
        $linhas = collect();

        if (in_array($filtros['filtro'], ['todos', 'despesas', 'pagar'], true)) {
            $linhas = $linhas->merge($this->despesas($propertyId, $filtros));
        }

        if (in_array($filtros['filtro'], ['todos', 'receitas', 'receber'], true)) {
            $linhas = $linhas->merge($this->receitas($propertyId, $filtros));
        }

        if (in_array($filtros['filtro'], ['todos', 'transferencias'], true) && Schema::hasTable('transferencias')) {
            $linhas = $linhas->merge($this->transferencias($propertyId, $filtros));
        }

        return $linhas->sortByDesc('data_ordem')->values();
    }

    private function despesas(int $propertyId, array $filtros): Collection
    {
        $campoData = $filtros['filtro'] === 'pagar'
            ? DB::raw('COALESCE(d.data_vencimento, d.data_lancamento)')
            : 'd.data_lancamento';

        return $this->aplicarPeriodo(
            DB::table('despesas as d')
                ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
                ->leftJoin('categorias as sc', 'sc.id', '=', 'd.subcategoria_id')
                ->leftJoin('safras as s', 's.id', '=', 'd.safra_id')
                ->leftJoin('contas as ct', 'ct.id', '=', 'd.conta_id')
                ->where('d.propriedade_id', $propertyId)
                ->where('d.status_pagamento', '!=', 'cancelado')
                ->when($filtros['filtro'] === 'pagar', fn ($query) => $query->whereIn('d.status_pagamento', ['pendente', 'vencido']))
                ->when($filtros['conta_id'], fn ($query) => $query->where('d.conta_id', $filtros['conta_id'])),
            $campoData,
            $filtros
        )
            ->get([
                DB::raw("'Despesa' as tipo"),
                'd.descricao',
                'd.fornecedor as pessoa',
                'd.valor_total',
                'd.data_lancamento as data_base',
                DB::raw('COALESCE(d.data_vencimento, d.data_lancamento) as data_evento'),
                'd.status_pagamento as status',
                's.descricao as safra_nome',
                'ct.nome as conta_nome',
                'ct.banco as conta_banco',
                DB::raw("CONCAT(COALESCE(c.nome, 'Sem categoria'), IF(sc.nome IS NULL OR sc.nome = '', '', CONCAT(' / ', sc.nome))) as categoria_nome"),
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function receitas(int $propertyId, array $filtros): Collection
    {
        $campoData = $filtros['filtro'] === 'receber' ? DB::raw('COALESCE(r.data_recebimento, r.data_venda)') : 'r.data_venda';

        return $this->aplicarPeriodo(
            DB::table('receitas as r')
                ->leftJoin('safras as s', 's.id', '=', 'r.safra_id')
                ->leftJoin('contas as ct', 'ct.id', '=', 'r.conta_id')
                ->where('r.propriedade_id', $propertyId)
                ->when($filtros['filtro'] === 'receber', fn ($query) => $query->where('r.status', 'pendente'), fn ($query) => $query->where('r.status', '!=', 'cancelado'))
                ->when($filtros['conta_id'], fn ($query) => $query->where('r.conta_id', $filtros['conta_id'])),
            $campoData,
            $filtros
        )
            ->get([
                DB::raw("'Receita' as tipo"),
                'r.descricao',
                'r.comprador as pessoa',
                'r.valor_total',
                'r.data_venda as data_base',
                DB::raw('COALESCE(r.data_recebimento, r.data_venda) as data_evento'),
                'r.status',
                's.descricao as safra_nome',
                'ct.nome as conta_nome',
                'ct.banco as conta_banco',
                DB::raw("'Receita' as categoria_nome"),
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function transferencias(int $propertyId, array $filtros): Collection
    {
        return $this->aplicarPeriodo(
            DB::table('transferencias as tf')
                ->join('contas as co', 'co.id', '=', 'tf.conta_origem_id')
                ->join('contas as cd', 'cd.id', '=', 'tf.conta_destino_id')
                ->where('tf.propriedade_id', $propertyId)
                ->when($filtros['conta_id'], function ($query) use ($filtros) {
                    $query->where(function ($inner) use ($filtros) {
                        $inner->where('tf.conta_origem_id', $filtros['conta_id'])
                            ->orWhere('tf.conta_destino_id', $filtros['conta_id']);
                    });
                }),
            'tf.data_transferencia',
            $filtros
        )
            ->get([
                DB::raw("'Transferencia' as tipo"),
                DB::raw("COALESCE(tf.descricao, 'Transferencia entre contas') as descricao"),
                DB::raw("CONCAT('Saiu de: ', co.nome) as pessoa"),
                'tf.valor as valor_total',
                'tf.data_transferencia as data_base',
                'tf.data_transferencia as data_evento',
                DB::raw("'transferida' as status"),
                DB::raw("'-' as safra_nome"),
                DB::raw("CONCAT('Entrou em: ', cd.nome) as conta_nome"),
                DB::raw('NULL as conta_banco'),
                DB::raw("'Transferencia entre contas' as categoria_nome"),
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function aplicarPeriodo($query, $campo, array $filtros)
    {
        if ($filtros['todos']) {
            return $query;
        }

        if ($filtros['usa_periodo']) {
            return $query
                ->when($filtros['data_inicio'] !== '', fn ($q) => $q->where($campo, '>=', $filtros['data_inicio']))
                ->when($filtros['data_fim'] !== '', fn ($q) => $q->where($campo, '<=', $filtros['data_fim']));
        }

        return $query->whereRaw('DATE_FORMAT('.$this->campoSql($campo).", '%Y-%m') = ?", [$filtros['mes']]);
    }

    private function normalizar($row): object
    {
        $conta = trim((string)($row->conta_nome ?? ''));
        if (!empty($row->conta_banco)) {
            $conta .= ($conta ? ' - ' : '') . $row->conta_banco;
        }

        return (object)[
            'data_ordem' => $row->data_base,
            'data' => FarmFormat::date($row->data_base),
            'tipo' => (string)$row->tipo,
            'descricao' => (string)$row->descricao,
            'pessoa' => (string)($row->pessoa ?: '-'),
            'categoria' => (string)($row->categoria_nome ?: '-'),
            'safra' => (string)($row->safra_nome ?: '-'),
            'conta' => $conta ?: 'Nao informada',
            'valor_raw' => (float)$row->valor_total,
            'valor' => FarmFormat::money($row->valor_total),
            'vencimento' => FarmFormat::date($row->data_evento),
            'status' => ucfirst(str_replace('_', ' ', (string)$row->status)),
        ];
    }

    private function totais(Collection $linhas): array
    {
        $receitas = (float)$linhas->where('tipo', 'Receita')->sum('valor_raw');
        $despesas = (float)$linhas->where('tipo', 'Despesa')->sum('valor_raw');
        $transferencias = (float)$linhas->where('tipo', 'Transferencia')->sum('valor_raw');

        return [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'transferencias' => $transferencias,
            'resultado' => $receitas - $despesas,
        ];
    }

    private function periodoTexto(array $filtros): string
    {
        if ($filtros['todos']) {
            return 'Todos';
        }

        if ($filtros['usa_periodo']) {
            return ($filtros['data_inicio'] ? FarmFormat::date($filtros['data_inicio']) : 'inicio')
                .' a '.($filtros['data_fim'] ? FarmFormat::date($filtros['data_fim']) : 'hoje');
        }

        return date('m/Y', strtotime($filtros['mes'].'-01'));
    }

    private function campoSql($campo): string
    {
        return $campo instanceof \Illuminate\Database\Query\Expression ? $campo->getValue(DB::connection()->getQueryGrammar()) : $campo;
    }
}
