<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CotacaoSojaService
{
    private const URL = 'https://www.noticiasagricolas.com.br/cotacoes/soja/soja-mercado-fisico-sindicatos-e-cooperativas';

    private const FONTE = 'Noticias Agricolas';

    public function tick(?int $propriedadeId, bool $force = false): bool
    {
        if (! $propriedadeId) {
            return false;
        }

        $prop = DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->where('ativo', 1)
            ->first();

        if (! $prop) {
            return false;
        }

        try {
            return $this->atualizarPropriedade((array) $prop, null, $force);
        } catch (Throwable $e) {
            $this->marcarFalha($propriedadeId, $e);

            return false;
        }
    }

    public function atualizarTodas(bool $force = false): array
    {
        $mercado = null;
        $ok = 0;
        $falhas = 0;

        foreach (DB::table('propriedades')->where('ativo', 1)->orderBy('id')->get() as $prop) {
            try {
                if ($force || $this->buscaVencida((array) $prop)) {
                    $mercado = $mercado ?: $this->buscarMercado();
                    if ($this->atualizarPropriedade((array) $prop, $mercado, $force)) {
                        $ok++;
                    }
                }
            } catch (Throwable $e) {
                $this->marcarFalha((int) $prop->id, $e);
                $falhas++;
            }
        }

        return ['atualizadas' => $ok, 'falhas' => $falhas];
    }

    public function atualizarPropriedade(array $prop, ?array $mercado = null, bool $force = false): bool
    {
        if (empty($prop['id']) || empty($prop['ativo'])) {
            return false;
        }
        if (! $force && ! $this->buscaVencida($prop)) {
            return false;
        }

        $mercado = $mercado ?: $this->buscarMercado();
        $row = $this->escolherPraca($mercado['rows'], $prop);
        if (! $row) {
            throw new RuntimeException('Nenhuma praca compativel encontrada para a propriedade.');
        }

        DB::table('propriedades')->where('id', (int) $prop['id'])->update([
            'regiao_cotacao' => $row['praca'],
            'cotacao_soja' => $row['valor'],
            'cotacao_soja_atualizada_em' => $mercado['data'],
            'cotacao_soja_fonte' => self::FONTE,
            'cotacao_soja_auto' => 1,
            'cotacao_soja_ultima_busca' => now(),
            'cotacao_soja_proxima_busca' => $this->proximaBusca(),
            'cotacao_soja_status' => 'atualizado',
            'cotacao_soja_erro' => null,
        ]);

        return true;
    }

    private function buscaVencida(array $prop): bool
    {
        $proxima = (string) ($prop['cotacao_soja_proxima_busca'] ?? '');

        return $proxima === '' || strtotime($proxima) <= time();
    }

    private function buscarMercado(): array
    {
        $html = $this->httpGet(self::URL);

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $cotacao = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' cotacao ')]")->item(0);
        if (! $cotacao) {
            throw new RuntimeException('Tabela de cotacao nao encontrada.');
        }

        $fechamentoNode = $xpath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' fechamento ')]", $cotacao)->item(0);
        $fechamentoTexto = $fechamentoNode ? trim($fechamentoNode->textContent) : '';
        if (! preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $fechamentoTexto, $mData)) {
            throw new RuntimeException('Data de fechamento nao encontrada.');
        }
        $data = "{$mData[3]}-{$mData[2]}-{$mData[1]}";

        $rows = [];
        foreach ($xpath->query(".//table[contains(concat(' ', normalize-space(@class), ' '), ' cot-fisicas ')]//tbody/tr", $cotacao) as $tr) {
            $tds = $xpath->query('./td', $tr);
            if ($tds->length < 2) {
                continue;
            }

            $praca = trim(preg_replace('/\s+/', ' ', $tds->item(0)->textContent));
            $valor = $this->valor($tds->item(1)->textContent);
            if ($praca === '' || $valor === null || $valor <= 0) {
                continue;
            }

            $info = $this->pracaInfo($praca);
            $rows[] = [
                'praca' => $praca,
                'valor' => $valor,
                'cidade' => $info['cidade'],
                'uf' => $info['uf'],
                'key' => $this->normalizado($info['cidade'].$info['uf']),
            ];
        }

        if (! $rows) {
            throw new RuntimeException('Nenhuma praca com cotacao valida foi encontrada.');
        }

        return ['data' => $data, 'rows' => $rows, 'url' => self::URL];
    }

    private function httpGet(string $url): string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 6, 'user_agent' => 'FarmFort/2.0 cotacao-soja (+http://localhost/farmfort)'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $html = @file_get_contents($url, false, $context);
        if ($html === false || trim($html) === '') {
            throw new RuntimeException('Nao foi possivel acessar a fonte de cotacao.');
        }

        return $html;
    }

    private function escolherPraca(array $rows, array $prop): ?array
    {
        $cidadeProp = trim((string) ($prop['municipio'] ?? ''));
        $ufProp = strtoupper(trim((string) ($prop['estado'] ?? '')));
        $lat = ($prop['latitude'] ?? null) !== null && ($prop['latitude'] ?? '') !== '' ? (float) $prop['latitude'] : null;
        $lng = ($prop['longitude'] ?? null) !== null && ($prop['longitude'] ?? '') !== '' ? (float) $prop['longitude'] : null;
        $cidadeNorm = $this->normalizado($cidadeProp);

        foreach ($rows as $row) {
            if ($cidadeNorm !== '' && $this->normalizado((string) $row['cidade']) === $cidadeNorm && (! $ufProp || $row['uf'] === $ufProp)) {
                return $row;
            }
        }

        $coords = $this->pracasCoords();
        if ($lat !== null && $lng !== null) {
            $best = null;
            foreach ($rows as $row) {
                if ($ufProp && $row['uf'] && $row['uf'] !== $ufProp) {
                    continue;
                }
                if (empty($coords[$row['key']])) {
                    continue;
                }
                [$rLat, $rLng] = $coords[$row['key']];
                $row['distancia_km'] = $this->distanciaKm($lat, $lng, $rLat, $rLng);
                if ($best === null || $row['distancia_km'] < $best['distancia_km']) {
                    $best = $row;
                }
            }
            if ($best) {
                return $best;
            }
        }

        $preferencias = [
            'GO' => ['rioverdego', 'jataigo', 'brasiliadf'],
            'MT' => ['sorrisomt', 'rondonopolismt', 'primaveradolestemt'],
            'MS' => ['campograndems', 'maracajums', 'saogabrieldooestems'],
            'PR' => ['paranaguapr', 'ubiratapr', 'castropr'],
            'SP' => ['santossp', 'candidomotasp'],
            'MG' => ['machadomg'],
            'BA' => ['luiseduardomagalhaesba'],
        ];
        foreach ($preferencias[$ufProp] ?? [] as $key) {
            foreach ($rows as $row) {
                if ($row['key'] === $key) {
                    return $row;
                }
            }
        }

        foreach ($rows as $row) {
            if (stripos($row['praca'], 'Porto Paranagua') !== false || stripos($row['praca'], 'Paranagua') !== false) {
                return $row;
            }
        }

        return $rows[0] ?? null;
    }

    private function marcarFalha(int $propriedadeId, Throwable $e): void
    {
        report($e);

        DB::table('propriedades')->where('id', $propriedadeId)->update([
            'cotacao_soja_ultima_busca' => now(),
            'cotacao_soja_proxima_busca' => now()->addHour(),
            'cotacao_soja_status' => 'erro',
            'cotacao_soja_erro' => substr($e->getMessage(), 0, 240),
        ]);
    }

    private function proximaBusca(): string
    {
        $base = now();
        $todayStart = $base->copy()->setTime(5, 0, 0);
        $todayEnd = $base->copy()->setTime(23, 0, 0);

        if ($base->lt($todayStart)) {
            return $todayStart->format('Y-m-d H:i:s');
        }
        if ($base->gte($todayEnd)) {
            return $base->copy()->addDay()->setTime(5, 0, 0)->format('Y-m-d H:i:s');
        }

        return $base->copy()->addHour()->minute(0)->second(0)->format('Y-m-d H:i:s');
    }

    private function valor(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '' || stripos($valor, 's/') !== false) {
            return null;
        }
        $valor = preg_replace('/[^0-9,.-]/', '', $valor);

        return $valor === '' ? null : (float) str_replace(['.', ','], ['', '.'], $valor);
    }

    private function pracaInfo(string $praca): array
    {
        if (preg_match('/^(.+?)\/([A-Z]{2})(?:\s|\(|$)/u', $praca, $m)) {
            return ['cidade' => trim($m[1]), 'uf' => strtoupper($m[2])];
        }
        if (stripos($praca, 'Paranagua') !== false || stripos($praca, 'Paranaguá') !== false) {
            return ['cidade' => 'Paranagua', 'uf' => 'PR'];
        }
        if (stripos($praca, 'Rio Grande') !== false) {
            return ['cidade' => 'Rio Grande', 'uf' => 'RS'];
        }
        if (stripos($praca, 'Santos') !== false) {
            return ['cidade' => 'Santos', 'uf' => 'SP'];
        }

        return ['cidade' => '', 'uf' => ''];
    }

    private function normalizado(string $texto): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($texto));
        $texto = strtolower($ascii !== false ? $ascii : $texto);

        return preg_replace('/[^a-z0-9]+/', '', $texto) ?: '';
    }

    private function pracasCoords(): array
    {
        return [
            'rioverdego' => [-17.7921, -50.9192],
            'jataigo' => [-17.8794, -51.7217],
            'brasiliadf' => [-15.7939, -47.8828],
            'rondonopolismt' => [-16.4673, -54.6372],
            'primaveradolestemt' => [-15.5606, -54.2973],
            'sorrisomt' => [-12.5452, -55.7219],
            'campograndems' => [-20.4697, -54.6201],
            'douradosms' => [-22.2231, -54.8120],
            'ubiratapr' => [-24.5393, -52.9865],
            'castropr' => [-24.7912, -50.0119],
            'paranaguapr' => [-25.5205, -48.5095],
            'machadomg' => [-21.6748, -45.9199],
            'candidomotasp' => [-22.7467, -50.3869],
            'luiseduardomagalhaesba' => [-12.0956, -45.7866],
        ];
    }

    private function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
