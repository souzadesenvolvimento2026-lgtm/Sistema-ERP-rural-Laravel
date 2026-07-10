<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LivroCaixaService
{
    public function dados(int $propertyId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $movimentos = $this->movimentos($propertyId, $filtros);
        $resumo = $this->resumo($movimentos);

        return [
            'activeModule' => 'financeiro',
            'title' => 'Livro Caixa',
            'subtitle' => 'Entradas recebidas e saídas pagas para conferência contábil.',
            'filtros' => $filtros,
            'meses' => $this->meses(),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'banco']),
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'cards' => [
                ['label' => 'Entradas', 'value' => FarmFormat::money($resumo['entradas']), 'tone' => 'success'],
                ['label' => 'Saidas', 'value' => FarmFormat::money($resumo['saidas']), 'tone' => 'danger'],
                ['label' => 'Saldo', 'value' => FarmFormat::money($resumo['saldo']), 'tone' => $resumo['saldo'] >= 0 ? 'success' : 'danger'],
                ['label' => 'Sem comprovante', 'value' => (string)$resumo['sem_comprovante'], 'tone' => 'warning'],
            ],
            'resumo' => $resumo,
            'resumoMensal' => $this->resumoMensal($movimentos),
            'movimentos' => $movimentos,
        ];
    }

    public function exportar(int $propertyId, Request $request)
    {
        $filtros = $this->filtros($request);
        $movimentos = $this->movimentos($propertyId, $filtros);
        $resumo = $this->resumo($movimentos);
        $formato = (string)$request->query('formato', 'csv');

        if (in_array($formato, ['xls', 'excel'], true)) {
            return $this->exportarExcel($movimentos, $resumo, $filtros);
        }

        if ($formato === 'pdf') {
            return $this->exportarPdf($movimentos, $resumo, $filtros);
        }

        return $this->exportarCsv($movimentos, $resumo, $filtros);
    }

    private function exportarCsv(Collection $movimentos, array $resumo, array $filtros)
    {
        $nome = 'farmfort_livro_caixa_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($movimentos, $resumo, $filtros) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['FarmFort - Livro Caixa'], ';');
            fputcsv($out, ['Período', $this->periodoTexto($filtros)], ';');
            fputcsv($out, ['Entradas', FarmFormat::money($resumo['entradas'])], ';');
            fputcsv($out, ['Saídas', FarmFormat::money($resumo['saidas'])], ';');
            fputcsv($out, ['Saldo', FarmFormat::money($resumo['saldo'])], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Data', 'Tipo', 'Histórico', 'Pessoa', 'Documento', 'Categoria', 'Safra', 'Conta', 'Entrada', 'Saída', 'Comprovante', 'Status'], ';');

            foreach ($movimentos as $movimento) {
                fputcsv($out, [
                    FarmFormat::date($movimento->data_mov),
                    $movimento->tipo,
                    $movimento->descricao,
                    $movimento->pessoa ?: '-',
                    $movimento->documento ?: '-',
                    $movimento->categoria ?: '-',
                    $movimento->safra ?: '-',
                    $movimento->conta ?: '-',
                    $movimento->entrada > 0 ? FarmFormat::money($movimento->entrada) : '',
                    $movimento->saida > 0 ? FarmFormat::money($movimento->saida) : '',
                    $movimento->tem_comprovante ? 'Com arquivo' : 'Sem arquivo',
                    $movimento->status,
                ], ';');
            }

            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportarExcel(Collection $movimentos, array $resumo, array $filtros)
    {
        $nome = 'farmfort_livro_caixa_'.date('Ymd_His').'.xls';

        return response()->streamDownload(function () use ($movimentos, $resumo, $filtros) {
            echo "\xEF\xBB\xBF";
            echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
            echo '<h2>FarmFort - Livro Caixa</h2>';
            echo '<table><tbody>';
            echo '<tr><td><strong>Período</strong></td><td>'.e($this->periodoTexto($filtros)).'</td></tr>';
            echo '<tr><td><strong>Entradas</strong></td><td>'.e(FarmFormat::money($resumo['entradas'])).'</td></tr>';
            echo '<tr><td><strong>Saídas</strong></td><td>'.e(FarmFormat::money($resumo['saidas'])).'</td></tr>';
            echo '<tr><td><strong>Saldo</strong></td><td>'.e(FarmFormat::money($resumo['saldo'])).'</td></tr>';
            echo '</tbody></table><br>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach (['Data', 'Tipo', 'Histórico', 'Pessoa', 'Documento', 'Categoria', 'Safra', 'Conta', 'Entrada', 'Saída', 'Comprovante', 'Status'] as $coluna) {
                echo '<th>'.e($coluna).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($movimentos as $movimento) {
                echo '<tr>';
                foreach ([
                    FarmFormat::date($movimento->data_mov),
                    $movimento->tipo,
                    $movimento->descricao,
                    $movimento->pessoa ?: '-',
                    $movimento->documento ?: '-',
                    $movimento->categoria ?: '-',
                    $movimento->safra ?: '-',
                    $movimento->conta ?: '-',
                    $movimento->entrada > 0 ? FarmFormat::money($movimento->entrada) : '',
                    $movimento->saida > 0 ? FarmFormat::money($movimento->saida) : '',
                    $movimento->tem_comprovante ? 'Com arquivo' : 'Sem arquivo',
                    $movimento->status,
                ] as $valor) {
                    echo '<td>'.e((string)$valor).'</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $nome, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function exportarPdf(Collection $movimentos, array $resumo, array $filtros)
    {
        $nome = 'farmfort_livro_caixa_'.date('Ymd_His').'.pdf';
        $pdf = $this->pdf($movimentos, $resumo, $filtros);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    private function pdf(Collection $movimentos, array $resumo, array $filtros): string
    {
        $chunks = $movimentos->isEmpty() ? collect([collect()]) : $movimentos->chunk(30)->values();
        $totalPaginas = $chunks->count();
        $conteudos = [];

        foreach ($chunks as $pagina => $movimentosPagina) {
            $stream = '';
            $this->pdfText($stream, 'FarmFort - Livro Caixa', 40, 800, 16);
            $this->pdfText($stream, 'Periodo: '.$this->periodoTexto($filtros), 40, 778, 10);
            $this->pdfText($stream, 'Pagina '.($pagina + 1).' de '.$totalPaginas, 455, 800, 9);

            if ($pagina === 0) {
                $this->pdfText($stream, 'Entradas: '.FarmFormat::money($resumo['entradas']), 40, 750, 10);
                $this->pdfText($stream, 'Saidas: '.FarmFormat::money($resumo['saidas']), 190, 750, 10);
                $this->pdfText($stream, 'Saldo: '.FarmFormat::money($resumo['saldo']), 340, 750, 10);
                $this->pdfText($stream, 'Sem comprovante: '.$resumo['sem_comprovante'], 40, 732, 10);
                $headerY = 700;
            } else {
                $headerY = 750;
            }

            $this->pdfText($stream, 'Data', 40, $headerY, 9);
            $this->pdfText($stream, 'Tipo', 95, $headerY, 9);
            $this->pdfText($stream, 'Historico', 150, $headerY, 9);
            $this->pdfText($stream, 'Entrada', 400, $headerY, 9);
            $this->pdfText($stream, 'Saida', 465, $headerY, 9);
            $this->pdfText($stream, 'Status', 525, $headerY, 9);

            $y = $headerY - 18;
            foreach ($movimentosPagina as $movimento) {
                $this->pdfText($stream, FarmFormat::date($movimento->data_mov), 40, $y, 8);
                $this->pdfText($stream, $movimento->tipo, 95, $y, 8);
                $this->pdfText($stream, $this->cortar($movimento->descricao, 42), 150, $y, 8);
                $this->pdfText($stream, $movimento->entrada > 0 ? FarmFormat::money($movimento->entrada) : '-', 400, $y, 8);
                $this->pdfText($stream, $movimento->saida > 0 ? FarmFormat::money($movimento->saida) : '-', 465, $y, 8);
                $this->pdfText($stream, $this->cortar($movimento->status, 12), 525, $y, 8);
                $y -= 18;
            }

            if ($movimentosPagina->isEmpty()) {
                $this->pdfText($stream, 'Nenhum movimento encontrado.', 40, $y, 9);
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

    private function filtros(Request $request): array
    {
        $ano = $request->integer('ano') ?: (int)date('Y');
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int)date('Y');
        }

        $mes = (string)$request->query('mes', 'todos');
        if ($mes !== 'todos') {
            $mesInt = (int)$mes;
            $mes = $mesInt >= 1 && $mesInt <= 12 ? str_pad((string)$mesInt, 2, '0', STR_PAD_LEFT) : 'todos';
        }

        $tipo = (string)$request->query('tipo', 'todos');
        if (!in_array($tipo, ['todos', 'entrada', 'saida'], true)) {
            $tipo = 'todos';
        }

        $comprovante = (string)$request->query('comprovante', 'todos');
        if (!in_array($comprovante, ['todos', 'com', 'sem'], true)) {
            $comprovante = 'todos';
        }

        return [
            'ano' => $ano,
            'mes' => $mes,
            'tipo' => $tipo,
            'safra_id' => $request->integer('safra_id') ?: null,
            'conta_id' => $request->integer('conta_id') ?: null,
            'categoria_id' => $request->integer('categoria_id') ?: null,
            'comprovante' => $comprovante,
        ];
    }

    private function movimentos(int $propertyId, array $filtros): Collection
    {
        $movimentos = collect();

        if ($filtros['tipo'] !== 'saida' && empty($filtros['categoria_id'])) {
            $movimentos = $movimentos->merge($this->entradas($propertyId, $filtros));
        }

        if ($filtros['tipo'] !== 'entrada') {
            $movimentos = $movimentos->merge($this->saidas($propertyId, $filtros));
        }

        return $movimentos
            ->sortBy([['data_mov', 'asc'], ['tipo', 'asc']])
            ->values()
            ->map(function ($row) {
                $row->entrada = (float)$row->entrada;
                $row->saida = (float)$row->saida;
                $row->valor = $row->entrada > 0 ? $row->entrada : $row->saida;
                $row->tem_comprovante = trim((string)($row->comprovante ?? '')) !== '';
                return $row;
            });
    }

    private function entradas(int $propertyId, array $filtros): Collection
    {
        return DB::table('receitas as r')
            ->leftJoin('contas as ct', 'ct.id', '=', 'r.conta_id')
            ->leftJoin('safras as s', 's.id', '=', 'r.safra_id')
            ->where('r.propriedade_id', $propertyId)
            ->where('r.status', 'recebido')
            ->whereNotNull('r.data_recebimento')
            ->whereYear('r.data_recebimento', $filtros['ano'])
            ->when($filtros['mes'] !== 'todos', fn ($query) => $query->whereMonth('r.data_recebimento', (int)$filtros['mes']))
            ->when($filtros['safra_id'], fn ($query) => $query->where('r.safra_id', $filtros['safra_id']))
            ->when($filtros['conta_id'], fn ($query) => $query->where('r.conta_id', $filtros['conta_id']))
            ->when($filtros['comprovante'] === 'com', fn ($query) => $query->whereRaw("COALESCE(r.comprovante, '') <> ''"))
            ->when($filtros['comprovante'] === 'sem', fn ($query) => $query->whereRaw("COALESCE(r.comprovante, '') = ''"))
            ->get([
                'r.id as origem_id',
                DB::raw("'receitas' as origem_tabela"),
                'r.data_recebimento as data_mov',
                DB::raw("'Entrada' as tipo"),
                'r.descricao',
                'r.comprador as pessoa',
                DB::raw("'' as documento"),
                DB::raw("'Receitas' as categoria"),
                DB::raw("'' as talhao"),
                'r.valor_total as entrada',
                DB::raw('0 as saida'),
                'ct.nome as conta',
                's.descricao as safra',
                DB::raw("'' as forma_pagamento"),
                'r.comprovante',
                'r.status',
                'r.observacoes',
            ]);
    }

    private function saidas(int $propertyId, array $filtros): Collection
    {
        return DB::table('despesas as d')
            ->join('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('talhoes as t', 't.id', '=', 'd.talhao_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'd.conta_id')
            ->leftJoin('safras as s', 's.id', '=', 'd.safra_id')
            ->where('d.propriedade_id', $propertyId)
            ->where('d.status_pagamento', 'pago')
            ->where('d.status_aprovacao', 'aprovada')
            ->whereNotNull('d.data_pagamento')
            ->whereYear('d.data_pagamento', $filtros['ano'])
            ->when($filtros['mes'] !== 'todos', fn ($query) => $query->whereMonth('d.data_pagamento', (int)$filtros['mes']))
            ->when($filtros['safra_id'], fn ($query) => $query->where('d.safra_id', $filtros['safra_id']))
            ->when($filtros['conta_id'], fn ($query) => $query->where('d.conta_id', $filtros['conta_id']))
            ->when($filtros['categoria_id'], fn ($query) => $query->where('d.categoria_id', $filtros['categoria_id']))
            ->when($filtros['comprovante'] === 'com', fn ($query) => $query->whereRaw("COALESCE(d.comprovante, '') <> ''"))
            ->when($filtros['comprovante'] === 'sem', fn ($query) => $query->whereRaw("COALESCE(d.comprovante, '') = ''"))
            ->get([
                'd.id as origem_id',
                DB::raw("'despesas' as origem_tabela"),
                'd.data_pagamento as data_mov',
                DB::raw("'Saida' as tipo"),
                'd.descricao',
                'd.fornecedor as pessoa',
                'd.nota_fiscal as documento',
                'c.nome as categoria',
                't.nome as talhao',
                DB::raw('0 as entrada'),
                'd.valor_total as saida',
                'ct.nome as conta',
                's.descricao as safra',
                'd.forma_pagamento',
                'd.comprovante',
                'd.status_pagamento as status',
                'd.observacoes',
            ]);
    }

    private function resumo(Collection $movimentos): array
    {
        $entradas = (float)$movimentos->sum('entrada');
        $saidas = (float)$movimentos->sum('saida');

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $entradas - $saidas,
            'sem_comprovante' => $movimentos->where('tem_comprovante', false)->count(),
            'total_lancamentos' => $movimentos->count(),
        ];
    }

    private function resumoMensal(Collection $movimentos): Collection
    {
        return $movimentos
            ->groupBy(fn ($row) => date('m', strtotime((string)$row->data_mov)))
            ->map(function (Collection $rows, string $mes) {
                $entradas = (float)$rows->sum('entrada');
                $saidas = (float)$rows->sum('saida');

                return (object)[
                    'mes' => $this->meses()[$mes] ?? $mes,
                    'entradas' => FarmFormat::money($entradas),
                    'saidas' => FarmFormat::money($saidas),
                    'saldo' => FarmFormat::money($entradas - $saidas),
                ];
            })
            ->values();
    }

    private function meses(): array
    {
        return [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Março',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];
    }

    private function periodoTexto(array $filtros): string
    {
        $mes = $filtros['mes'] === 'todos' ? 'Todos os meses' : ($this->meses()[$filtros['mes']] ?? $filtros['mes']);

        return $mes.' de '.$filtros['ano'];
    }
}
