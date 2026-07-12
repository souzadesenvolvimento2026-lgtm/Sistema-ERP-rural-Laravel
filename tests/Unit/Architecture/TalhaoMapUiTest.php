<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class TalhaoMapUiTest extends TestCase
{
    public function test_exclusion_action_is_exclusive_to_the_contextual_map_toolbar(): void
    {
        $view = $this->contents('resources/views/talhoes/mapa.blade.php');
        $javascript = $this->contents('public/js/talhao-mapa.js');

        $this->assertStringNotContainsString('btnDrawExclusionTop', $view);
        $this->assertStringNotContainsString('data-map-draw-exclusion', $view);
        $this->assertStringContainsString("createMapTool(container, 'exclusion'", $javascript);
        $this->assertStringContainsString("'Adicionar área excluída'", $javascript);
    }

    public function test_map_tools_are_capability_driven_reversible_and_safely_serialized(): void
    {
        $view = $this->contents('resources/views/talhoes/mapa.blade.php');
        $javascript = $this->contents('public/js/talhao-mapa.js');

        $this->assertStringContainsString('Js::from($talhoes)', $view);
        $this->assertStringNotContainsString('{!! $talhoesJson !!}', $view);
        $this->assertStringContainsString('selectedTalhao?.can_edit_geometry === true', $javascript);
        $this->assertStringContainsString('selectedTalhao?.can_add_exclusion === true', $javascript);
        $this->assertStringContainsString("createMapTool(container, 'revert'", $javascript);
        $this->assertStringContainsString('function revertCurrent(', $javascript);
    }

    public function test_contextual_toolbar_starts_transparent_and_only_exposes_eligible_actions(): void
    {
        $css = $this->contents('public/css/farmfort.css');
        $javascript = $this->contents('public/js/talhao-mapa.js');

        $this->assertMatchesRegularExpression(
            '/#talhaoMap \.ff-map-context-toolbar\s*\{[^}]*opacity:\s*0;[^}]*visibility:\s*hidden;[^}]*pointer-events:\s*none;/s',
            $css,
        );
        $this->assertStringContainsString('#talhaoMap .ff-map-context-toolbar.is-visible', $css);
        $this->assertStringContainsString("options: { position: 'topleft' }", $javascript);
        $this->assertStringContainsString('edit: !busy && canEdit', $javascript);
        $this->assertStringContainsString('exclusion: !busy && canExclude', $javascript);
        $this->assertStringContainsString('save: isEditing', $javascript);
        $this->assertStringContainsString('revert: busy', $javascript);
    }

    private function contents(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, "Não foi possível ler {$relativePath}.");

        return $contents;
    }
}
