window.initTalhaoMapa = function initTalhaoMapa(config) {
    translateLeafletDraw();
    const MAP_MAX_ZOOM = 17;
    const MAP_MAX_NATIVE_ZOOM = 16;
    const map = L.map('talhaoMap', {
        zoomControl: true,
        attributionControl: true,
        preferCanvas: true,
        maxZoom: MAP_MAX_ZOOM,
        zoomSnap: 0.25,
        zoomDelta: 0.5,
        wheelPxPerZoomLevel: 90,
    }).setView(config.centro, 14);
    const drawnItems = new L.FeatureGroup();
    const talhaoLayers = new Map();
    const labelsLayer = L.layerGroup();
    const bounds = L.latLngBounds();

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
    map.addLayer(drawnItems);

    renderTalhoes(map, config.talhoes || [], talhaoLayers, labelsLayer, bounds);

    L.control.layers(
        { Satélite: satellite, Ruas: roads },
        { Rótulos: labelsLayer },
        { collapsed: false, position: 'topright' }
    ).addTo(map);

    map.on('zoomend', () => {
        if (map.getZoom() > MAP_MAX_ZOOM) {
            map.setZoom(MAP_MAX_ZOOM);
        }
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.18), { maxZoom: MAP_MAX_ZOOM });
    }

    configureDraw(map, drawnItems, config.talhoes || []);
    bindPolygonTargetSelector(config.talhoes || []);
    bindMapActionForms();
    bindMapList(map, talhaoLayers);

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
    L.drawLocal.draw.toolbar.buttons.polygon = 'Desenhar polígono';
    L.drawLocal.draw.handlers.polygon.tooltip.start = 'Clique para iniciar o polígono.';
    L.drawLocal.draw.handlers.polygon.tooltip.cont = 'Clique para continuar o polígono.';
    L.drawLocal.draw.handlers.polygon.tooltip.end = 'Clique no primeiro ponto para finalizar.';
    L.drawLocal.draw.handlers.polyline.error = '<strong>Erro:</strong> as linhas não podem se cruzar.';

    L.drawLocal.edit.toolbar.actions.save.title = 'Salvar alterações';
    L.drawLocal.edit.toolbar.actions.save.text = 'Salvar';
    L.drawLocal.edit.toolbar.actions.cancel.title = 'Cancelar edição';
    L.drawLocal.edit.toolbar.actions.cancel.text = 'Cancelar';
    L.drawLocal.edit.toolbar.actions.clearAll.title = 'Remover todos';
    L.drawLocal.edit.toolbar.actions.clearAll.text = 'Remover todos';
    L.drawLocal.edit.toolbar.buttons.edit = 'Editar desenho';
    L.drawLocal.edit.toolbar.buttons.editDisabled = 'Nenhuma área para editar';
    L.drawLocal.edit.toolbar.buttons.remove = 'Excluir desenho';
    L.drawLocal.edit.toolbar.buttons.removeDisabled = 'Nenhuma área para excluir';
    L.drawLocal.edit.handlers.edit.tooltip.text = 'Arraste os pontos para editar.';
    L.drawLocal.edit.handlers.edit.tooltip.subtext = 'Clique em cancelar para desfazer.';
    L.drawLocal.edit.handlers.remove.tooltip.text = 'Clique em uma área para excluir.';
}

function renderTalhoes(map, talhoes, talhaoLayers, labelsLayer, bounds) {
    const colorByType = { polygon: '#74c69d', line: '#ffd166', point: '#2d9d5c', manual: '#2d9d5c' };

    talhoes.forEach((talhao) => {
        if (talhao.points && talhao.points.length >= 3) {
            const outer = talhao.points.map((point) => [point.lat, point.lng]);
            const holes = (talhao.exclusoes || [])
                .filter((ring) => Array.isArray(ring) && ring.length >= 3)
                .map((ring) => ring.map((point) => [point.lat, point.lng]));
            const polygonColor = colorByType.polygon;
            const polygon = L.polygon([outer, ...holes], {
                color: polygonColor,
                weight: 2,
                opacity: 0.95,
                fillColor: '#2d9d5c',
                fillOpacity: 0.28,
            }).addTo(map);

            polygon.bindPopup(popupHtml(talhao));
            talhaoLayers.set(String(talhao.id), polygon);
            polygon.getBounds().isValid() && bounds.extend(polygon.getBounds());

            holes.forEach((ring) => {
                L.polygon(ring, {
                    color: '#f59e0b',
                    fillColor: '#f59e0b',
                    fillOpacity: 0.14,
                    weight: 2,
                    dashArray: '6 5',
                    interactive: false,
                }).addTo(map);
            });

            const labelPoint = polygon.getBounds().getCenter();
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
            return;
        }

        if (talhao.lat && talhao.lng) {
            const marker = L.circleMarker([talhao.lat, talhao.lng], {
                radius: 8,
                color: '#ffffff',
                fillColor: colorByType.point,
                fillOpacity: 0.95,
                weight: 2,
            }).addTo(map).bindPopup(popupHtml(talhao));

            talhaoLayers.set(String(talhao.id), marker);
            bounds.extend(marker.getLatLng());
        }

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

function configureDraw(map, drawnItems, talhoes) {
    let drawMode = 'talhao';
    let exclusionTalhaoId = null;
    let polygonDrawHandler = null;
    let exclusionDrawHandler = null;
    const drawControl = new L.Control.Draw({
        edit: { featureGroup: drawnItems },
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
    if (L.Draw && L.Draw.Polygon) {
        polygonDrawHandler = new L.Draw.Polygon(map, {
            allowIntersection: false,
            showArea: true,
            shapeOptions: { color: '#05a784', fillColor: '#23885f', fillOpacity: 0.28 },
        });
        exclusionDrawHandler = new L.Draw.Polygon(map, {
            allowIntersection: false,
            showArea: true,
            shapeOptions: { color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.18, dashArray: '6 5' },
        });
    }

    const startDraw = (mode, talhaoId = null) => {
        drawMode = mode;
        exclusionTalhaoId = talhaoId;
        if (mode === 'exclusao' && !exclusionTalhaoId) {
            exclusionTalhaoId = selectedExclusionTalhaoId();
        }

        if (mode === 'exclusao' && !exclusionTalhaoId) {
            alert('Selecione o talhão que receberá a área excluída.');
            document.querySelector('[data-exclusion-form]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        const handler = mode === 'exclusao' ? exclusionDrawHandler : polygonDrawHandler;
        if (handler) {
            handler.enable();
            return;
        }

        document.querySelector('.leaflet-draw-draw-polygon')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    };

    document.querySelector('.leaflet-draw-draw-polygon')?.addEventListener('click', () => {
        drawMode = 'talhao';
        exclusionTalhaoId = null;
    });

    document.getElementById('btnDrawTalhaoTop')?.addEventListener('click', () => {
        startDraw('talhao');
    });

    document.getElementById('btnDrawExclusionTop')?.addEventListener('click', () => {
        startDraw('exclusao');
    });

    document.querySelectorAll('[data-map-draw-exclusion]').forEach((button) => {
        button.addEventListener('click', () => startDraw('exclusao', button.dataset.mapDrawExclusion));
    });

    document.getElementById('btnDrawPivoTop')?.addEventListener('click', () => {
        const panel = document.querySelector('[data-pivo-panel]') || document.querySelector('#polygonFormPanel')?.nextElementSibling;
        panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    map.on(L.Draw.Event.CREATED, (event) => {
        handlePolygonCreated(event, drawnItems, {
            mode: drawMode,
            talhaoId: exclusionTalhaoId,
            talhoes,
        });
        drawMode = 'talhao';
        exclusionTalhaoId = null;
    });
}

function bindPolygonTargetSelector(talhoes) {
    const select = document.getElementById('polygonTalhaoId');
    const nameInput = document.getElementById('polygonName');
    if (!select || !nameInput) return;

    select.addEventListener('change', () => {
        const selected = talhoes.find((talhao) => String(talhao.id) === String(select.value));
        nameInput.value = selected ? selected.nome : '';
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

function bindMapList(map, talhaoLayers) {
    const MAP_MAX_ZOOM = 17;
    document.querySelectorAll('[data-map-focus-talhao]').forEach((button) => {
        button.addEventListener('click', () => {
            const layer = talhaoLayers.get(String(button.dataset.mapFocusTalhao));
            if (!layer) return;

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
            const select = document.querySelector('[data-map-talhao-select]');
            if (select) {
                select.value = button.dataset.mapEditTalhao;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            document.querySelector('.ff-map-hidden-forms')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

function handlePolygonCreated(event, drawnItems, context = {}) {
    drawnItems.clearLayers();
    drawnItems.addLayer(event.layer);

    const points = event.layer.getLatLngs()[0].map((point) => ({
        lat: Number(point.lat.toFixed(7)),
        lng: Number(point.lng.toFixed(7)),
    }));

    if (context.mode === 'exclusao') {
        event.layer.setStyle?.({ color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.18, dashArray: '6 5' });
        handleExclusionCreated(points, context);
        return;
    }

    document.getElementById('polygonCoordinates').value = JSON.stringify(points);
    document.getElementById('polygonFormPanel').style.display = 'block';
    document.getElementById('polygonName').focus();
    document.getElementById('polygonFormPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function selectedExclusionTalhaoId() {
    return document.querySelector('[data-exclusion-form] [data-map-talhao-select]')?.value
        || document.getElementById('polygonTalhaoId')?.value
        || '';
}

function handleExclusionCreated(points, context) {
    const form = document.querySelector('[data-exclusion-form]');
    const select = form?.querySelector('[data-map-talhao-select]');
    const textarea = form?.querySelector('[data-exclusion-json]');
    const talhaoId = context.talhaoId || selectedExclusionTalhaoId();

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
            const submitButton = form.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.click();
            } else {
                form.submit();
            }
        }
        return;
    }

    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    textarea.focus();
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

    return `
        <div class="map-popup">
            <strong>${escapeHtml(talhao.nome || 'Talhão')}</strong>
            <div class="text-muted mb-2">${escapeHtml(talhao.tipo_label || 'Polígono')} - ${escapeHtml(talhao.area_formatada || `${formatNumber(talhao.area || 0)} ha`)}</div>
            ${talhao.exclusoes_count ? `<div><span>Área excluída:</span> <b>${escapeHtml(talhao.area_excluida_formatada || '')}</b></div>` : ''}
            ${talhao.pivo_ativo ? `<div><span>Pivô:</span> <b>${escapeHtml(talhao.pivo_area_formatada || '')}</b></div>` : ''}
            <div><span>Despesas:</span> <b>${escapeHtml(talhao.total_despesas_formatado || talhao.custo_formatado || 'R$ 0,00')}</b></div>
            <div><span>Custo/ha:</span> <b>${escapeHtml(talhao.custo_ha_formatado || 'R$ 0,00/ha')}</b></div>
            <div><span>Lançamentos:</span> <b>${escapeHtml(talhao.qtd_despesas ?? 0)}</b></div>
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
