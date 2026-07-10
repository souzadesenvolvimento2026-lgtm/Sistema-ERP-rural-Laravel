window.initTalhaoMapa = function initTalhaoMapa(config) {
    const map = L.map('talhaoMap', {
        zoomControl: true,
        attributionControl: true,
    }).setView(config.centro, 14);
    const drawnItems = new L.FeatureGroup();
    const talhaoLayers = new Map();
    const labelsLayer = L.layerGroup();
    const bounds = L.latLngBounds();

    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 20,
        attribution: 'Leaflet | Esri, Maxar, Earthstar Geographics, Esri',
    }).addTo(map);

    const roads = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap',
    });

    labelsLayer.addTo(map);
    map.addLayer(drawnItems);

    renderTalhoes(map, config.talhoes || [], talhaoLayers, labelsLayer, bounds);

    L.control.layers(
        { Satelite: satellite, Ruas: roads },
        { Rotulos: labelsLayer },
        { collapsed: false, position: 'topright' }
    ).addTo(map);

    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.08), { maxZoom: 15 });
    }

    configureDraw(map, drawnItems);
    bindPolygonTargetSelector(config.talhoes || []);
    bindMapActionForms();
    bindMapList(map, talhaoLayers);

    setTimeout(() => map.invalidateSize(), 120);
};

function renderTalhoes(map, talhoes, talhaoLayers, labelsLayer, bounds) {
    talhoes.forEach((talhao) => {
        if (talhao.points && talhao.points.length >= 3) {
            const outer = talhao.points.map((point) => [point.lat, point.lng]);
            const holes = (talhao.exclusoes || [])
                .filter((ring) => Array.isArray(ring) && ring.length >= 3)
                .map((ring) => ring.map((point) => [point.lat, point.lng]));
            const polygon = L.polygon([outer, ...holes], {
                color: '#42d4b7',
                weight: 2,
                opacity: 0.95,
                fillColor: '#23885f',
                fillOpacity: 0.36,
            }).addTo(map);

            polygon.bindPopup(popupHtml(talhao));
            talhaoLayers.set(String(talhao.id), polygon);
            polygon.getBounds().isValid() && bounds.extend(polygon.getBounds());

            const labelPoint = polygon.getBounds().getCenter();
            L.marker(labelPoint, {
                opacity: 0,
                interactive: false,
                keyboard: false,
            }).bindTooltip(labelHtml(talhao.nome), {
                permanent: true,
                direction: 'center',
                className: 'ff-talhao-map-label',
            }).addTo(labelsLayer);
            return;
        }

        if (talhao.lat && talhao.lng) {
            const marker = L.circleMarker([talhao.lat, talhao.lng], {
                radius: 6,
                color: '#42d4b7',
                fillColor: '#16a37a',
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

function configureDraw(map, drawnItems) {
    let polygonDrawHandler = null;
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
    }

    document.getElementById('btnDrawTalhaoTop')?.addEventListener('click', () => {
        if (polygonDrawHandler) {
            polygonDrawHandler.enable();
            return;
        }

        document.querySelector('.leaflet-draw-draw-polygon')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    document.getElementById('btnDrawPivoTop')?.addEventListener('click', () => {
        const panel = document.querySelector('[data-pivo-panel]') || document.querySelector('#polygonFormPanel')?.nextElementSibling;
        panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    map.on(L.Draw.Event.CREATED, (event) => handlePolygonCreated(event, drawnItems));
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
    document.querySelectorAll('[data-map-focus-talhao]').forEach((button) => {
        button.addEventListener('click', () => {
            const layer = talhaoLayers.get(String(button.dataset.mapFocusTalhao));
            if (!layer) return;

            if (typeof layer.getBounds === 'function') {
                map.fitBounds(layer.getBounds().pad(0.22), { maxZoom: 16 });
            } else if (typeof layer.getLatLng === 'function') {
                map.setView(layer.getLatLng(), 16);
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

function handlePolygonCreated(event, drawnItems) {
    drawnItems.clearLayers();
    drawnItems.addLayer(event.layer);

    const points = event.layer.getLatLngs()[0].map((point) => ({
        lat: Number(point.lat.toFixed(7)),
        lng: Number(point.lng.toFixed(7)),
    }));

    document.getElementById('polygonCoordinates').value = JSON.stringify(points);
    document.getElementById('polygonFormPanel').style.display = 'block';
    document.getElementById('polygonName').focus();
    document.getElementById('polygonFormPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function popupHtml(talhao) {
    return `<strong>${escapeHtml(talhao.nome || 'Talhão')}</strong><br>${formatNumber(talhao.area || 0)} ha<br>${escapeHtml(talhao.custo_formatado || 'R$ 0,00')}`;
}

function labelHtml(value) {
    return `<span>${escapeHtml(value || 'Talhão')}</span>`;
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
