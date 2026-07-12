<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class BladeBoundaryTest extends TestCase
{
    public function test_blade_templates_do_not_access_the_persistence_or_service_layers(): void
    {
        $viewsPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
        $patterns = [
            'facade DB' => '/(?<![A-Za-z0-9_])(?:\\\\?Illuminate\\\\Support\\\\Facades\\\\)?DB\s*::/',
            'Model da aplicação' => '/\\\\?App\\\\Models\\\\[A-Za-z_][A-Za-z0-9_]*/',
            'Service Locator' => '/\bapp\s*\(\s*(?:\\\\?App\\\\Services\\\\)?[A-Za-z_][A-Za-z0-9_]*Service::class/',
            'decisao de workflow' => '/@(?:if|unless)\s*\([^\r\n]*(?:status(?:_key)?|aprovacao_key|plano|finalizado|total_cargas)/i',
            'agrupamento de status' => '/\bin_array\s*\([^\r\n]*(?:status(?:_key)?|aprovacao_key)/i',
        ];
        $violations = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            foreach ($patterns as $boundary => $pattern) {
                if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
                    continue;
                }

                $offset = $match[0][1];
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $relativePath = str_replace(dirname(__DIR__, 3).DIRECTORY_SEPARATOR, '', $file->getPathname());
                $violations[] = "{$relativePath}:{$line} acessa {$boundary}";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "As Blades devem receber dados prontos de Services/View Composers:\n".implode("\n", $violations),
        );
    }
}
