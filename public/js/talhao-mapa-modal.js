window.initTalhaoMapa = function initTalhaoMapa(config) {
    translateLeafletDraw();
    const MAP_MAX_ZOOM = 17;
    const MAP_MAX_NATIVE_ZOOM = 16;
    const map = L.map('talhaoMap', {
        zoomControl: true,
        attributionControl: true,
        preferCanvas: false,
        maxZoom: MAP_MAX_ZOOM,
        zoomSnap: 0.25,
        zoomDelta: 0.5,
        wheelPxPerZoomLevel: 90,
    }).setView(config.centro, 14);
    const draftItems = new L.FeatureGroup();
    const talhaoLayers = new Map();
    const labelsLayer = L.layerGroup();
    const bounds = L.latLngBounds();
    const talhoes = config.talhoes || [];

    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: MAP_MAX_ZOOM,
        maxNativeZoom: MAP_MAX_NATIVE_ZOOM,
        keepBuffer: 4,
        updateWhenZooming: false,
        attribution: 'Leaflet | Esri, Maxar, Earthstar Geographics, Esri',
    }).addTo(map);

    const roads = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: MAP_MAX_ZOOM,
        maxNativeZoom: 19,
        keepBuffer: 4,
        updateWhenZooming: false,
        attribution: '&copy; OpenStreetMap',
    });

    labelsLayer.addTo(map);
    map.addLayer(draftItems);
    renderTalhoes(map, talhoes, talhaoLayers, labelsLayer, bounds);

    L.control.layers(
        { Satélite: satellite, Ruas: roads },
        { Rótulos: labelsLayer },
        { collapsed: false, position: 'topright' }
    ).addTo(map);

    map.on('zoomend', () => {
        if (map.getZoom() > MAP_MAX_ZOOM) map.setZoom(MAP_MAX_ZOOM);
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.18), { maxZoom: MAP_MAX_ZOOM });
    }

    const polygonModal = bindPolygonFormModal();
    const pivoModal = bindMapPivoModal(talhoes);
    const mapTools = configureDraw(map, draftItems, talhoes, talhaoLayers, {
        openPolygonModal: polygonModal.open,
        openPivoModal: pivoModal.open,
    });
    bindPolygonTargetSelector(talhoes);
    bindMapActionForms();
    const editModal = bindTalhaoEditModal(talhoes, mapTools, pivoModal.open);
    bindMapList(map, talhaoLayers, mapTools.selectTalhao, editModal.open);

    setTimeout(() => map.invalidateSize(), 120);
};

function translateLeafletDraw() {
    if (!window.L || !L.drawLocal) return;

    L.drawLocal.draw.toolbar.actions.title = 'Cancelar desenho';
    L.drawLocal.draw.toolbar.actions.text = 'Cancelar';
    L.drawLocal.draw.toolbar.finish.title = 'Finalizar desenho';
    L.drawLocal.draw.toolbar.finish.text = 'Finalizar';
    L.drawLocal.draw.toolbar.undo.title = 'Remover último ponto';
    L.drawLocal.draw.toolbar.undo.text = 'Remover último ponto';
    L.drawLocal.draw.toolbar.buttons.polygon = 'Desenhar novo talhão';
    if (L.drawLocal.draw.toolbar.buttons.circle !== undefined) {
        L.drawLocal.draw.toolbar.buttons.circle = 'Desenhar pivô circular';
    }
    L.drawLocal.draw.handlers.polygon.tooltip.start = 'Clique para iniciar o polígono.';
    L.drawLocal.draw.handlers.polygon.tooltip.cont = 'Clique para continuar o polígono.';
    L.drawLocal.draw.handlers.polygon.tooltip.end = 'Clique no primeiro ponto para finalizar.';
    if (L.drawLocal.draw.handlers.circle?.tooltip) {
        L.drawLocal.draw.handlers.circle.tooltip.start = 'Clique e arraste para desenhar o círculo do pivô.';
    }
    L.drawLocal.draw.handlers.polyline.error = '<strong>Erro:</strong> as linhas não podem se cruzar.';
}

function renderTalhoes(map, talhoes, talhaoLayers, labelsLayer, bounds) {
    const colorByType = { polygon: '#74c69d', line: '#ffd166', point: '#2d9d5c', manual: '#2d9d5c' };

    talhoes.forEach((talhao) => {
        let layer = null;
        const polygonColor = colorByType.polygon;
        const geometrias = Array.isArray(talhao.geometries) && talhao.geometries.length
            ? talhao.geometries
            : (
                talhao.points && talhao.points.length >= 3
                    ? [{ points: talhao.points, exclusions: talhao.exclusoes || [] }]
                    : []
            );

        if (geometrias.length) {
            const baseStyle = {
                color: polygonColor,
                weight: 2,
                opacity: 0.95,
                fillColor: '#2d9d5c',
                fillOpacity: 0.28,
                fillRule: 'evenodd',
            };
            const parts = [];

            geometrias.forEach((geometria) => {
                if (!Array.isArray(geometria.points) || geometria.points.length < 3) return;

                const outer = geometria.points.map((point) => [point.lat, point.lng]);
                const holes = (geometria.exclusions || [])
                    .filter((ring) => Array.isArray(ring) && ring.length >= 3)
                    .map((ring) => ring.map((point) => [point.lat, point.lng]));
                const polygonPart = L.polygon([outer, ...holes], baseStyle);
                polygonPart.ffBaseStyle = baseStyle;
                parts.push(polygonPart);
            });

            if (parts.length) {
                layer = L.featureGroup(parts).addTo(map);
                layer.ffBaseStyle = baseStyle;
                layer.bindPopup(popupHtml(talhao));
                const layerBounds = layer.getBounds();
                layerBounds.isValid() && bounds.extend(layerBounds);

                const labelPoint = talhao.lat && talhao.lng
                    ? L.latLng(talhao.lat, talhao.lng)
                    : layerBounds.getCenter();
                L.marker(labelPoint, {
                    opacity: 0,
                    interactive: false,
                    keyboard: false,
                }).bindTooltip(labelHtml(talhao.nome, polygonColor), {
                    permanent: true,
                    direction: 'center',
                    className: 'ff-talhao-map-label',
                    opacity: 1,
                }).addTo(labelsLayer);
            }
        } else if (talhao.lat && talhao.lng) {
            const baseStyle = {
                radius: 8,
                color: '#ffffff',
                fillColor: colorByType.point,
                fillOpacity: 0.95,
                weight: 2,
            };
            layer = L.circleMarker([talhao.lat, talhao.lng], baseStyle)
                .addTo(map)
                .bindPopup(popupHtml(talhao));
            layer.ffBaseStyle = baseStyle;
            bounds.extend(layer.getLatLng());
        }

        if (layer) talhaoLayers.set(String(talhao.id), layer);

        if (talhao.pivo_ativo && talhao.pivo_lat && talhao.pivo_lng && talhao.pivo_raio_m) {
            L.circle([talhao.pivo_lat, talhao.pivo_lng], {
                radius: Number(talhao.pivo_raio_m),
                color: '#8edfd0',
                weight: 2,
                fillColor: '#8edfd0',
                fillOpacity: 0.18,
            }).addTo(map);
        }
    });
}

function configureDraw(map, draftItems, talhoes, talhaoLayers, options = {}) {
    const talhoesById = new Map(talhoes.map((talhao) => [String(talhao.id), talhao]));
    let selectedTalhao = null;
    let selectedLayer = null;
    let editableLayer = null;
    let drawMode = 'talhao';
    let exclusionTalhaoId = null;
    let pivoTalhaoId = null;
    let activeDrawHandler = null;
    let activeDrawing = false;
    let hasDraft = false;
    let isEditing = false;
    let pivoCircleState = null;

    const drawControl = new L.Control.Draw({
        edit: false,
        draw: {
            polygon: {
                allowIntersection: false,
                showArea: true,
                shapeOptions: { color: '#05a784', fillColor: '#23885f', fillOpacity: 0.28 },
            },
            polyline: false,
            rectangle: false,
            circle: false,
            circlemarker: false,
            marker: false,
        },
    });
    map.addControl(drawControl);

    const defaultPolygonHandler = drawControl._toolbars?.draw?._modes?.polygon?.handler || null;
    const polygonDrawHandler = L.Draw && L.Draw.Polygon
        ? new L.Draw.Polygon(map, {
            allowIntersection: false,
            showArea: true,
            shapeOptions: { color: '#05a784', fillColor: '#23885f', fillOpacity: 0.28 },
        })
        : null;
    const exclusionDrawHandler = L.Draw && L.Draw.Polygon
        ? new L.Draw.Polygon(map, {
            allowIntersection: false,
            showArea: true,
            shapeOptions: { color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.18, dashArray: '6 5' },
        })
        : null;
    const selectionStatus = createSelectionStatusControl(map);
    const contextToolbar = createMapContextToolbar(map, {
        edit: startEdit,
        exclusion: () => startDraw('exclusao'),
        save: saveEdit,
        revert: () => revertCurrent(true),
    });

    const defaultPolygonButton = document.querySelector('#talhaoMap .leaflet-draw-draw-polygon');
    if (defaultPolygonButton) {
        defaultPolygonButton.classList.add('ff-leaflet-draw-polygon');
        defaultPolygonButton.innerHTML = '<i class="bi bi-bounding-box" aria-hidden="true"></i>';
        defaultPolygonButton.setAttribute('aria-label', 'Desenhar novo talhão');
        defaultPolygonButton.addEventListener('click', (event) => {
            if (!discardPendingChanges()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            drawMode = 'talhao';
            exclusionTalhaoId = null;
            activeDrawHandler = defaultPolygonHandler;
        }, true);
    }

    talhaoLayers.forEach((layer, talhaoId) => {
        layer.on('click', () => selectTalhao(talhaoId, { openPopup: false }));
    });

    function selectTalhao(talhaoId, options = {}) {
        const id = String(talhaoId || '');
        const talhao = talhoesById.get(id);
        const layer = talhaoLayers.get(id) || null;
        if (!talhao) return false;

        if (selectedTalhao && String(selectedTalhao.id) !== id && !discardPendingChanges()) {
            return false;
        }

        if (selectedLayer && selectedLayer !== layer) {
            applySelectedStyle(selectedLayer, false);
        }

        selectedTalhao = talhao;
        selectedLayer = layer;
        if (selectedLayer) applySelectedStyle(selectedLayer, true);
        markSelectedListItem(id);
        synchronizeMapForms(id);
        const hasPolygon = Array.isArray(selectedTalhao.points) && selectedTalhao.points.length >= 3;
        const selectionMessage = !hasPolygon && !(selectedTalhao.block_reason || selectedTalhao.map_mutation_block_reason)
            ? 'Este talhão ainda não possui polígono. Use Desenhar novo talhão para georreferenciá-lo.'
            : null;
        selectionStatus.update(selectedTalhao, selectionMessage);
        syncToolbar();

        if (options.openPopup !== false) layer?.openPopup?.();
        return true;
    }

    function startDraw(mode, options = {}) {
        if (mode === 'exclusao') {
            if (!selectedTalhao) {
                alert('Selecione um talhão no mapa ou na lista antes de adicionar uma área excluída.');
                return;
            }

            if (!selectedTalhao.can_add_exclusion) {
                alert(selectedTalhao.map_mutation_block_reason || selectedTalhao.block_reason || 'Este talhão não pode receber alterações no momento.');
                return;
            }
        }

        if (mode === 'pivo') {
            startPivoCircleDraw(options);
            return;
        }

        if (!discardPendingChanges()) return;

        drawMode = mode;
        exclusionTalhaoId = mode === 'exclusao' ? String(selectedTalhao.id) : null;
        pivoTalhaoId = null;
        activeDrawHandler = mode === 'exclusao'
            ? exclusionDrawHandler
            : polygonDrawHandler;

        if (activeDrawHandler) {
            activeDrawHandler.enable();
            return;
        }

        defaultPolygonButton?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    }

    function startPivoCircleDraw(options = {}) {
        if (!discardPendingChanges()) return;

        pivoTalhaoId = options.targetMode === 'existing' && selectedTalhao
            ? String(selectedTalhao.id)
            : null;
        activeDrawHandler?.disable?.();
        defaultPolygonHandler?.disable?.();
        cleanupPivoCircleDraw(false);

        drawMode = 'pivo';
        activeDrawing = true;
        hasDraft = false;
        draftItems.clearLayers();
        map.dragging?.disable?.();
        map.getContainer()?.classList.add('ff-map-drawing-circle');
        selectionStatus.update(
            selectedTalhao,
            'Clique, arraste e solte para desenhar o círculo do pivô.',
        );

        const state = {
            center: null,
            layer: null,
            started: false,
            options,
            onMouseDown(event) {
                const button = event.originalEvent?.button ?? 0;
                if (button !== 0) return;

                state.started = true;
                state.center = event.latlng;
                state.layer = L.circle(state.center, {
                    ...pivoCircleStyle(),
                    radius: 1,
                }).addTo(draftItems);
                if (event.originalEvent) {
                    L.DomEvent.preventDefault(event.originalEvent);
                }
            },
            onMouseMove(event) {
                if (!state.started || !state.layer || !state.center) return;

                state.layer.setRadius(Math.max(1, map.distance(state.center, event.latlng)));
            },
            onMouseUp(event) {
                if (!state.started || !state.layer || !state.center) return;

                const radius = Math.max(1, map.distance(state.center, event.latlng));
                state.layer.setRadius(radius);

                if (radius < 5) {
                    draftItems.removeLayer(state.layer);
                    state.layer = null;
                    state.center = null;
                    state.started = false;
                    selectionStatus.update(selectedTalhao, 'Arraste para abrir o raio do pivô.');
                    return;
                }

                finishPivoCircleDraw(state.layer, state.options);
            },
            onKeyDown(event) {
                if (event.key === 'Escape') {
                    revertCurrent(false);
                }
            },
        };

        pivoCircleState = state;
        map.on('mousedown', state.onMouseDown);
        map.on('mousemove', state.onMouseMove);
        map.on('mouseup', state.onMouseUp);
        document.addEventListener('keydown', state.onKeyDown);
        syncToolbar();
    }

    function finishPivoCircleDraw(layer, options = {}) {
        cleanupPivoCircleDraw(true);
        draftItems.clearLayers();
        draftItems.addLayer(layer);
        layer.setStyle?.(pivoCircleStyle());
        hasDraft = true;
        activeDrawing = false;

        handlePivoCreated(layer, {
            mode: 'pivo',
            pivoTalhaoId,
            talhoes,
            cancelDraft: () => revertCurrent(false),
            openPivoModal: options.openPivoModal,
        });

        drawMode = 'talhao';
        pivoTalhaoId = null;
        syncToolbar();
    }

    function cleanupPivoCircleDraw(keepLayer = false) {
        if (!pivoCircleState) return;

        map.off('mousedown', pivoCircleState.onMouseDown);
        map.off('mousemove', pivoCircleState.onMouseMove);
        map.off('mouseup', pivoCircleState.onMouseUp);
        document.removeEventListener('keydown', pivoCircleState.onKeyDown);
        map.dragging?.enable?.();
        map.getContainer()?.classList.remove('ff-map-drawing-circle');

        if (!keepLayer && pivoCircleState.layer) {
            draftItems.removeLayer(pivoCircleState.layer);
        }

        pivoCircleState = null;
    }

    function startEdit() {
        if (!selectedTalhao || !selectedLayer || !selectedTalhao.can_edit_geometry) return;
        if (!Array.isArray(selectedTalhao.points) || selectedTalhao.points.length < 3) return;
        if (!discardPendingChanges()) return;

        editableLayer = buildEditableLayer(selectedTalhao).addTo(map);
        bringLayerToFront(editableLayer);
        enableLayerEditing(editableLayer);
        dimSelectedLayer(selectedLayer);
        isEditing = true;
        selectionStatus.update(selectedTalhao, 'Arraste os pontos e confirme no ícone verde. Use Reverter para cancelar.');
        syncToolbar();
    }

    function saveEdit() {
        if (!isEditing || !editableLayer || !selectedTalhao) return;

        const geometryPayload = editableGeometryPayload(editableLayer, selectedTalhao);
        if (!geometryPayload) {
            alert('O polígono precisa ter pelo menos três pontos em cada parte.');
            return;
        }

        const form = document.querySelector('[data-polygon-form]');
        const select = document.getElementById('polygonTalhaoId');
        const coordinates = document.getElementById('polygonCoordinates');
        const name = document.getElementById('polygonName');
        const description = document.getElementById('polygonDescription');
        if (!form || !select || !coordinates || !name) {
            alert('Não foi possível preparar a atualização do talhão.');
            return;
        }

        if (!confirm(`Salvar os ajustes no polígono do ${selectedTalhao.nome}?`)) return;

        select.value = String(selectedTalhao.id);
        name.value = selectedTalhao.nome || '';
        if (description) description.value = selectedTalhao.descricao || '';
        coordinates.value = JSON.stringify(geometryPayload);
        disableLayerEditing(editableLayer);

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function revertCurrent(showFeedback) {
        activeDrawHandler?.disable?.();
        defaultPolygonHandler?.disable?.();
        cleanupPivoCircleDraw(false);
        activeDrawHandler = null;
        activeDrawing = false;

        if (editableLayer) {
            disableLayerEditing(editableLayer);
            map.removeLayer(editableLayer);
            editableLayer = null;
        }

        isEditing = false;
        draftItems.clearLayers();
        hasDraft = false;
        drawMode = 'talhao';
        exclusionTalhaoId = null;
        pivoTalhaoId = null;
        clearDraftForms();

        if (selectedLayer) applySelectedStyle(selectedLayer, true);
        selectionStatus.update(
            selectedTalhao,
            showFeedback && selectedTalhao ? 'Ajustes não salvos revertidos.' : null,
        );
        syncToolbar();
    }

    function discardPendingChanges() {
        if (!hasPendingChanges()) return true;
        if (!confirm('Existem ajustes ainda não salvos. Deseja revertê-los?')) return false;

        revertCurrent(false);
        return true;
    }

    function hasPendingChanges() {
        return isEditing || activeDrawing || hasDraft;
    }

    function syncToolbar() {
        const hasPolygon = Boolean(selectedTalhao?.points?.length >= 3);
        const canEdit = hasPolygon && selectedTalhao?.can_edit_geometry === true;
        const canExclude = hasPolygon && selectedTalhao?.can_add_exclusion === true;
        const busy = isEditing || activeDrawing || hasDraft;

        contextToolbar.update({
            edit: !busy && canEdit,
            exclusion: !busy && canExclude,
            save: isEditing,
            revert: busy,
        });
    }

    map.on(L.Draw.Event.DRAWSTART, () => {
        activeDrawing = true;
        syncToolbar();
    });

    map.on(L.Draw.Event.DRAWSTOP, () => {
        activeDrawing = false;
        activeDrawHandler = null;
        syncToolbar();
    });

    map.on(L.Draw.Event.CREATED, (event) => {
        draftItems.clearLayers();
        draftItems.addLayer(event.layer);
        hasDraft = true;
        handlePolygonCreated(event, {
            mode: drawMode,
            talhaoId: exclusionTalhaoId,
            pivoTalhaoId,
            talhoes,
            cancelDraft: () => revertCurrent(false),
            openPolygonModal: options.openPolygonModal,
            openPivoModal: options.openPivoModal,
        });
        drawMode = 'talhao';
        exclusionTalhaoId = null;
        pivoTalhaoId = null;
        syncToolbar();
    });

    document.getElementById('btnDrawTalhaoTop')?.addEventListener('click', () => startDraw('talhao'));
    document.getElementById('btnDrawPivoTop')?.addEventListener('click', () => {
        startDraw('pivo', { targetMode: 'new' });
    });

    return {
        selectTalhao,
        startExclusion: () => startDraw('exclusao'),
        startPivo: (targetMode = 'existing') => startDraw('pivo', { targetMode }),
        cancelEdits: () => revertCurrent(false),
    };
}

function createMapContextToolbar(map, callbacks) {
    const tools = {};
    let container = null;
    const ContextToolbar = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            container = L.DomUtil.create('div', 'leaflet-bar ff-map-context-toolbar');
            container.setAttribute('aria-label', 'Ferramentas do talhão selecionado');
            tools.edit = createMapTool(container, 'edit', 'Editar polígono', 'bi-pencil-square', callbacks.edit);
            tools.exclusion = createMapTool(container, 'exclusion', 'Adicionar área excluída', 'bi-scissors', callbacks.exclusion);
            tools.save = createMapTool(container, 'save', 'Salvar ajustes', 'bi-check-lg', callbacks.save);
            tools.revert = createMapTool(container, 'revert', 'Reverter ajustes não salvos', 'bi-arrow-counterclockwise', callbacks.revert);
            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.disableScrollPropagation(container);
            return container;
        },
    });
    new ContextToolbar().addTo(map);

    return {
        update(state) {
            let visibleTools = 0;
            Object.entries(tools).forEach(([name, tool]) => {
                const visible = state[name] === true;
                tool.hidden = !visible;
                tool.setAttribute('aria-hidden', visible ? 'false' : 'true');
                if (visible) visibleTools += 1;
            });
            container?.classList.toggle('is-visible', visibleTools > 0);
            container?.setAttribute('aria-hidden', visibleTools > 0 ? 'false' : 'true');
        },
    };
}

function createMapTool(container, name, label, icon, callback) {
    const tool = L.DomUtil.create('a', `ff-map-tool ff-map-tool-${name}`, container);
    tool.href = '#';
    tool.dataset.mapTool = name;
    tool.title = label;
    tool.setAttribute('role', 'button');
    tool.setAttribute('aria-label', label);
    tool.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i>`;
    tool.hidden = true;
    L.DomEvent.on(tool, 'click', (event) => {
        L.DomEvent.preventDefault(event);
        L.DomEvent.stopPropagation(event);
        callback();
    });
    return tool;
}

function createSelectionStatusControl(map) {
    let container = null;
    const SelectionStatus = L.Control.extend({
        options: { position: 'bottomleft' },
        onAdd() {
            container = L.DomUtil.create('div', 'ff-map-selection-status');
            container.setAttribute('aria-live', 'polite');
            container.hidden = true;
            L.DomEvent.disableClickPropagation(container);
            return container;
        },
    });
    new SelectionStatus().addTo(map);

    return {
        update(talhao, message = null) {
            if (!container) return;
            if (!talhao) {
                container.hidden = true;
                container.textContent = '';
                return;
            }

            const blockedReason = talhao.map_mutation_block_reason || talhao.block_reason || '';
            const detail = message || blockedReason || 'Talhão selecionado. Escolha uma ferramenta no mapa.';
            container.classList.toggle('is-blocked', Boolean(blockedReason && !message));
            container.innerHTML = `<strong>${escapeHtml(talhao.nome || 'Talhão')}</strong><span>${escapeHtml(detail)}</span>`;
            container.hidden = false;
        },
    };
}

function applySelectedStyle(layer, selected) {
    if (!layer) return;
    const baseStyle = layer.ffBaseStyle || {};
    setLayerStyle(layer, selected
        ? { ...baseStyle, color: '#00d4aa', weight: Math.max(3, Number(baseStyle.weight || 2) + 1), fillOpacity: 0.38 }
        : baseStyle);
    if (selected) bringLayerToFront(layer);
}

function dimSelectedLayer(layer) {
    if (!layer) return;
    setLayerStyle(layer, { ...(layer.ffBaseStyle || {}), opacity: 0.28, fillOpacity: 0.08 });
}

function setLayerStyle(layer, style) {
    if (layer?.setStyle) {
        layer.setStyle(style);
        return;
    }

    layer?.eachLayer?.((child) => setLayerStyle(child, style));
}

function bringLayerToFront(layer) {
    if (layer?.bringToFront) {
        layer.bringToFront();
        return;
    }

    layer?.eachLayer?.((child) => bringLayerToFront(child));
}

function markSelectedListItem(talhaoId) {
    document.querySelectorAll('[data-map-list-talhao]').forEach((item) => {
        const selected = String(item.dataset.mapListTalhao) === String(talhaoId);
        item.classList.toggle('is-selected', selected);
        item.setAttribute('aria-current', selected ? 'true' : 'false');
    });
}

function synchronizeMapForms(talhaoId) {
    setMapSelectValues(talhaoId, document);
}

function clearDraftForms() {
    const coordinates = document.getElementById('polygonCoordinates');
    const polygonTarget = document.getElementById('polygonTalhaoId');
    const polygonModal = document.getElementById('mapPolygonFormModal');
    const polygonName = document.getElementById('polygonName');
    const polygonDescription = document.getElementById('polygonDescription');
    const exclusionJson = document.querySelector('[data-exclusion-json]');
    if (coordinates) coordinates.value = '';
    if (polygonTarget) polygonTarget.value = '';
    if (polygonName) polygonName.value = '';
    if (polygonDescription) polygonDescription.value = '';
    if (polygonModal) {
        polygonModal.dataset.pendingPolygon = '0';
        polygonModal.dataset.submittingPolygon = '0';
    }
    if (exclusionJson) exclusionJson.value = '';
}

function buildEditableLayer(talhao) {
    const editStyle = {
        color: '#00d4aa',
        weight: 3,
        opacity: 1,
        fillColor: '#00a77e',
        fillOpacity: 0.24,
        dashArray: '6 4',
    };
    const geometrias = Array.isArray(talhao?.geometries) && talhao.geometries.length
        ? talhao.geometries
        : [{ points: talhao?.points || [], exclusions: talhao?.exclusoes || [] }];
    const parts = geometrias
        .filter((geometria) => Array.isArray(geometria.points) && geometria.points.length >= 3)
        .map((geometria, index) => {
            const polygon = L.polygon(
                geometria.points.map((point) => [Number(point.lat), Number(point.lng)]),
                editStyle,
            );
            polygon.ffGeometryIndex = index;
            polygon.ffGeometryExclusions = geometria.exclusions || [];

            return polygon;
        });

    return parts.length === 1 ? parts[0] : L.featureGroup(parts);
}

function enableLayerEditing(layer) {
    if (layer?.editing) {
        layer.editing.enable();
        return;
    }

    layer?.eachLayer?.((child) => enableLayerEditing(child));
}

function disableLayerEditing(layer) {
    if (layer?.editing) {
        layer.editing.disable();
        return;
    }

    layer?.eachLayer?.((child) => disableLayerEditing(child));
}

function editableGeometryPayload(layer, talhao) {
    const parts = [];

    if (layer?.eachLayer && !layer?.getLatLngs) {
        layer.eachLayer((child) => {
            const points = layerPoints(child);
            if (points.length >= 3) {
                parts.push({
                    points,
                    exclusions: child.ffGeometryExclusions || [],
                });
            }
        });
    } else {
        const points = layerPoints(layer);
        if (points.length >= 3) {
            const firstGeometry = Array.isArray(talhao?.geometries) ? talhao.geometries[0] : null;
            parts.push({
                points,
                exclusions: firstGeometry?.exclusions || talhao?.exclusoes || [],
            });
        }
    }

    if (!parts.length) {
        return null;
    }

    if (parts.length === 1) {
        return parts[0].points;
    }

    return {
        type: 'MultiPolygon',
        geometries: parts,
    };
}

function layerPoints(layer) {
    const latLngs = layer?.getLatLngs?.();
    const ring = Array.isArray(latLngs?.[0]) ? latLngs[0] : [];
    return ring.map((point) => ({
        lat: Number(point.lat.toFixed(7)),
        lng: Number(point.lng.toFixed(7)),
    }));
}

function bindPolygonTargetSelector(talhoes) {
    const select = document.getElementById('polygonTalhaoId');
    const nameInput = document.getElementById('polygonName');
    const descriptionInput = document.getElementById('polygonDescription');
    if (!select || !nameInput) return;

    select.addEventListener('change', () => {
        const selected = talhoes.find((talhao) => String(talhao.id) === String(select.value));
        nameInput.value = selected ? selected.nome : '';
        if (descriptionInput) descriptionInput.value = selected ? selected.descricao || '' : '';
    });
}

function bindMapActionForms() {
    document.querySelectorAll('[data-map-action-template]').forEach((form) => {
        const select = form.querySelector('[data-map-talhao-select]');
        if (!select) return;

        const apply = () => {
            const id = select.value;
            const template = form.dataset.mapActionTemplate || '';
            if (id && template.includes('__ID__')) {
                form.action = template.replace('__ID__', encodeURIComponent(id));
            }

            const option = select.selectedOptions[0];
            if (!option) return;

            const nome = form.querySelector('[name="nome"]');
            const area = form.querySelector('[name="area"]');
            const descricao = form.querySelector('[name="descricao"]');
            if (nome && option.dataset.nome) nome.value = option.dataset.nome;
            if (area && option.dataset.area) area.value = option.dataset.area;
            if (descricao && option.dataset.descricao !== undefined) descricao.value = option.dataset.descricao;
        };

        select.addEventListener('change', apply);
        form.addEventListener('submit', (event) => {
            apply();
            if (!form.action || form.action.includes('__ID__')) {
                event.preventDefault();
                select.focus();
            }
        });
    });
}

function bindPolygonFormModal() {
    const modalEl = document.getElementById('mapPolygonFormModal');
    const form = modalEl?.querySelector('[data-polygon-form]');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    let pendingCancel = null;

    if (!modalEl || !form) {
        return { open: () => {} };
    }

    const fields = {
        target: document.getElementById('polygonTalhaoId'),
        coordinates: document.getElementById('polygonCoordinates'),
        name: document.getElementById('polygonName'),
        description: document.getElementById('polygonDescription'),
        area: document.getElementById('polygonAreaEstimate'),
        geometry: modalEl.querySelector('[data-polygon-geometry-label]'),
    };

    const closeFallback = () => {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    const cancelDraft = () => {
        if (typeof pendingCancel === 'function') {
            pendingCancel();
        }

        pendingCancel = null;
        modalEl.dataset.pendingPolygon = '0';
        modalEl.dataset.submittingPolygon = '0';
        form.reset();
        if (fields.target) fields.target.value = '';
        if (fields.coordinates) fields.coordinates.value = '';
        if (fields.name) fields.name.value = '';
        if (fields.description) fields.description.value = '';
        if (fields.area) fields.area.value = 'Calculada ao salvar';
        if (fields.geometry) fields.geometry.value = 'Polígono - desenho no mapa';
    };

    modalEl.querySelectorAll('[data-polygon-modal-cancel]').forEach((button) => {
        button.addEventListener('click', () => {
            cancelDraft();
            if (!modal) closeFallback();
        });
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        if (modalEl.dataset.pendingPolygon === '1' && modalEl.dataset.submittingPolygon !== '1') {
            cancelDraft();
        }
    });

    form.addEventListener('submit', () => {
        modalEl.dataset.submittingPolygon = '1';
        pendingCancel = null;
    });

    const open = (points, cancelCallback) => {
        form.reset();
        pendingCancel = cancelCallback;
        modalEl.dataset.pendingPolygon = '1';
        modalEl.dataset.submittingPolygon = '0';
        if (fields.target) fields.target.value = '';
        if (fields.coordinates) fields.coordinates.value = JSON.stringify(points || []);
        if (fields.name) fields.name.value = '';
        if (fields.description) fields.description.value = '';
        if (fields.area) fields.area.value = 'Calculada ao salvar';
        if (fields.geometry) fields.geometry.value = 'Polígono - desenho no mapa';

        if (modal) {
            modal.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        setTimeout(() => fields.name?.focus(), 180);
    };

    return { open };
}

function setMapSelectValues(talhaoId, root = document) {
    const id = String(talhaoId || '');
    if (!id) return;

    root.querySelectorAll('[data-map-talhao-select]').forEach((select) => {
        if (!Array.from(select.options).some((option) => String(option.value) === id)) return;

        select.value = id;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function bindMapPivoModal(talhoes) {
    const modalEl = document.getElementById('mapPivoModal');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const talhoesById = new Map((talhoes || []).map((talhao) => [String(talhao.id), talhao]));
    let pendingCancel = null;
    let pendingDraft = null;

    if (!modalEl) {
        return { open: () => {} };
    }

    const pivoForm = modalEl.querySelector('[data-map-pivo-form]');
    const createForm = modalEl.querySelector('[data-map-pivo-create-form]');
    const existingSection = modalEl.querySelector('[data-map-pivo-existing-section]');
    const newSection = modalEl.querySelector('[data-map-pivo-new-section]');
    const pivoSelect = pivoForm?.querySelector('[data-map-talhao-select]');
    const pivoFields = {
        lat: pivoForm?.querySelector('[name="pivo_lat"]'),
        lng: pivoForm?.querySelector('[name="pivo_lng"]'),
        raio: pivoForm?.querySelector('[name="pivo_raio_m"]'),
    };
    const createFields = {
        lat: createForm?.querySelector('[name="pivo_lat"]'),
        lng: createForm?.querySelector('[name="pivo_lng"]'),
        raio: createForm?.querySelector('[name="pivo_raio_m"]'),
    };

    const closeFallback = () => {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    const fillPivoFields = () => {
        if (pendingDraft) return;

        const talhao = talhoesById.get(String(pivoSelect?.value || ''));
        if (!talhao) return;

        if (pivoFields.lat) pivoFields.lat.value = talhao.pivo_lat ?? '';
        if (pivoFields.lng) pivoFields.lng.value = talhao.pivo_lng ?? '';
        if (pivoFields.raio) pivoFields.raio.value = talhao.pivo_raio_m ?? '';
    };

    pivoSelect?.addEventListener('change', fillPivoFields);

    const applyDraftFields = (draft) => {
        if (!draft) return;

        const lat = draft.lat ?? '';
        const lng = draft.lng ?? '';
        const raio = draft.raio ?? '';

        if (pivoFields.lat) pivoFields.lat.value = lat;
        if (pivoFields.lng) pivoFields.lng.value = lng;
        if (pivoFields.raio) pivoFields.raio.value = raio;
        if (createFields.lat) createFields.lat.value = lat;
        if (createFields.lng) createFields.lng.value = lng;
        if (createFields.raio) createFields.raio.value = raio;
    };

    const cancelDraft = () => {
        if (typeof pendingCancel === 'function') {
            pendingCancel();
        }

        pendingCancel = null;
        pendingDraft = null;
        modalEl.dataset.pendingPivo = '0';
        modalEl.dataset.submittingPivo = '0';
    };

    modalEl.addEventListener('hidden.bs.modal', () => {
        if (modalEl.dataset.pendingPivo === '1' && modalEl.dataset.submittingPivo !== '1') {
            cancelDraft();
        }
    });

    modalEl.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            modalEl.dataset.submittingPivo = '1';
            pendingCancel = null;
            pendingDraft = null;
        });
    });

    const open = (talhaoId = null, draft = null) => {
        const mode = talhaoId ? 'existing' : 'new';
        pendingCancel = typeof draft?.cancelDraft === 'function' ? draft.cancelDraft : null;
        pendingDraft = draft ? {
            lat: draft.lat,
            lng: draft.lng,
            raio: draft.raio,
        } : null;
        modalEl.dataset.pivoMode = mode;
        modalEl.dataset.pendingPivo = draft ? '1' : '0';
        modalEl.dataset.submittingPivo = '0';
        if (existingSection) existingSection.hidden = mode !== 'existing';
        if (newSection) newSection.hidden = mode !== 'new';

        if (talhaoId) {
            setMapSelectValues(talhaoId, modalEl);
            fillPivoFields();
        }

        applyDraftFields(pendingDraft);

        if (modal) {
            modal.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        setTimeout(() => {
            const activeSection = mode === 'existing' ? existingSection : newSection;
            const firstEmpty = activeSection?.querySelector('select, input:not([type="hidden"])');
            firstEmpty?.focus?.();
        }, 180);
    };

    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
        button.addEventListener('click', () => {
            if (modalEl.dataset.pendingPivo === '1' && modalEl.dataset.submittingPivo !== '1') {
                cancelDraft();
            }

            if (!modal) closeFallback();
        });
    });

    return { open };
}

function bindTalhaoEditModal(talhoes, mapTools, openPivoModal = null) {
    const modalEl = document.getElementById('mapTalhaoEditModal');
    const form = modalEl?.querySelector('[data-map-details-form]');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const talhoesById = new Map((talhoes || []).map((talhao) => [String(talhao.id), talhao]));
    let activeTalhaoId = null;

    if (!modalEl || !form) {
        return { open: () => {} };
    }

    const fields = {
        id: modalEl.querySelector('[data-map-edit-talhao-id]'),
        name: modalEl.querySelector('[name="nome"]'),
        area: modalEl.querySelector('[name="area"]'),
        description: modalEl.querySelector('[name="descricao"]'),
        geometry: modalEl.querySelector('[data-map-edit-geometry]'),
        removePivo: modalEl.querySelector('[data-map-modal-remove-pivo]'),
        clearExclusions: modalEl.querySelector('[data-map-modal-clear-exclusions]'),
    };

    const closeFallback = () => {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    const cancelModalEditing = () => {
        mapTools.cancelEdits?.();
        form.reset();
        activeTalhaoId = null;
    };

    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
        button.addEventListener('click', () => {
            cancelModalEditing();
            if (!modal) closeFallback();
        });
    });

    const open = (talhaoId) => {
        const id = String(talhaoId || '');
        const talhao = talhoesById.get(id);
        if (!talhao) return;

        activeTalhaoId = id;
        const hasPolygon = Array.isArray(talhao.points) && talhao.points.length >= 3;
        const hasGeometry = hasPolygon || Boolean(talhao.tem_geometria);
        const actionTemplate = form.dataset.mapActionTemplate || '';

        if (actionTemplate.includes('__ID__')) {
            form.action = actionTemplate.replace('__ID__', encodeURIComponent(id));
        }

        if (fields.id) fields.id.value = id;
        if (fields.name) fields.name.value = talhao.nome || '';
        if (fields.area) {
            fields.area.value = formatAreaInput(talhao.area);
            fields.area.readOnly = hasPolygon;
            fields.area.title = hasPolygon ? 'Área calculada pelo polígono do mapa.' : '';
        }
        if (fields.description) fields.description.value = talhao.descricao || '';
        if (fields.geometry) {
            const geometryLabel = talhao.tipo_label || (hasPolygon ? 'Polígono' : 'Manual');
            fields.geometry.value = hasPolygon
                ? `${geometryLabel} - com desenho no mapa`
                : (hasGeometry ? `${geometryLabel} - com referência no mapa` : 'Manual - sem desenho no mapa');
        }
        if (fields.removePivo) {
            fields.removePivo.disabled = !talhao.pivo_ativo;
            fields.removePivo.title = talhao.pivo_ativo ? 'Remover pivô deste talhão' : 'Este talhão não possui pivô cadastrado';
        }
        if (fields.clearExclusions) {
            const canClear = talhao.can_clear_exclusions === true;
            const hasExclusions = Number(talhao.exclusoes_count || 0) > 0;
            fields.clearExclusions.disabled = !canClear;
            fields.clearExclusions.title = canClear
                ? 'Limpar áreas excluídas deste talhão'
                : (hasExclusions
                    ? talhao.map_mutation_block_reason || talhao.block_reason || 'Este talhão não pode limpar exclusões no momento.'
                    : 'Este talhão não possui áreas excluídas.');
        }

        setMapSelectValues(id, document);

        if (modal) {
            modal.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        setTimeout(() => fields.name?.focus(), 180);
    };

    modalEl.querySelector('[data-map-modal-exclusion]')?.addEventListener('click', () => {
        const id = activeTalhaoId;
        if (!id) return;

        modal?.hide();
        if (!modal) closeFallback();

        setTimeout(() => {
            if (!mapTools.selectTalhao(id, { openPopup: false })) return;
            mapTools.startExclusion();
        }, 180);
    });

    modalEl.querySelector('[data-map-modal-pivo]')?.addEventListener('click', () => {
        const id = activeTalhaoId;
        if (!id) return;

        modal?.hide();
        if (!modal) closeFallback();

        setTimeout(() => {
            if (!mapTools.selectTalhao(id, { openPopup: false })) return;
            mapTools.startPivo?.('existing');
        }, 180);
    });

    modalEl.querySelector('[data-map-modal-remove-pivo]')?.addEventListener('click', () => {
        const id = activeTalhaoId;
        const talhao = id ? talhoesById.get(id) : null;
        const deleteForm = document.querySelector('[data-pivo-delete-form]');
        const select = deleteForm?.querySelector('[data-map-talhao-select]');
        if (!id || !talhao?.pivo_ativo || !deleteForm || !select) return;
        if (!confirm(`Remover o pivô do talhão ${talhao.nome}?`)) return;

        setMapSelectValues(id, deleteForm);
        if (typeof deleteForm.requestSubmit === 'function') {
            deleteForm.requestSubmit();
        } else {
            deleteForm.submit();
        }
    });

    modalEl.querySelector('[data-map-modal-clear-exclusions]')?.addEventListener('click', () => {
        const id = activeTalhaoId;
        const talhao = id ? talhoesById.get(id) : null;
        const clearForm = document.querySelector('[data-exclusion-clear-form]');
        const select = clearForm?.querySelector('[data-map-talhao-select]');
        if (!id || !talhao || !clearForm || !select) return;

        if (talhao.can_clear_exclusions !== true) {
            alert(talhao.map_mutation_block_reason || talhao.block_reason || 'Este talhão não possui exclusões que possam ser limpas.');
            return;
        }

        if (!confirm(`Limpar todas as áreas excluídas do talhão ${talhao.nome}?`)) return;

        setMapSelectValues(id, clearForm);
        if (typeof clearForm.requestSubmit === 'function') {
            clearForm.requestSubmit();
        } else {
            clearForm.submit();
        }
    });

    return { open };
}

function formatAreaInput(value) {
    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function bindMapList(map, talhaoLayers, selectTalhao, openEditModal) {
    const MAP_MAX_ZOOM = 17;
    document.querySelectorAll('[data-map-focus-talhao]').forEach((button) => {
        button.addEventListener('click', () => {
            const talhaoId = String(button.dataset.mapFocusTalhao);
            const layer = talhaoLayers.get(talhaoId);
            if (!layer || !selectTalhao(talhaoId)) return;

            if (typeof layer.getBounds === 'function') {
                map.fitBounds(layer.getBounds().pad(0.35), { maxZoom: MAP_MAX_ZOOM });
            } else if (typeof layer.getLatLng === 'function') {
                map.setView(layer.getLatLng(), MAP_MAX_ZOOM);
            }
            layer.openPopup?.();
        });
    });

    document.querySelectorAll('[data-map-edit-talhao]').forEach((button) => {
        button.addEventListener('click', () => {
            const talhaoId = String(button.dataset.mapEditTalhao);
            if (!selectTalhao(talhaoId, { openPopup: false })) return;

            openEditModal?.(talhaoId);
        });
    });
}

function handlePolygonCreated(event, context = {}) {
    if (context.mode === 'pivo') {
        handlePivoCreated(event.layer, context);
        return;
    }

    const points = layerPoints(event.layer);

    if (context.mode === 'exclusao') {
        event.layer.setStyle?.({ color: '#f59e0b', fill: false, fillOpacity: 0, dashArray: '6 5', className: 'ff-map-exclusion-outline' });
        handleExclusionCreated(points, context);
        return;
    }

    if (typeof context.openPolygonModal === 'function') {
        context.openPolygonModal(points, context.cancelDraft);
        return;
    }

    const coordinates = document.getElementById('polygonCoordinates');
    const name = document.getElementById('polygonName');
    if (coordinates) coordinates.value = JSON.stringify(points);
    name?.focus();
}

function handlePivoCreated(layer, context = {}) {
    const draft = circleDraftData(layer);
    if (!draft) {
        alert('Não foi possível ler o pivô desenhado no mapa.');
        context.cancelDraft?.();
        return;
    }

    layer.setStyle?.(pivoCircleStyle());

    context.openPivoModal?.(context.pivoTalhaoId || null, {
        ...draft,
        cancelDraft: context.cancelDraft,
    });
}

function pivoCircleStyle() {
    return {
        color: '#8edfd0',
        weight: 2,
        fillColor: '#8edfd0',
        fillOpacity: 0.2,
    };
}

function circleDraftData(layer) {
    const center = layer?.getLatLng?.();
    const radius = layer?.getRadius?.();
    if (!center || !Number.isFinite(Number(radius))) {
        return null;
    }

    return {
        lat: Number(center.lat.toFixed(7)),
        lng: Number(center.lng.toFixed(7)),
        raio: Number(Number(radius).toFixed(2)),
    };
}

function handleExclusionCreated(points, context) {
    const form = document.querySelector('[data-exclusion-form]');
    const select = form?.querySelector('[data-map-talhao-select]');
    const textarea = form?.querySelector('[data-exclusion-json]');
    const talhaoId = context.talhaoId;

    if (!form || !select || !textarea || !talhaoId) {
        alert('Não foi possível identificar o talhão da área excluída.');
        return;
    }

    select.value = String(talhaoId);
    select.dispatchEvent(new Event('change', { bubbles: true }));
    textarea.value = JSON.stringify(points);

    const talhao = (context.talhoes || []).find((item) => String(item.id) === String(talhaoId));
    const nome = talhao?.nome || 'talhão selecionado';
    const salvarAgora = confirm(`Salvar esta área excluída no ${nome}? Ela será descontada da área útil do talhão.`);

    if (salvarAgora) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
        return;
    }

    context.cancelDraft?.();
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function popupHtml(talhao) {
    const downloadUrls = talhao.download_urls || {};
    const mapsLink = talhao.google_url
        ? `<a class="btn btn-sm btn-outline-success mt-2" href="${escapeHtml(talhao.google_url)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir no Maps</a>`
        : '';
    const downloadLinks = talhao.download_url || downloadUrls.kml
        ? `<div class="map-popup-downloads mt-2">
            <a href="${escapeHtml(downloadUrls.kml || downloadLink(talhao.download_url, 'kml'))}"><i class="bi bi-filetype-xml me-1"></i>KML</a>
            <a href="${escapeHtml(downloadUrls.kmz || downloadLink(talhao.download_url, 'kmz'))}"><i class="bi bi-file-zip me-1"></i>KMZ</a>
            <a href="${escapeHtml(downloadUrls.shp || downloadLink(talhao.download_url, 'shp'))}"><i class="bi bi-map me-1"></i>SHP</a>
        </div>`
        : '';
    const blockReason = talhao.map_mutation_block_reason || talhao.block_reason || '';
    const mapLock = blockReason
        ? `<div class="map-popup-lock mt-2"><i class="bi bi-lock-fill"></i>${escapeHtml(blockReason)}</div>`
        : '';

    return `
        <div class="map-popup">
            <strong>${escapeHtml(talhao.nome || 'Talhão')}</strong>
            <div class="text-muted mb-2">${escapeHtml(talhao.tipo_label || 'Polígono')} - ${escapeHtml(talhao.area_formatada || `${formatNumber(talhao.area || 0)} ha`)}</div>
            ${talhao.exclusoes_count ? `<div><span>Área excluída:</span> <b>${escapeHtml(talhao.area_excluida_formatada || '')}</b></div>` : ''}
            ${talhao.pivo_ativo ? `<div><span>Pivô:</span> <b>${escapeHtml(talhao.pivo_area_formatada || '')}</b></div>` : ''}
            <div><span>Despesas:</span> <b>${escapeHtml(talhao.total_despesas_formatado || talhao.custo_formatado || 'R$ 0,00')}</b></div>
            <div><span>Custo/ha:</span> <b>${escapeHtml(talhao.custo_ha_formatado || 'R$ 0,00/ha')}</b></div>
            <div><span>Lançamentos:</span> <b>${escapeHtml(talhao.qtd_despesas ?? 0)}</b></div>
            ${mapLock}
            ${mapsLink}
            ${downloadLinks}
        </div>
    `;
}

function labelHtml(value, color) {
    return `<span class="ff-talhao-map-label-text" style="--talhao-label-color:${escapeHtml(color || '#74c69d')}">${escapeHtml(value || 'Talhão')}</span>`;
}

function downloadLink(baseUrl, formato) {
    if (!baseUrl) return '#';
    const separator = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${separator}formato=${encodeURIComponent(formato)}`;
}
