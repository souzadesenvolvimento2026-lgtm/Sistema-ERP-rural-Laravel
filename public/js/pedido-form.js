(() => {
    const items = document.querySelector('#items');
    const template = document.querySelector('#itemTemplate');
    const orderTotal = document.querySelector('#orderTotal');
    const addItemButton = document.querySelector('[data-add-item]');

    if (!items || !template || !orderTotal || !addItemButton) {
        return;
    }

    function parseNumber(value) {
        value = String(value || '').trim();

        if (value.includes(',')) {
            value = value.replace(/\./g, '').replace(',', '.');
        }

        const number = Number(value);

        return Number.isFinite(number) ? number : 0;
    }

    function money(value) {
        return value.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    }

    function refreshItemLabels() {
        const itemCards = Array.from(items.querySelectorAll('[data-item]'));

        itemCards.forEach((item, index) => {
            const title = item.querySelector('[data-item-title]');
            const removeButton = item.querySelector('[data-remove-item]');

            if (title) {
                title.textContent = `Item ${index + 1}`;
            }

            if (removeButton) {
                removeButton.disabled = itemCards.length === 1;
            }
        });
    }

    function refreshTotals() {
        let total = 0;

        items.querySelectorAll('[data-item]').forEach((item) => {
            const quantity = parseNumber(item.querySelector('[data-quantity]')?.value);
            const unitValue = parseNumber(item.querySelector('[data-unit-value]')?.value);
            const itemTotal = quantity * unitValue;
            const itemTotalInput = item.querySelector('[data-item-total]');

            total += itemTotal;

            if (itemTotalInput) {
                itemTotalInput.value = money(itemTotal);
            }
        });

        orderTotal.textContent = money(total);
        refreshItemLabels();
    }

    function refreshAssetUse(item) {
        const asset = item.querySelector('[data-asset]');
        const use = item.querySelector('[data-asset-use]');
        const useWrap = item.querySelector('[data-asset-use-wrap]');
        const quantityWrap = item.querySelector('[data-asset-quantity-wrap]');
        const hasAsset = asset?.value !== '';

        if (useWrap) {
            useWrap.style.display = hasAsset ? '' : 'none';
        }

        if (!hasAsset && use) {
            use.value = 'estoque';
        }

        if (quantityWrap) {
            quantityWrap.style.display = hasAsset && use?.value === 'parcial' ? '' : 'none';
        }
    }

    function setField(item, selector, value) {
        const field = item.querySelector(selector);

        if (field) {
            field.value = value ?? '';
        }
    }

    function fillItem(item, data) {
        setField(item, '[name="item_product_code[]"]', data.product_code);
        setField(item, '[name="item_description[]"]', data.description);
        setField(item, '[name="item_categoria_id[]"]', data.categoria_id);
        setField(item, '[name="item_patrimonio_id[]"]', data.patrimonio_id);
        setField(item, '[name="item_patrimonio_uso[]"]', data.patrimonio_uso || 'estoque');
        setField(item, '[name="item_patrimonio_quantidade[]"]', data.patrimonio_quantidade);
        setField(item, '[name="item_unit[]"]', data.unit);
        setField(item, '[name="item_quantity[]"]', data.quantity);
        setField(item, '[name="item_unit_value[]"]', data.unit_value);
    }

    function bindItem(item) {
        item.querySelectorAll('[data-quantity], [data-unit-value]').forEach((input) => {
            input.addEventListener('input', refreshTotals);
        });

        item.querySelector('[data-remove-item]')?.addEventListener('click', () => {
            if (items.querySelectorAll('[data-item]').length > 1) {
                item.remove();
                refreshTotals();
            }
        });

        item.querySelector('[data-asset]')?.addEventListener('change', () => refreshAssetUse(item));
        item.querySelector('[data-asset-use]')?.addEventListener('change', () => refreshAssetUse(item));
        refreshAssetUse(item);
    }

    function addItem(data = null) {
        const item = template.content.firstElementChild.cloneNode(true);
        items.appendChild(item);

        if (data) {
            fillItem(item, data);
        }

        bindItem(item);
        refreshTotals();
    }

    addItemButton.addEventListener('click', () => addItem());

    let existingItems = [];

    try {
        existingItems = JSON.parse(items.dataset.existingItems || '[]');
    } catch (error) {
        existingItems = [];
    }

    if (existingItems.length > 0) {
        existingItems.forEach((item) => addItem(item));
    } else {
        addItem();
    }
})();
