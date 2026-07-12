<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MunicipioGeocoder
{
    private const ESTADOS_UF = [
        'acre' => 'AC',
        'alagoas' => 'AL',
        'amapa' => 'AP',
        'amazonas' => 'AM',
        'bahia' => 'BA',
        'ceara' => 'CE',
        'distrito federal' => 'DF',
        'espirito santo' => 'ES',
        'goias' => 'GO',
        'maranhao' => 'MA',
        'mato grosso' => 'MT',
        'mato grosso do sul' => 'MS',
        'minas gerais' => 'MG',
        'para' => 'PA',
        'paraiba' => 'PB',
        'parana' => 'PR',
        'pernambuco' => 'PE',
        'piaui' => 'PI',
        'rio de janeiro' => 'RJ',
        'rio grande do norte' => 'RN',
        'rio grande do sul' => 'RS',
        'rondonia' => 'RO',
        'roraima' => 'RR',
        'santa catarina' => 'SC',
        'sao paulo' => 'SP',
        'sergipe' => 'SE',
        'tocantins' => 'TO',
    ];

    public function localizar(float $latitude, float $longitude): ?array
    {
        if (! (bool) config('services.nominatim.enabled', true)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $precision = min(7, max(4, (int) config('services.nominatim.cache_precision', 5)));
        $cacheKey = sprintf(
            'farmfort:municipio:v1:%.*f:%.*f',
            $precision,
            $latitude,
            $precision,
            $longitude
        );
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && ! empty($cached['municipio'])) {
            return $cached;
        }
        if ($cached === 'nao_localizado') {
            return null;
        }

        $localizacao = $this->consultar($latitude, $longitude);
        if ($localizacao) {
            Cache::put(
                $cacheKey,
                $localizacao,
                now()->addDays(max(1, (int) config('services.nominatim.cache_days', 180)))
            );

            return $localizacao;
        }

        Cache::put($cacheKey, 'nao_localizado', now()->addMinutes(30));

        return null;
    }

    private function consultar(float $latitude, float $longitude): ?array
    {
        $consulta = function () use ($latitude, $longitude): ?array {
            $this->aguardarLimitePublico();

            $endpoint = rtrim((string) config('services.nominatim.url'), '/').'/reverse';
            $query = [
                'format' => 'jsonv2',
                'lat' => number_format($latitude, 7, '.', ''),
                'lon' => number_format($longitude, 7, '.', ''),
                'zoom' => 10,
                'layer' => 'address',
                'addressdetails' => 1,
            ];
            $email = trim((string) config('services.nominatim.email'));
            if ($email !== '') {
                $query['email'] = $email;
            }

            $response = Http::withHeaders([
                'User-Agent' => (string) config('services.nominatim.user_agent'),
                'Referer' => (string) config('app.url'),
                'Accept-Language' => 'pt-BR,pt;q=0.9',
            ])
                ->acceptJson()
                ->connectTimeout((int) config('services.nominatim.connect_timeout', 3))
                ->timeout((int) config('services.nominatim.timeout', 8))
                ->get($endpoint, $query);

            if (! $response->successful()) {
                return null;
            }

            return $this->normalizarResposta((array) $response->json());
        };

        try {
            if ((int) config('services.nominatim.rate_limit_ms', 1100) <= 0) {
                return $consulta();
            }

            return Cache::lock('farmfort:nominatim:request', 20)->block(20, $consulta);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function aguardarLimitePublico(): void
    {
        $intervaloMs = max(0, (int) config('services.nominatim.rate_limit_ms', 1100));
        if ($intervaloMs === 0) {
            return;
        }

        $agora = microtime(true);
        $ultima = (float) Cache::get('farmfort:nominatim:last_request_at', 0);
        $restante = ($intervaloMs / 1000) - ($agora - $ultima);
        if ($restante > 0) {
            usleep((int) ceil($restante * 1_000_000));
        }

        Cache::put('farmfort:nominatim:last_request_at', microtime(true), now()->addDay());
    }

    private function normalizarResposta(array $resposta): ?array
    {
        $endereco = is_array($resposta['address'] ?? null) ? $resposta['address'] : [];
        $municipio = $this->primeiroTexto($endereco, [
            'municipality',
            'city',
            'town',
            'village',
            'county',
        ]);
        if ($municipio === '') {
            return null;
        }

        $estado = $this->primeiroTexto($endereco, ['state', 'region']);
        $codigoEstado = strtoupper(trim((string) ($endereco['ISO3166-2-lvl4'] ?? '')));
        $uf = preg_match('/^BR-([A-Z]{2})$/', $codigoEstado, $matches)
            ? $matches[1]
            : (self::ESTADOS_UF[Str::lower(Str::ascii($estado))] ?? '');

        return [
            'municipio' => $municipio,
            'uf' => $uf,
            'estado' => $estado,
            'fonte' => 'OpenStreetMap',
        ];
    }

    private function primeiroTexto(array $dados, array $chaves): string
    {
        foreach ($chaves as $chave) {
            $valor = trim(preg_replace('/\s+/u', ' ', (string) ($dados[$chave] ?? '')) ?? '');
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }
}
