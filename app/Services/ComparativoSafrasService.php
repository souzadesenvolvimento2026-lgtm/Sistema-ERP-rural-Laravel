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
        $stream = '';
        $this->pdfText($stream, 'FarmFort - Comparativo de Safras', 40, 800, 16);
        $this->pdfText($stream, 'Visualizacao: '.($dados['modo'] === 'sacas_ha' ? 'Sacas por hectare' : 'Reais por hectare'), 40, 778, 10);
        $this->pdfText($stream, 'Safras: '.$dados['safras']->count(), 40, 760, 10);
        $this->pdfText($stream, 'Categoria', 40, 730, 9);

        $x = 260;
        foreach (array_slice($headers, 1, 4) as $header) {
            $this->pdfText($stream, $this->cortar($header, 24), $x, 730, 8);
            $x += 75;
        }

        $y = 710;
        foreach ($dados['linhas']->take(28) as $linha) {
            $this->pdfText($stream, $this->cortar($linha->nome, 34), 40, $y, 8);
            $x = 260;
            $values = [];
            foreach ($dados['safras'] as $safra) {
                $values[] = $linha->valores[(int)$safra->id] ?? '0,00';
            }
            $values[] = $linha->media;
            foreach (array_slice($values, 0, 4) as $value) {
                $this->pdfText($stream, (string)$value, $x, $y, 8);
                $x += 75;
            }
            $y -= 18;
        }

        if ($dados['linhas']->isEmpty()) {
            $this->pdfText($stream, 'Nenhum dado encontrado para os filtros informados.', 40, $y, 9);
        } elseif ($dados['linhas']->count() > 28) {
            $this->pdfText($stream, 'Exibindo 28 de '.$dados['linhas']->count().' linhas. Use CSV/Excel para lista completa.', 40, 155, 8);
        }

        $this->pdfText($stream, 'Documento gerado automaticamente pelo FarmFort ERP Rural.', 40, 40, 8);

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

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

    private function pdfText(string &$stream, string $text, int $x, int $y, int $size): void
    {
        $stream .= "BT /F1 ".$size." Tf ".$x." ".$y." Td (".$this->pdfEscape($text).") Tj ET\n";
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
