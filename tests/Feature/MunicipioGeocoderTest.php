<?php

namespace Tests\Feature;

use App\Services\MunicipioGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MunicipioGeocoderTest extends TestCase
{
    public function test_localiza_municipio_brasileiro_e_reaproveita_cache(): void
    {
        config()->set('services.nominatim.enabled', true);
        config()->set('services.nominatim.url', 'https://nominatim.test');
        config()->set('services.nominatim.rate_limit_ms', 0);
        Cache::flush();

        Http::fake([
            'https://nominatim.test/reverse*' => Http::response([
                'address' => [
                    'municipality' => 'São João da Paraúna',
                    'state' => 'Goiás',
                    'ISO3166-2-lvl4' => 'BR-GO',
                    'country_code' => 'br',
                ],
            ]),
        ]);

        $geocoder = app(MunicipioGeocoder::class);
        $primeira = $geocoder->localizar(-16.755719, -50.365338);
        $segunda = $geocoder->localizar(-16.755719, -50.365338);

        $this->assertSame('São João da Paraúna', $primeira['municipio']);
        $this->assertSame('GO', $primeira['uf']);
        $this->assertSame($primeira, $segunda);
        Http::assertSentCount(1);
    }
}
