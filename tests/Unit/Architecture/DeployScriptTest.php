<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class DeployScriptTest extends TestCase
{
    public function test_update_development_confirms_remote_commit_and_keeps_sudo_alive(): void
    {
        $script = $this->contents('deploy/update-development.sh');

        $this->assertStringContainsString('git ls-remote origin "refs/heads/$BRANCH"', $script);
        $this->assertStringContainsString('REMOTE_COMMIT', $script);
        $this->assertStringContainsString('LOCAL_COMMIT="$(git rev-parse HEAD)"', $script);
        $this->assertStringContainsString('TRACKING_COMMIT="$(git rev-parse "origin/$BRANCH")"', $script);
        $this->assertStringContainsString('Servidor nao chegou ao commit mais recente do GitHub.', $script);
        $this->assertStringContainsString('sudo -v', $script);
        $this->assertStringContainsString('SUDO_KEEPALIVE_PID="$!"', $script);
        $this->assertStringContainsString('./deploy/development.sh "$DEPLOY_REF"', $script);
    }

    public function test_development_deploy_records_the_published_commit(): void
    {
        $script = $this->contents('deploy/development.sh');

        $this->assertStringContainsString('TARGET_COMMIT="$(git rev-parse --verify "$DEPLOY_REF^{commit}")"', $script);
        $this->assertStringContainsString('Commit alvo: $TARGET_COMMIT', $script);
        $this->assertStringContainsString('$APP_DIR/.deploy-commit', $script);
        $this->assertStringContainsString('Commit publicado: $PUBLISHED_COMMIT', $script);
        $this->assertStringContainsString('Deploy copiou arquivos, mas o marcador publicado nao confere.', $script);
    }

    private function contents(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, "Nao foi possivel ler {$relativePath}.");

        return $contents;
    }
}
