<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_login_uses_rate_limiter(): void
    {
        $controller = $this->contents('app/Http/Controllers/AuthSessionController.php');

        $this->assertStringContainsString('use Illuminate\Support\Facades\RateLimiter;', $controller);
        $this->assertStringContainsString('RateLimiter::tooManyAttempts', $controller);
        $this->assertStringContainsString('RateLimiter::hit', $controller);
        $this->assertStringContainsString('RateLimiter::clear', $controller);
        $this->assertStringContainsString('LOGIN_MAX_ATTEMPTS = 5', $controller);
    }

    public function test_security_headers_middleware_is_registered(): void
    {
        $bootstrap = $this->contents('bootstrap/app.php');
        $middleware = $this->contents('app/Http/Middleware/SecurityHeaders.php');

        $this->assertStringContainsString('SecurityHeaders::class', $bootstrap);
        $this->assertStringContainsString('X-Content-Type-Options', $middleware);
        $this->assertStringContainsString('X-Frame-Options', $middleware);
        $this->assertStringContainsString('Referrer-Policy', $middleware);
        $this->assertStringContainsString('Permissions-Policy', $middleware);
        $this->assertStringContainsString('Strict-Transport-Security', $middleware);
    }

    public function test_geo_uploads_are_hardened_in_backend(): void
    {
        $service = $this->contents('app/Services/TalhaoService.php');
        $propertyController = $this->contents('app/Http/Controllers/PropriedadeController.php');

        $this->assertStringContainsString('GEO_UPLOAD_EXTENSIONS', $service);
        $this->assertStringContainsString('GEO_UPLOAD_MAX_BYTES', $service);
        $this->assertStringContainsString('validarArquivoGeo', $service);
        $this->assertStringContainsString('zipGeoEntryPermitida', $service);
        $this->assertStringContainsString('GEO_ZIP_MAX_ENTRIES', $service);
        $this->assertStringContainsString("'kml_area' => ['nullable', 'file', 'mimes:kml,kmz,shp,zip', 'max:20480']", $propertyController);
    }

    public function test_property_user_isolation_and_permission_checks_are_explicit(): void
    {
        $properties = $this->contents('app/Services/PropriedadeService.php');
        $financeController = $this->contents('app/Http/Controllers/ContaBancariaController.php');
        $financeService = $this->contents('app/Services/ContaBancariaService.php');
        $chat = $this->contents('app/Services/ChatInternoService.php');
        $docs = $this->contents('app/Services/DocumentoService.php');

        $this->assertStringContainsString('impedirUsuarioVinculadoEmOutraPropriedade', $properties);
        $this->assertStringContainsString('removerUsuarioDaPropriedade', $properties);
        $this->assertStringContainsString('validarLimiteUsuarios', $properties);
        $this->assertStringContainsString('authorizeManageFinance', $financeController);
        $this->assertStringContainsString("where('tf.propriedade_id', \$propriedadeId)", $financeService);
        $this->assertStringContainsString("where('up.propriedade_id', '=', \$propriedadeId)", $chat);
        $this->assertStringContainsString('str_starts_with($path, $base)', $chat);
        $this->assertStringContainsString('str_starts_with($path, $base)', $docs);
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents, "Nao foi possivel ler {$path}.");

        return $contents;
    }
}
