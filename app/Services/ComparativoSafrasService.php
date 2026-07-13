<?php

namespace App\Services;

use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComparativoSafrasService
{
    public function dados(Request $request): array
    {
        $context = app(FarmContext::class);
        $fazendas = DB::table('propriedades')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']);
        $propertyId = $this->fazendaId($request, $context->propertyId(), $fazendas);
        $ciclo = $this->ciclo($request);
        $modo = $request->query('modo') === 'sacas_ha' ? 'sacas_ha' : 'reais';

        $safras = DB::table('safras')
            ->leftJoin('culturas', 'culturas.id', '=', 'safras.cultura_id')
            ->where('safras.propriedade_id', $propertyId)
            ->when($ciclo !== '', fn ($query) => $query->where('safras.safra_referencia', $ciclo))
            ->orderBy('safras.data_inicio')
            ->orderBy('safras.id')
            ->get(['safras.*', 'culturas.nome as cultura_nome']);

        $safraIds = $safras->pluck('id')->map(fn ($id) => (int)$id)->all();
        $areas = $safras->pluck('area_plantada', 'id')->map(fn ($area) => (float)$area)->all();
        $precos = $this->precosMedios($propertyId, $safraIds);
        $grupos = $this->grupos($propertyId, $safraIds, $precos);
        $linhas = $this->linhas($grupos, $safras, $areas, $precos, $modo);

        return [
            'activeModule' => 'relatorios',
            'title' => 'Comparativo de Safras',
            'subtitle' => 'Comparacao de custos, despesas e preco medio entre safras.',
            'fazendas' => $fazendas,
            'propertyId' => $propertyId,
            'ciclo' => $ciclo,
            'modo' => $modo,
            'safras' => $safras,
            'linhas' => $linhas,
            'cards' => [
                ['label' => 'Safras', 'value' => (string)$safras->count(), 'tone' => 'success'],
                ['label' => 'Grupos', 'value' => (string)count($grupos), 'tone' => 'success'],
                ['label' => 'Modo', 'value' => $modo === 'sacas_ha' ? 'sc/ha' : 'R$/ha', 'tone' => 'warning'],
                ['label' => 'Linhas', 'value' => (string)$linhas->count(), 'tone' => 'success'],
            ],
            'avisos' => [
                'despesas' => DB::table('despesas')->where('propriedade_id', $propertyId)->whereNull('safra_id')->where('status_pagamento', '!=', 'cancelado')->count(),
                'receitas' => DB::table('receitas')->where('propriedade_id', $propertyId)->whereNull('safra_id')->where('status', '!=', 'cancelado')->count(),
            ],
        ];
    }

    public function exportar(Request $request, string $formato)
    {
        $dados = $this->dados($request);
        $formato = in_array($formato, ['csv', 'excel', 'xls', 'pdf'], true) ? $formato : 'csv';
        $headers = $this->cabecalho($dados);
        $filename = 'farmfort_comparativo_safras_'.now()->format('Ymd_His');

        if ($formato === 'pdf') {
            return response($this->pdf($dados, $headers), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        if ($formato === 'excel' || $formato === 'xls') {
            return response()->streamDownload(function () use ($dados, $headers): void {
                echo "\xEF\xBB\xBF";
                echo '<!doctype html><html><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>';
                foreach ($headers as $header) {
                    echo '<th>'.e($header).'</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($dados['linhas'] as $linha) {
                    echo '<tr><td>'.e($linha->nome).'</td>';
                    foreach ($dados['safras'] as $safra) {
                        echo '<td>'.e($linha->valores[(int)$safra->id] ?? '0,00').'</td>';
                    }
                    echo '<td>'.e($linha->media).'</td></tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename.'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($dados, $headers): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($dados['linhas'] as $linha) {
                $row = [$linha->nome];
                foreach ($dados['safras'] as $safra) {
                    $row[] = $linha->valores[(int)$safra->id] ?? '0,00';
                }
                $row[] = $linha->media;
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cabecalho(array $dados): array
    {
        $unidade = $dados['modo'] === 'sacas_ha' ? 'sc/ha' : 'R$/ha';

        return array_merge(
            ['Categoria'],
            $dados['safras']->map(fn ($safra) => $safra->descricao.' ('.$unidade.')')->all(),
            ['Média ('.$unidade.')']
        );
    }

    private function pdf(array $dados, array $headers): string
    {
        $colors = [
            'paper' => [1.000, 1.000, 1.000],
            'ink' => [0.058, 0.090, 0.133],
            'muted' => [0.361, 0.439, 0.525],
            'line' => [0.835, 0.878, 0.918],
            'soft' => [0.937, 0.980, 0.965],
            'softStrong' => [0.875, 0.957, 0.929],
            'green' => [0.027, 0.525, 0.325], // #078653
            'greenStrong' => [0.012, 0.384, 0.243], // #03623e
            'red' => [0.863, 0.208, 0.271], // #dc3545
        ];

        $pageWidth = 842.0;
        $pageHeight = 595.0;
        $rowsPerPage = 20;
        $linhas = $dados['linhas'] instanceof Collection ? $dados['linhas'] : collect($dados['linhas']);
        $chunks = $linhas->isEmpty() ? [collect()] : $linhas->chunk($rowsPerPage)->values()->all();
        $streams = [];
        $totalPages = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $stream = '';
            $this->pdfComparativoPage(
                $stream,
                $dados,
                $headers,
                $chunk instanceof Collection ? $chunk : collect($chunk),
                $index + 1,
                $totalPages,
                $pageWidth,
                $pageHeight,
                $colors
            );
            $streams[] = $stream;
        }

        return $this->pdfDocument($streams, $pageWidth, $pageHeight);
    }

    private function pdfComparativoPage(
        string &$stream,
        array $dados,
        array $headers,
        Collection $linhas,
        int $page,
        int $totalPages,
        float $pageWidth,
        float $pageHeight,
        array $colors
    ): void {
        $margin = 30.0;
        $usableWidth = $pageWidth - ($margin * 2);
        $fazenda = $dados['fazendas']->firstWhere('id', $dados['propertyId']);
        $fazendaNome = $fazenda->nome ?? 'Fazenda selecionada';
        $modoLabel = $dados['modo'] === 'sacas_ha' ? 'Sacas por hectare' : 'Reais por hectare';
        $cicloLabel = match ($dados['ciclo']) {
            'primeira' => '1ª Safra',
            'segunda' => '2ª Safra',
            'terceira' => '3ª Safra',
            default => 'Todas',
        };

        $this->pdfRect($stream, 0, 0, $pageWidth, $pageHeight, $colors['paper']);
        $this->pdfRect($stream, $margin, 524, 5, 44, $colors['green']);
        $this->pdfFavicon($stream, $margin + 17, 535, 30, $colors);

        $this->pdfText($stream, 'FarmFort ERP Rural', $margin + 58, 558, 12, 'F2', $colors['greenStrong']);
        $this->pdfText($stream, 'Comparativo de Safras', $margin + 58, 538, 20, 'F2', $colors['ink']);
        $this->pdfText($stream, 'Compare custos, despesas e rentabilidade entre as safras da fazenda.', $margin + 58, 522, 9, 'F1', $colors['muted']);

        $metaX = $pageWidth - $margin - 198;
        $this->pdfRect($stream, $metaX, 524, 198, 44, $colors['soft'], $colors['line']);
        $this->pdfText($stream, 'Emissão', $metaX + 10, 554, 7, 'F2', $colors['muted']);
        $this->pdfTextRight($stream, now()->format('d/m/Y H:i'), $metaX + 188, 554, 8, 'F1', $colors['ink']);
        $this->pdfText($stream, 'Formato', $metaX + 10, 538, 7, 'F2', $colors['muted']);
        $this->pdfTextRight($stream, 'PDF econômico', $metaX + 188, 538, 8, 'F1', $colors['ink']);

        $this->pdfLine($stream, $margin, 506, $pageWidth - $margin, 506, $colors['green'], 1.2);

        $filterY = 466.0;
        $filterWidth = ($usableWidth - 16) / 3;
        $this->pdfFilterBox($stream, $margin, $filterY, $filterWidth, 'Fazenda', (string)$fazendaNome, $colors);
        $this->pdfFilterBox($stream, $margin + $filterWidth + 8, $filterY, $filterWidth, 'Safra de referência', $cicloLabel, $colors);
        $this->pdfFilterBox($stream, $margin + (($filterWidth + 8) * 2), $filterY, $filterWidth, 'Visualização', $modoLabel, $colors);

        $summaryY = 422.0;
        $summaryWidth = ($usableWidth - 16) / 3;
        $this->pdfSummaryBox($stream, $margin, $summaryY, $summaryWidth, 'Safras comparadas', (string)$dados['safras']->count(), $colors);
        $this->pdfSummaryBox($stream, $margin + $summaryWidth + 8, $summaryY, $summaryWidth, 'Modo', $dados['modo'] === 'sacas_ha' ? 'sc/ha' : 'R$/ha', $colors);
        $this->pdfSummaryBox($stream, $margin + (($summaryWidth + 8) * 2), $summaryY, $summaryWidth, 'Linhas do relatório', (string)$dados['linhas']->count(), $colors);

        $this->pdfText($stream, 'Detalhamento por categoria', $margin, 397, 10, 'F2', $colors['greenStrong']);

        if ($dados['safras']->isEmpty()) {
            $this->pdfRect($stream, $margin, 222, $usableWidth, 112, $colors['soft'], $colors['line']);
            $this->pdfText($stream, 'Nenhuma safra encontrada para a fazenda selecionada.', $margin + 170, 286, 14, 'F2', $colors['ink']);
            $this->pdfText($stream, 'Cadastre uma safra para visualizar o comparativo.', $margin + 244, 264, 9, 'F1', $colors['muted']);
            $this->pdfFooter($stream, $page, $totalPages, $pageWidth, $colors);
            return;
        }

        $this->pdfTable($stream, $dados, $headers, $linhas, $margin, 382, $usableWidth, $colors);
        $this->pdfFooter($stream, $page, $totalPages, $pageWidth, $colors);
    }

    private function pdfFilterBox(string &$stream, float $x, float $y, float $w, string $label, string $value, array $colors): void
    {
        $this->pdfRect($stream, $x, $y, $w, 34, $colors['paper'], $colors['line']);
        $this->pdfText($stream, $label, $x + 9, $y + 22, 7, 'F2', $colors['muted']);
        $this->pdfText($stream, $this->cortar($value, 42), $x + 9, $y + 8, 9, 'F1', $colors['ink']);
    }

    private function pdfSummaryBox(string &$stream, float $x, float $y, float $w, string $label, string $value, array $colors): void
    {
        $this->pdfRect($stream, $x, $y, 4, 32, $colors['green']);
        $this->pdfRect($stream, $x + 4, $y, $w - 4, 32, $colors['paper'], $colors['line']);
        $this->pdfText($stream, $label, $x + 12, $y + 19, 7, 'F2', $colors['muted']);
        $this->pdfText($stream, $value, $x + 12, $y + 7, 11, 'F2', $colors['greenStrong']);
    }

    private function pdfTable(string &$stream, array $dados, array $headers, Collection $linhas, float $x, float $topY, float $w, array $colors): void
    {
        $headerHeight = 21.0;
        $rowHeight = 15.7;
        $valueHeaders = array_values(array_slice($headers, 1));
        $valueCount = max(1, count($valueHeaders));
        $firstWidth = min(230.0, max(178.0, $w * 0.30));
        $valueWidth = ($w - $firstWidth) / $valueCount;
        $headerFont = $valueWidth < 72 ? 6 : 7;
        $bodyFont = $valueWidth < 72 ? 6.2 : 7.2;

        $this->pdfRect($stream, $x, $topY - $headerHeight, $w, $headerHeight, $colors['softStrong'], $colors['line']);
        $this->pdfText($stream, 'Categoria', $x + 8, $topY - 14, 7.2, 'F2', $colors['greenStrong']);

        $colX = $x + $firstWidth;
        foreach ($valueHeaders as $header) {
            $limit = max(9, (int)floor($valueWidth / 4.8));
            $this->pdfText($stream, $this->cortar((string)$header, $limit), $colX + 4, $topY - 14, $headerFont, 'F2', $colors['greenStrong']);
            $colX += $valueWidth;
        }

        if ($linhas->isEmpty()) {
            $emptyY = $topY - $headerHeight - 54;
            $this->pdfRect($stream, $x, $emptyY, $w, 54, $colors['paper'], $colors['line']);
            $this->pdfText($stream, 'Nenhum dado encontrado para os filtros informados.', $x + 220, $emptyY + 23, 9, 'F1', $colors['muted']);
            return;
        }

        foreach ($linhas->values() as $index => $linha) {
            $rowTop = $topY - $headerHeight - ($index * $rowHeight);
            $rowBottom = $rowTop - $rowHeight;
            $isGroup = (bool)($linha->grupo ?? false);
            $parentKey = (string)($linha->parent_key ?? '');
            $key = (string)($linha->key ?? '');
            $isExpense = $key === 'despesa' || $parentKey === 'despesa';
            $isRevenue = str_contains($key, 'receita') || str_contains($key, 'preco') || str_contains($parentKey, 'receita');
            $fill = $isGroup ? $colors['softStrong'] : $colors['paper'];

            $this->pdfRect($stream, $x, $rowBottom, $w, $rowHeight, $fill);
            $this->pdfLine($stream, $x, $rowBottom, $x + $w, $rowBottom, $colors['line'], 0.35);

            $labelColor = $isGroup ? $colors['greenStrong'] : $colors['ink'];
            $valueColor = $isExpense ? $colors['red'] : ($isRevenue ? $colors['greenStrong'] : $colors['ink']);
            $font = $isGroup ? 'F2' : 'F1';
            $labelPrefix = $isGroup ? '> ' : '   ';
            $this->pdfText($stream, $labelPrefix.$this->cortar((string)$linha->nome, 40), $x + 8, $rowBottom + 4.7, $bodyFont, $font, $labelColor);

            $values = [];
            foreach ($dados['safras'] as $safra) {
                $values[] = $linha->valores[(int)$safra->id] ?? '0,00';
            }
            $values[] = $linha->media;

            $colX = $x + $firstWidth;
            foreach (array_slice($values, 0, $valueCount) as $value) {
                $this->pdfTextRight($stream, (string)$value, $colX + $valueWidth - 7, $rowBottom + 4.7, $bodyFont, $font, $valueColor);
                $colX += $valueWidth;
            }
        }
    }

    private function pdfFooter(string &$stream, int $page, int $totalPages, float $pageWidth, array $colors): void
    {
        $this->pdfLine($stream, 30, 34, $pageWidth - 30, 34, $colors['line'], 0.45);
        $this->pdfText($stream, 'Documento gerado automaticamente pelo FarmFort ERP Rural.', 30, 20, 7, 'F1', $colors['muted']);
        $this->pdfTextRight($stream, 'Página '.$page.' de '.$totalPages, $pageWidth - 30, 20, 7, 'F1', $colors['muted']);
    }

    private function pdfDocument(array $streams, float $pageWidth, float $pageHeight): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pageObjects = [];

        foreach ($streams as $stream) {
            $contentNumber = count($objects) + 1;
            $objects[] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $pageNumber = count($objects) + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->pdfNumber($pageWidth).' '.$this->pdfNumber($pageHeight).'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentNumber.' 0 R >>';
            $pageObjects[] = $pageNumber.' 0 R';
        }

        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $pageObjects).'] /Count '.count($pageObjects).' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

    private function pdfText(string &$stream, string $text, float $x, float $y, float $size, string $font = 'F1', array $color = [0, 0, 0]): void
    {
        $stream .= "BT /".$font.' '.$this->pdfNumber($size).' Tf '.$this->pdfColor($color, 'rg').' '.$this->pdfNumber($x).' '.$this->pdfNumber($y).' Td ('.$this->pdfEscape($text).") Tj ET\n";
    }

    private function pdfTextRight(string &$stream, string $text, float $rightX, float $y, float $size, string $font = 'F1', array $color = [0, 0, 0]): void
    {
        $x = $rightX - $this->pdfTextWidth($text, $size);
        $this->pdfText($stream, $text, $x, $y, $size, $font, $color);
    }

    private function pdfTextWidth(string $text, float $size): float
    {
        $latin = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
        $length = strlen($latin !== false ? $latin : $text);

        return $length * $size * 0.48;
    }

    private function pdfRect(string &$stream, float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lineWidth = 0.5): void
    {
        $stream .= "q\n";
        if ($fill !== null) {
            $stream .= $this->pdfColor($fill, 'rg')."\n";
        }
        if ($stroke !== null) {
            $stream .= $this->pdfColor($stroke, 'RG')."\n".$this->pdfNumber($lineWidth)." w\n";
        }
        $operation = $fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S');
        $stream .= $this->pdfNumber($x).' '.$this->pdfNumber($y).' '.$this->pdfNumber($w).' '.$this->pdfNumber($h).' re '.$operation."\nQ\n";
    }

    private function pdfLine(string &$stream, float $x1, float $y1, float $x2, float $y2, array $color, float $lineWidth = 0.5): void
    {
        $stream .= "q\n".$this->pdfColor($color, 'RG')."\n".$this->pdfNumber($lineWidth)." w\n";
        $stream .= $this->pdfNumber($x1).' '.$this->pdfNumber($y1).' m '.$this->pdfNumber($x2).' '.$this->pdfNumber($y2)." l S\nQ\n";
    }

    private function pdfFavicon(string &$stream, float $x, float $y, float $size, array $colors): void
    {
        $this->pdfRect($stream, $x, $y, $size, $size, $colors['soft'], $colors['green']);
        $left = $x + ($size * 0.24);
        $bottom = $y + ($size * 0.22);
        $right = $x + ($size * 0.82);
        $top = $y + ($size * 0.82);
        $stream .= "q\n".$this->pdfColor($colors['green'], 'RG')."\n1.6 w\n";
        $stream .= $this->pdfNumber($left).' '.$this->pdfNumber($bottom + 4).' m '.
            $this->pdfNumber($left + 2).' '.$this->pdfNumber($top - 2).' '.
            $this->pdfNumber($right - 4).' '.$this->pdfNumber($top + 2).' '.
            $this->pdfNumber($right).' '.$this->pdfNumber($top - 6)." c\n";
        $stream .= $this->pdfNumber($right - 1).' '.$this->pdfNumber($top - 18).' '.
            $this->pdfNumber($left + 11).' '.$this->pdfNumber($bottom - 2).' '.
            $this->pdfNumber($left).' '.$this->pdfNumber($bottom + 4)." c S\n";
        $stream .= $this->pdfNumber($left + 4).' '.$this->pdfNumber($bottom + 5).' m '.
            $this->pdfNumber($right - 8).' '.$this->pdfNumber($top - 8)." l S\nQ\n";
    }

    private function pdfColor(array $rgb, string $operator): string
    {
        return $this->pdfNumber((float)$rgb[0]).' '.$this->pdfNumber((float)$rgb[1]).' '.$this->pdfNumber((float)$rgb[2]).' '.$operator;
    }

    private function pdfNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function pdfEscape(string $text): string
    {
        $latin = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $this->cortar($text, 120));
        $text = $latin !== false ? $latin : $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function cortar(string $text, int $limit): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', $text));

        return strlen($text) > $limit ? substr($text, 0, max(0, $limit - 1)).'.' : $text;
    }

    private function fazendaId(Request $request, int $defaultId, Collection $fazendas): int
    {
        $id = $request->integer('fazenda_id') ?: $defaultId;
        return $fazendas->contains('id', $id) ? $id : $defaultId;
    }

    private function ciclo(Request $request): string
    {
        $ciclo = (string)$request->query('ciclo_safra', '');
        return in_array($ciclo, ['', 'primeira', 'segunda', 'terceira'], true) ? $ciclo : '';
    }

    private function grupos(int $propertyId, array $safraIds, array $precos): array
    {
        $grupos = [
            'custo' => ['nome' => 'Custo', 'safras' => [], 'categorias' => []],
            'despesa' => ['nome' => 'Despesa', 'safras' => [], 'categorias' => []],
            'receita' => ['nome' => 'Preço Médio de Vendas', 'safras' => $precos, 'categorias' => []],
        ];

        if (!$safraIds) {
            return $grupos;
        }

        $despesasNormalizadas = DB::table('despesas as d')
            ->join('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('categorias as cp', 'cp.id', '=', 'c.categoria_pai_id')
            ->where('d.propriedade_id', $propertyId)
            ->whereIn('d.safra_id', $safraIds)
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(d.status_aprovacao, '') != 'reprovada'")
            ->select([
                'd.safra_id',
                DB::raw('COALESCE(cp.id, c.id) as categoria_id'),
                DB::raw('COALESCE(cp.nome, c.nome) as categoria_nome'),
                'd.valor_total',
            ]);

        $rows = DB::query()
            ->fromSub($despesasNormalizadas, 'base')
            ->groupBy('safra_id', 'categoria_id', 'categoria_nome')
            ->orderBy('categoria_nome')
            ->get([
                'safra_id',
                'categoria_id',
                'categoria_nome',
                DB::raw('SUM(valor_total) as total'),
            ]);

        foreach ($rows as $row) {
            $sid = (int)$row->safra_id;
            $categoriaId = (int)$row->categoria_id;
            $categoriaNome = (string)$row->categoria_nome;
            $grupoKey = $this->custoDireto($categoriaId, $categoriaNome) ? 'custo' : 'despesa';
            $categoriaKey = $this->slug($this->grupoCusto($categoriaId, $categoriaNome));
            $categoriaLabel = $this->grupoCusto($categoriaId, $categoriaNome);
            $valor = (float)$row->total;

            $grupos[$grupoKey]['safras'][$sid] = ($grupos[$grupoKey]['safras'][$sid] ?? 0.0) + $valor;
            if (!isset($grupos[$grupoKey]['categorias'][$categoriaKey])) {
                $grupos[$grupoKey]['categorias'][$categoriaKey] = ['nome' => $categoriaLabel, 'safras' => [], 'total' => 0.0];
            }
            $grupos[$grupoKey]['categorias'][$categoriaKey]['safras'][$sid] = ($grupos[$grupoKey]['categorias'][$categoriaKey]['safras'][$sid] ?? 0.0) + $valor;
            $grupos[$grupoKey]['categorias'][$categoriaKey]['total'] += $valor;
        }

        $grupos['receita']['categorias']['preco_medio_venda'] = [
            'nome' => 'Preço Médio de Venda',
            'safras' => $precos,
            'total' => array_sum($precos),
            'tipo' => 'preco',
        ];

        return $grupos;
    }

    private function precosMedios(int $propertyId, array $safraIds): array
    {
        if (!$safraIds) {
            return [];
        }

        return DB::table('receitas as r')
            ->where('r.propriedade_id', $propertyId)
            ->whereIn('r.safra_id', $safraIds)
            ->where('r.status', '!=', 'cancelado')
            ->groupBy('r.safra_id')
            ->get(['r.safra_id', DB::raw('SUM(r.valor_total) as total'), DB::raw('SUM(COALESCE(r.quantidade, 0)) as quantidade')])
            ->mapWithKeys(function ($row) {
                $quantidade = (float)$row->quantidade;
                return [(int)$row->safra_id => $quantidade > 0 ? (float)$row->total / $quantidade : 0.0];
            })
            ->all();
    }

    private function linhas(array $grupos, Collection $safras, array $areas, array $precos, string $modo): Collection
    {
        return collect($grupos)->flatMap(function (array $grupo, string $grupoKey) use ($safras, $areas, $precos, $modo) {
            $categorias = collect($grupo['categorias']);
            $linhas = collect([
                $this->linha(
                    $grupo['nome'],
                    $grupo['safras'],
                    $safras,
                    $areas,
                    $precos,
                    $modo,
                    true,
                    false,
                    $grupoKey,
                    null,
                    $categorias->isNotEmpty()
                ),
            ]);

            foreach ($categorias as $categoriaKey => $categoria) {
                $linhas->push($this->linha(
                    $categoria['nome'],
                    $categoria['safras'],
                    $safras,
                    $areas,
                    $precos,
                    $modo,
                    false,
                    ($categoria['tipo'] ?? '') === 'preco',
                    $grupoKey.'-'.$this->domKey((string)$categoriaKey),
                    $grupoKey,
                    false
                ));
            }

            return $linhas;
        })->values();
    }

    private function linha(
        string $nome,
        array $valores,
        Collection $safras,
        array $areas,
        array $precos,
        string $modo,
        bool $grupo,
        bool $preco,
        string $key,
        ?string $parentKey,
        bool $temFilhos
    ): object
    {
        $celulas = [];
        $media = [];
        foreach ($safras as $safra) {
            $sid = (int)$safra->id;
            $base = (float)($valores[$sid] ?? 0);
            $valor = $preco ? $base : $this->valorExibicao($base, $sid, $areas, $precos, $modo);
            $celulas[$sid] = number_format($valor, 2, ',', '.');
            if ($base > 0) {
                $media[] = $valor;
            }
        }

        return (object)[
            'nome' => $nome,
            'grupo' => $grupo,
            'key' => $this->domKey($key),
            'parent_key' => $parentKey ? $this->domKey($parentKey) : null,
            'tem_filhos' => $temFilhos,
            'valores' => $celulas,
            'media' => number_format($media ? array_sum($media) / count($media) : 0, 2, ',', '.'),
        ];
    }

    private function valorExibicao(float $valor, int $safraId, array $areas, array $precos, string $modo): float
    {
        $area = (float)($areas[$safraId] ?? 0);
        if ($area <= 0) {
            return 0.0;
        }

        if ($modo !== 'sacas_ha') {
            return $valor / $area;
        }

        $preco = (float)($precos[$safraId] ?? 0);
        return $preco > 0 ? ($valor / $preco / $area) : 0.0;
    }

    private function custoDireto(int $categoriaId, string $categoria): bool
    {
        return in_array($categoriaId, [1, 2, 6, 9, 10, 13, 27, 36, 98, 110, 114, 118, 121, 122, 124], true)
            || in_array($this->slug($categoria), ['sementes', 'fertilizantes', 'combustivel', 'terceirizacoes agricolas', 'arrendamento', 'corretivos', 'biologicos', 'mao de obra', 'colheita', 'adjuvante', 'frete', 'nutricao de plantas', 'oleo mineral vegetal', 'lubrificantes', 'quimico'], true);
    }

    private function grupoCusto(int $categoriaId, string $categoria): string
    {
        return [6 => 'Combustivel', 9 => 'Terceirizacoes agricolas', 27 => 'Biologicos'][$categoriaId] ?? $categoria;
    }

    private function slug(string $texto): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $texto = strtolower($ascii !== false ? $ascii : $texto);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    private function domKey(string $texto): string
    {
        return str_replace(' ', '-', $this->slug($texto));
    }
}
