<?php

namespace Tests\Unit;

use App\Services\MunicipioGeocoder;
use App\Services\TalhaoRegiaoService;
use PHPUnit\Framework\TestCase;

class TalhaoRegiaoServiceTest extends TestCase
{
    public function test_escolhe_o_municipio_com_maior_soma_de_hectares(): void
    {
        $geocoder = $this->createMock(MunicipioGeocoder::class);
        $geocoder->method('localizar')->willReturnCallback(
            fn (float $latitude): array => $latitude < -16.7
                ? ['municipio' => 'São João da Paraúna', 'uf' => 'GO', 'fonte' => 'OpenStreetMap']
                : ['municipio' => 'Rio Verde', 'uf' => 'GO', 'fonte' => 'OpenStreetMap']
        );
        $service = new TalhaoRegiaoService($geocoder);

        $resultado = $service->calcular([
            ['area' => 20, 'centro_lat' => -16.75, 'centro_lng' => -50.36],
            ['area' => 15, 'centro_lat' => -16.76, 'centro_lng' => -50.37],
            ['area' => 30, 'centro_lat' => -16.68, 'centro_lng' => -50.90],
        ], (object) [
            'municipio' => 'Município cadastrado',
            'estado' => 'GO',
            'regiao_cotacao' => 'Outra praça',
        ]);

        $this->assertSame('São João da Paraúna/GO', $resultado['regiao']);
        $this->assertSame(35.0, $resultado['area_ha']);
        $this->assertSame(2, $resultado['municipios_analisados']);
        $this->assertSame('OpenStreetMap', $resultado['fonte']);
    }

    public function test_usa_municipio_da_propriedade_quando_localizacao_nao_estiver_disponivel(): void
    {
        $geocoder = $this->createMock(MunicipioGeocoder::class);
        $geocoder->method('localizar')->willReturn(null);
        $service = new TalhaoRegiaoService($geocoder);

        $resultado = $service->calcular([
            ['area' => 40, 'centro_lat' => -16.75, 'centro_lng' => -50.36],
        ], (object) [
            'municipio' => 'São João da Paraúna',
            'estado' => 'GO',
            'regiao_cotacao' => 'Rio Verde/GO (Comigo)',
        ]);

        $this->assertSame('São João da Paraúna/GO', $resultado['regiao']);
        $this->assertNull($resultado['fonte']);
        $this->assertSame(0, $resultado['municipios_analisados']);
    }
}
