document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-modal-name-target][data-modal-form-target]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const nameTarget = document.querySelector(trigger.dataset.modalNameTarget);
            const formTarget = document.querySelector(trigger.dataset.modalFormTarget);

            if (nameTarget && formTarget) {
                nameTarget.textContent = trigger.dataset.modalName;
                formTarget.action = trigger.dataset.modalDeleteUrl;
            }
        });
    });

    if (typeof window.bootstrap !== 'undefined') {
        return;
    }

    const sidebar = document.getElementById('adminSidebar');
    const sidebarBackdrop = document.createElement('div');
    sidebarBackdrop.className = 'offcanvas-backdrop fade';

    function closeSidebar() {
        if (!sidebar) {
            return;
        }

        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
        sidebarBackdrop.remove();
    }

    document.querySelectorAll('[data-bs-toggle="offcanvas"]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            sidebar.classList.add('show');
            sidebarBackdrop.classList.add('show');
            document.body.appendChild(sidebarBackdrop);
        });
    });

    document.querySelectorAll('[data-bs-dismiss="offcanvas"]').forEach(function (trigger) {
        trigger.addEventListener('click', closeSidebar);
    });
    sidebarBackdrop.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            trigger.nextElementSibling?.classList.toggle('show');
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
            menu.classList.remove('show');
        });
    });

    let activeModal = null;
    let modalBackdrop = null;

    function closeModal() {
        if (!activeModal) {
            return;
        }

        activeModal.classList.remove('show');
        activeModal.style.display = 'none';
        activeModal.setAttribute('aria-hidden', 'true');
        activeModal.removeAttribute('aria-modal');
        activeModal.removeAttribute('role');
        modalBackdrop?.remove();
        document.body.classList.remove('modal-open');
        activeModal = null;
        modalBackdrop = null;
    }

    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            activeModal = document.querySelector(trigger.dataset.bsTarget);
            if (!activeModal) {
                return;
            }

            activeModal.style.display = 'block';
            activeModal.classList.add('show');
            activeModal.removeAttribute('aria-hidden');
            activeModal.setAttribute('aria-modal', 'true');
            activeModal.setAttribute('role', 'dialog');
            document.body.classList.add('modal-open');
            modalBackdrop = document.createElement('div');
            modalBackdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(modalBackdrop);
        });
    });

    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (trigger) {
        trigger.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
            closeSidebar();
        }
    });

    document.querySelectorAll('.alert [data-bs-dismiss="alert"]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            trigger.closest('.alert')?.remove();
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const entryForm = document.querySelector('[data-entry-form]');
    const entryItems = document.querySelector('[data-entry-items]');

    if (!entryForm || !entryItems) {
        return;
    }

    const list = entryItems.querySelector('[data-entry-items-list]');
    const template = document.querySelector('[data-entry-item-template]');
    const addButton = entryItems.querySelector('[data-add-entry-item]');
    const inventoryOptions = JSON.parse(entryItems.dataset.inventoryOptions || '[]');
    const inventoryById = new Map(inventoryOptions.map(function (item) {
        return [String(item.id), item];
    }));
    const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    function calculateTotals() {
        let total = 0;
        list.querySelectorAll('[data-entry-item]').forEach(function (row) {
            const quantity = Number.parseFloat(row.querySelector('[data-entry-quantity]')?.value) || 0;
            const unitCost = Number.parseFloat(row.querySelector('[data-entry-unit-cost]')?.value) || 0;
            const subtotal = quantity * unitCost;
            row.querySelector('[data-entry-subtotal]').textContent = money.format(subtotal);
            total += subtotal;
        });
        entryItems.querySelector('[data-entry-total]').textContent = money.format(total);
    }

    function updateExpiration(row) {
        const selected = inventoryById.get(row.querySelector('[data-entry-product]')?.value);
        const expiration = row.querySelector('[data-entry-expiration]');
        const state = row.querySelector('[data-expiration-state]');
        const required = Boolean(selected?.requiresExpiration);

        expiration.required = required;
        state.textContent = required ? '*' : '(Opcional)';
        state.className = required ? 'text-danger' : 'text-body-secondary fw-normal';
    }

    function reindexRows() {
        const rows = list.querySelectorAll('[data-entry-item]');
        rows.forEach(function (row, index) {
            row.querySelector('[data-entry-item-number]').textContent = index + 1;
            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`);
            });
            row.querySelector('[data-remove-entry-item]').disabled = rows.length === 1;
        });
    }

    function initializeRow(row) {
        row.querySelector('[data-entry-product]').addEventListener('change', function () {
            updateExpiration(row);
        });
        row.querySelectorAll('[data-entry-quantity], [data-entry-unit-cost]').forEach(function (field) {
            field.addEventListener('input', calculateTotals);
        });
        row.querySelector('[data-remove-entry-item]').addEventListener('click', function () {
            if (list.querySelectorAll('[data-entry-item]').length === 1) {
                return;
            }
            row.remove();
            reindexRows();
            calculateTotals();
        });
        updateExpiration(row);
    }

    list.querySelectorAll('[data-entry-item]').forEach(initializeRow);
    reindexRows();
    calculateTotals();

    addButton.addEventListener('click', function () {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', list.querySelectorAll('[data-entry-item]').length).trim();
        const row = wrapper.firstElementChild;
        list.appendChild(row);
        initializeRow(row);
        reindexRows();
        row.querySelector('[data-entry-product]').focus();
    });

    entryForm.addEventListener('submit', function () {
        const submitButton = entryForm.querySelector('[data-entry-submit]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-disabled', 'true');
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-transfer-form]');
    const section = document.querySelector('[data-transfer-items]');

    if (!form || !section) {
        return;
    }

    const list = section.querySelector('[data-transfer-items-list]');
    const template = document.querySelector('[data-transfer-item-template]');
    const cabinetSelect = form.querySelector('[data-transfer-cabinet]');
    const addButton = section.querySelector('[data-add-transfer-item]');
    const compatibilityWarning = section.querySelector('[data-transfer-compatibility-warning]');
    const data = JSON.parse(section.dataset.transferData || '{}');
    const inventoryById = new Map((data.inventoryItems || []).map(function (item) {
        return [String(item.id), item];
    }));

    function formatQuantity(value) {
        const number = Number.parseFloat(value) || 0;
        return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 3 }).format(number);
    }

    function compatibleProductIds() {
        return new Set(data.cabinets?.[cabinetSelect.value] || []);
    }

    function selectedInventoryIds(exceptRow = null) {
        return new Set(Array.from(list.querySelectorAll('[data-transfer-item]'))
            .filter(function (row) { return row !== exceptRow; })
            .map(function (row) { return row.querySelector('[data-transfer-product]').value; })
            .filter(Boolean));
    }

    function updateQuantityState(row) {
        const item = inventoryById.get(row.querySelector('[data-transfer-product]').value);
        const quantity = Number.parseFloat(row.querySelector('[data-transfer-quantity]').value) || 0;
        const warning = row.querySelector('[data-transfer-stock-warning]');
        warning.classList.toggle('d-none', !item || quantity <= Number.parseFloat(item.usableStock));
    }

    function updateRow(row) {
        const select = row.querySelector('[data-transfer-product]');
        const selected = inventoryById.get(select.value);
        const compatible = compatibleProductIds();
        const selectedElsewhere = selectedInventoryIds(row);

        Array.from(select.options).forEach(function (option) {
            if (!option.value) {
                return;
            }
            const item = inventoryById.get(option.value);
            option.disabled = !cabinetSelect.value || !compatible.has(String(item.productId)) || selectedElsewhere.has(option.value);
        });

        row.querySelector('[data-transfer-stock]').textContent = selected ? formatQuantity(selected.usableStock) : '—';
        row.querySelector('[data-transfer-unit]').textContent = selected?.unit || 'Unidad';
        row.querySelector('[data-transfer-incompatible]').classList.toggle(
            'd-none',
            !selected || compatible.has(String(selected.productId)),
        );
        updateQuantityState(row);
    }

    function updateAllRows() {
        list.querySelectorAll('[data-transfer-item]').forEach(updateRow);
    }

    function reindexRows() {
        const rows = list.querySelectorAll('[data-transfer-item]');
        rows.forEach(function (row, index) {
            row.querySelector('[data-transfer-item-number]').textContent = index + 1;
            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`);
            });
            row.querySelector('[data-remove-transfer-item]').disabled = rows.length === 1;
        });
    }

    function initializeRow(row) {
        row.querySelector('[data-transfer-product]').addEventListener('change', updateAllRows);
        row.querySelector('[data-transfer-quantity]').addEventListener('input', function () {
            updateQuantityState(row);
        });
        row.querySelector('[data-remove-transfer-item]').addEventListener('click', function () {
            if (list.querySelectorAll('[data-transfer-item]').length === 1) {
                return;
            }
            row.remove();
            reindexRows();
            updateAllRows();
        });
    }

    cabinetSelect.addEventListener('change', function () {
        const compatible = compatibleProductIds();
        let removedSelection = false;
        list.querySelectorAll('[data-transfer-item]').forEach(function (row) {
            const select = row.querySelector('[data-transfer-product]');
            const selected = inventoryById.get(select.value);
            if (selected && !compatible.has(String(selected.productId))) {
                select.value = '';
                removedSelection = true;
            }
        });
        compatibilityWarning.classList.toggle('d-none', !removedSelection);
        updateAllRows();
    });

    list.querySelectorAll('[data-transfer-item]').forEach(initializeRow);
    reindexRows();
    updateAllRows();

    addButton.addEventListener('click', function () {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', list.querySelectorAll('[data-transfer-item]').length).trim();
        const row = wrapper.firstElementChild;
        list.appendChild(row);
        initializeRow(row);
        reindexRows();
        updateAllRows();
        row.querySelector('[data-transfer-product]').focus();
    });

    form.addEventListener('submit', function () {
        const submit = form.querySelector('[data-transfer-submit]');
        if (submit) {
            submit.disabled = true;
            submit.setAttribute('aria-disabled', 'true');
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-outbound-form]');
    const section = document.querySelector('[data-outbound-items]');
    if (!form || !section) return;

    const list = section.querySelector('[data-outbound-items-list]');
    const template = document.querySelector('[data-outbound-item-template]');
    const addButton = section.querySelector('[data-add-outbound-item]');
    const options = JSON.parse(section.dataset.inventoryOptions || '[]');
    const byId = new Map(options.map(function (item) { return [String(item.id), item]; }));
    const formatQuantity = function (value) {
        return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 3 }).format(Number.parseFloat(value) || 0);
    };

    function selectedIds(exceptRow) {
        return new Set(Array.from(list.querySelectorAll('[data-outbound-item]'))
            .filter(function (row) { return row !== exceptRow; })
            .map(function (row) { return row.querySelector('[data-outbound-product]').value; })
            .filter(Boolean));
    }

    function updateRow(row) {
        const select = row.querySelector('[data-outbound-product]');
        const selected = byId.get(select.value);
        const quantity = Number.parseFloat(row.querySelector('[data-outbound-quantity]').value) || 0;
        const used = selectedIds(row);
        Array.from(select.options).forEach(function (option) {
            if (option.value) option.disabled = used.has(option.value);
        });
        row.querySelector('[data-outbound-stock]').textContent = selected ? formatQuantity(selected.usableStock) : '—';
        row.querySelector('[data-outbound-unit]').textContent = selected?.unit || 'Unidad';
        row.querySelector('[data-outbound-stock-warning]').classList.toggle('d-none', !selected || quantity <= Number.parseFloat(selected.usableStock));
    }

    function updateRows() { list.querySelectorAll('[data-outbound-item]').forEach(updateRow); }
    function reindexRows() {
        const rows = list.querySelectorAll('[data-outbound-item]');
        rows.forEach(function (row, index) {
            row.querySelector('[data-outbound-item-number]').textContent = index + 1;
            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`);
            });
            row.querySelector('[data-remove-outbound-item]').disabled = rows.length === 1;
        });
    }
    function initializeRow(row) {
        row.querySelector('[data-outbound-product]').addEventListener('change', updateRows);
        row.querySelector('[data-outbound-quantity]').addEventListener('input', function () { updateRow(row); });
        row.querySelector('[data-remove-outbound-item]').addEventListener('click', function () {
            if (list.querySelectorAll('[data-outbound-item]').length === 1) return;
            row.remove(); reindexRows(); updateRows();
        });
    }

    list.querySelectorAll('[data-outbound-item]').forEach(initializeRow);
    reindexRows(); updateRows();
    addButton.addEventListener('click', function () {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', list.querySelectorAll('[data-outbound-item]').length).trim();
        const row = wrapper.firstElementChild;
        list.appendChild(row); initializeRow(row); reindexRows(); updateRows();
        row.querySelector('[data-outbound-product]').focus();
    });
    form.addEventListener('submit', function () {
        const submit = form.querySelector('[data-outbound-submit]');
        if (submit) { submit.disabled = true; submit.setAttribute('aria-disabled', 'true'); }
    });
});
