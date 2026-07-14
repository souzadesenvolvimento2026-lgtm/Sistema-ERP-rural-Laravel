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
        $this->assertStringNotContainsString('scrollIntoView', $javascript);
    }

    public function test_new_polygon_uses_a_floating_modal_instead_of_the_bottom_panel(): void
    {
        $view = $this->contents('resources/views/talhoes/partials/mapa-form.blade.php');

        $this->assertStringContainsString('id="mapPolygonFormModal"', $view);
        $this->assertStringContainsString('data-polygon-form', $view);
        $this->assertStringContainsString('data-polygon-modal-cancel', $view);
        $this->assertStringNotContainsString('id="polygonFormPanel"', $view);

        foreach (['public/js/talhao-mapa.js', 'public/js/talhao-mapa-modal.js'] as $script) {
            $javascript = $this->contents($script);

            $this->assertStringContainsString('function bindPolygonFormModal()', $javascript);
            $this->assertStringContainsString('openPolygonModal: polygonModal.open', $javascript);
            $this->assertStringContainsString('context.openPolygonModal(points, context.cancelDraft)', $javascript);
            $this->assertStringNotContainsString('polygonFormPanel', $javascript);
        }
    }

    public function test_new_talhao_button_opens_the_floating_create_modal(): void
    {
        $index = $this->contents('resources/views/talhoes/index.blade.php');
        $modal = $this->contents('resources/views/talhoes/partials/novo-talhao-modal.blade.php');
        $css = $this->contents('public/css/farmfort.css');

        $this->assertStringContainsString('data-bs-target="#talhaoCreateModal"', $index);
        $this->assertStringContainsString("@include('talhoes.partials.novo-talhao-modal')", $index);
        $this->assertStringContainsString('id="talhaoCreateModal"', $modal);
        $this->assertStringContainsString("route('talhoes.importar-geo')", $modal);
        $this->assertStringContainsString("route('talhoes.store')", $modal);
        $this->assertStringContainsString('form="talhaoImportModalForm"', $modal);
        $this->assertStringContainsString('form="talhaoManualModalForm"', $modal);
        $this->assertStringContainsString('.ff-talhao-create-modal .modal-content', $css);
    }

    public function test_map_adjustments_are_only_available_in_floating_modals(): void
    {
        $view = $this->contents('resources/views/talhoes/partials/mapa-form.blade.php');
        $css = $this->contents('public/css/farmfort.css');

        $this->assertStringContainsString('id="mapPivoModal"', $view);
        $this->assertStringContainsString('data-map-pivo-existing-section', $view);
        $this->assertStringContainsString('data-map-pivo-new-section', $view);
        $this->assertStringContainsString('data-map-pivo-create-form', $view);
        $this->assertStringContainsString('data-map-pivo-remove-section hidden', $view);
        $this->assertStringContainsString('data-map-modal-clear-exclusions', $view);
        $this->assertStringContainsString('data-exclusion-clear-form', $view);
        $this->assertStringNotContainsString('data-pivo-panel', $view);
        $this->assertStringNotContainsString('Ajustes do mapa', $view);
        $this->assertStringContainsString('.ff-map-hidden-forms { display: contents; }', $css);
        $this->assertStringContainsString('.ff-map-modal-section[hidden]', $css);
        $this->assertStringContainsString('.ff-map-pivo-measurements', $css);
        $this->assertStringContainsString('.ff-map-drawing-circle', $css);
        $this->assertStringNotContainsString('[data-pivo-panel]', $css);

        foreach (['public/js/talhao-mapa.js', 'public/js/talhao-mapa-modal.js'] as $script) {
            $javascript = $this->contents($script);

            $this->assertStringContainsString('function bindMapPivoModal(', $javascript);
            $this->assertStringContainsString("startPivo: (targetMode = 'existing') => startDraw('pivo', { targetMode })", $javascript);
            $this->assertStringContainsString("document.getElementById('btnDrawPivoTop')?.addEventListener('click', () => {", $javascript);
            $this->assertStringContainsString("startDraw('pivo', { targetMode: 'new' });", $javascript);
            $this->assertStringContainsString('function startPivoCircleDraw(', $javascript);
            $this->assertStringContainsString('L.circle(state.center', $javascript);
            $this->assertStringContainsString('function finishPivoCircleDraw(', $javascript);
            $this->assertStringContainsString('function pivoCircleStyle()', $javascript);
            $this->assertStringContainsString('function handlePivoCreated(', $javascript);
            $this->assertStringContainsString('openPivoModal: options.openPivoModal', $javascript);
            $this->assertStringContainsString("modalEl.dataset.pivoMode = mode", $javascript);
            $this->assertStringContainsString("document.querySelector('[data-exclusion-clear-form]')", $javascript);
            $this->assertStringNotContainsString('L.Draw.Circle', $javascript);
            $this->assertStringNotContainsString('pivoDrawHandler', $javascript);
            $this->assertStringNotContainsString('data-pivo-panel', $javascript);
            $this->assertStringNotContainsString('scrollIntoView', $javascript);
        }
    }

    public function test_saved_excluded_areas_are_rendered_as_visual_holes_in_the_field(): void
    {
        foreach (['public/js/talhao-mapa.js', 'public/js/talhao-mapa-modal.js'] as $script) {
            $javascript = $this->contents($script);

            $this->assertStringContainsString('preferCanvas: false', $javascript);
            $this->assertStringContainsString("fillRule: 'evenodd'", $javascript);
            $this->assertStringContainsString('layer = L.polygon([outer, ...holes], baseStyle).addTo(map);', $javascript);
            $this->assertStringContainsString('color: polygonColor', $javascript);
            $this->assertStringContainsString("fill: false", $javascript);
            $this->assertStringContainsString("className: 'ff-map-exclusion-outline'", $javascript);
            $this->assertStringNotContainsString("fillOpacity: 0.14", $javascript);
        }
    }

    public function test_unified_fields_can_be_edited_as_composite_geometry(): void
    {
        $service = $this->contents('app/Services/TalhaoService.php');

        $this->assertStringContainsString('function decodificarGeometriasDoFormulario(', $service);
        $this->assertStringContainsString('function preservarExclusoesAoRedesenhar(', $service);
        $this->assertStringContainsString('function calcularAreasGeometriasEstritas(', $service);
        $this->assertStringContainsString('$geometrias = $this->decodificarGeometriasDoFormulario', $service);
        $this->assertStringContainsString('$this->serializarGeometrias($geometriasAtualizadas)', $service);

        foreach (['public/js/talhao-mapa.js', 'public/js/talhao-mapa-modal.js'] as $script) {
            $javascript = $this->contents($script);

            $this->assertStringContainsString('function buildEditableLayer(talhao)', $javascript);
            $this->assertStringContainsString('enableLayerEditing(editableLayer)', $javascript);
            $this->assertStringContainsString('disableLayerEditing(editableLayer)', $javascript);
            $this->assertStringContainsString('editableGeometryPayload(editableLayer, selectedTalhao)', $javascript);
            $this->assertStringContainsString("type: 'MultiPolygon'", $javascript);
            $this->assertStringContainsString('const canEdit = hasPolygon && selectedTalhao?.can_edit_geometry === true;', $javascript);
            $this->assertStringNotContainsString('Este talhao unificado possui mais de um poligono', $javascript);
            $this->assertStringNotContainsString('!isComposite', $javascript);
        }
    }

    public function test_map_screen_uses_fixed_viewport_with_scroll_only_on_field_list(): void
    {
        $layout = $this->contents('resources/views/layouts/farmfort.blade.php');
        $view = $this->contents('resources/views/talhoes/mapa.blade.php');
        $css = $this->contents('public/css/farmfort.css');

        $this->assertStringContainsString('bodyClass', $layout);
        $this->assertStringContainsString("'bodyClass' => 'ff-map-fixed-body'", $view);
        $this->assertStringContainsString('html:has(body.ff-map-fixed-body)', $css);
        $this->assertStringContainsString('body.ff-map-fixed-body {', $css);
        $this->assertStringContainsString('body.ff-map-fixed-body .main', $css);
        $this->assertStringContainsString('grid-template-rows: auto minmax(0, 1fr);', $css);
        $this->assertStringContainsString('body.ff-map-fixed-body .ff-map-page', $css);
        $this->assertStringContainsString('grid-template-rows: auto auto minmax(0, 1fr);', $css);
        $this->assertStringContainsString('body.ff-map-fixed-body .ff-map-list-panel .map-list', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString('body.ff-map-fixed-body .ff-map-canvas', $css);
        $this->assertStringContainsString('height: 100% !important;', $css);
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
