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

    public function test_list_edit_action_opens_the_floating_modal_without_scrolling_to_bottom_forms(): void
    {
        $mapView = $this->contents('resources/views/talhoes/mapa.blade.php');
        $view = $this->contents('resources/views/talhoes/partials/mapa-form.blade.php');
        $javascript = $this->contents('public/js/talhao-mapa-modal.js');

        $this->assertStringContainsString('talhao-mapa-modal.js', $mapView);
        $this->assertStringContainsString('id="mapTalhaoEditModal"', $view);
        $this->assertStringContainsString('data-map-details-form', $view);
        $this->assertStringContainsString('function bindTalhaoEditModal(', $javascript);
        $this->assertStringContainsString('openEditModal?.(talhaoId)', $javascript);
        $this->assertStringNotContainsString("document.querySelector('.ff-map-hidden-forms')?.scrollIntoView", $javascript);
    }

    public function test_map_forms_use_relative_actions_to_keep_the_current_protocol(): void
    {
        $view = $this->contents('resources/views/talhoes/partials/mapa-form.blade.php');
        $controller = $this->contents('app/Http/Controllers/TalhaoController.php');

        $this->assertStringContainsString("route('talhoes.mapa.store', [], false)", $view);
        $this->assertStringContainsString("route('talhoes.mapa.pivo.create', [], false)", $view);
        $this->assertStringContainsString('data-map-action-template="/talhoes/__ID__/mapa/dados"', $view);
        $this->assertStringContainsString('data-map-action-template="/talhoes/__ID__/mapa/pivo"', $view);
        $this->assertStringContainsString('data-map-action-template="/talhoes/__ID__/mapa/exclusoes"', $view);
        $this->assertStringNotContainsString("url('/talhoes/", $view);
        $this->assertStringContainsString("setTargetUrl('/talhoes/mapa')", $controller);
        $this->assertStringNotContainsString("redirect()->route('talhoes.mapa')", $controller);
    }

    public function test_canceling_map_editing_reverts_pending_modal_and_exclusion_drafts(): void
    {
        $view = $this->contents('resources/views/talhoes/partials/mapa-form.blade.php');

        $this->assertStringContainsString('data-map-modal-cancel', $view);

        foreach (['public/js/talhao-mapa.js', 'public/js/talhao-mapa-modal.js'] as $script) {
            $javascript = $this->contents($script);

            $this->assertStringContainsString('cancelDraft: () => revertCurrent(false)', $javascript);
            $this->assertStringContainsString('cancelEdits: () => revertCurrent(false)', $javascript);
            $this->assertStringContainsString('const cancelModalEditing = () => {', $javascript);
            $this->assertStringContainsString('mapTools.cancelEdits?.();', $javascript);
            $this->assertStringContainsString('context.cancelDraft?.();', $javascript);
            $this->assertStringNotContainsString('textarea.focus();', $javascript);
        }
    }

    private function contents(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, "Não foi possível ler {$relativePath}.");

        return $contents;
    }
}
