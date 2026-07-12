<?php

namespace App\Services;

use Illuminate\Support\Str;

class TalhaoRegiaoService
{
    public function __construct(private readonly MunicipioGeocoder $geocoder) {}

    public function calcular(iterable $talhoes, ?object $propriedade): array
    {
        $municipios = [];

        foreach ($talhoes as $talhao) {
            $area = (float) $this->valor($talhao, 'area');
            $latitude = $this->coordenada($talhao, 'centro_lat', 'lat');
            $longitude = $this->coordenada($talhao, 'centro_lng', 'lng');
            if ($area <= 0 || $latitude === null || $longitude === null) {
                continue;
            }

            $localizacao = $this->geocoder->localizar($latitude, $longitude);
            if (! $localizacao || empty($localizacao['municipio'])) {
                continue;
            }

            $municipio = trim((string) $localizacao['municipio']);
            $uf = strtoupper(trim((string) ($localizacao['uf'] ?? '')));
            $chave = Str::lower(Str::ascii($municipio.'|'.$uf));

            $municipios[$chave] ??= [
                'municipio' => $municipio,
                'uf' => $uf,
                'area' => 0.0,
                'maior_talhao' => 0.0,
                'latitude_ponderada' => 0.0,
                'longitude_ponderada' => 0.0,
                'fonte' => $localizacao['fonte'] ?? 'OpenStreetMap',
            ];
            $municipios[$chave]['area'] += $area;
            $municipios[$chave]['maior_talhao'] = max($municipios[$chave]['maior_talhao'], $area);
            $municipios[$chave]['latitude_ponderada'] += $latitude * $area;
            $municipios[$chave]['longitude_ponderada'] += $longitude * $area;
        }

        if ($municipios) {
            $municipios = array_values($municipios);
            usort($municipios, function (array $a, array $b): int {
                $porArea = $b['area'] <=> $a['area'];
                if ($porArea !== 0) {
                    return $porArea;
                }

                $porMaiorTalhao = $b['maior_talhao'] <=> $a['maior_talhao'];

                return $porMaiorTalhao !== 0
                    ? $porMaiorTalhao
                    : strcasecmp($a['municipio'], $b['municipio']);
            });

            $principal = $municipios[0];
            $latitude = $principal['latitude_ponderada'] / $principal['area'];
            $longitude = $principal['longitude_ponderada'] / $principal['area'];

            return [
                'regiao' => $principal['municipio'].($principal['uf'] !== '' ? '/'.$principal['uf'] : ''),
                'coordenadas' => number_format($latitude, 6, '.', '').', '.number_format($longitude, 6, '.', ''),
                'fonte' => $principal['fonte'],
                'area_ha' => round($principal['area'], 2),
                'municipios_analisados' => count($municipios),
            ];
        }

        return $this->fallback($propriedade);
    }

    private function fallback(?object $propriedade): array
    {
        $municipio = trim((string) ($propriedade->municipio ?? ''));
        $estado = trim((string) ($propriedade->estado ?? ''));
        $regiaoCotacao = trim((string) ($propriedade->regiao_cotacao ?? ''));
        $regiao = $municipio !== '' ? $municipio : ($regiaoCotacao !== '' ? $regiaoCotacao : 'Região da fazenda');

        if ($municipio !== '' && $estado !== '' && stripos($regiao, $estado) === false) {
            $regiao .= '/'.$estado;
        }

        return [
            'regiao' => $regiao,
            'coordenadas' => null,
            'fonte' => null,
            'area_ha' => null,
            'municipios_analisados' => 0,
        ];
    }

    private function coordenada(mixed $talhao, string $preferida, string $alternativa): ?float
    {
        $valor = $this->valor($talhao, $preferida);
        if (! is_numeric($valor)) {
            $valor = $this->valor($talhao, $alternativa);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function valor(mixed $talhao, string $chave): mixed
    {
        return is_array($talhao)
            ? ($talhao[$chave] ?? null)
            : ($talhao->{$chave} ?? null);
    }
}
