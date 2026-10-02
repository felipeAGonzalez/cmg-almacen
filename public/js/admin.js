document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-user-form]');
    if (!form) return;

    const roleSelect = form.querySelector('#role');
    const warehouseSelection = form.querySelector('#warehouseSelection');
    const administratorNotice = form.querySelector('#administratorAccessNotice');
    const warehouseInputs = warehouseSelection.querySelectorAll('input[type="checkbox"]');
    const warehouseSelectionHelp = form.querySelector('#warehouseSelectionHelp');
    const hospitalSection = form.querySelector('#hospitalUserLink');
    const hospitalSelect = form.querySelector('#hospital_user_id');
    const hospitalStatus = form.querySelector('[data-hospital-nurse-status]');
    const hospitalSelector = form.querySelector('[data-hospital-nurse-selector]');
    const currentLink = form.querySelector('[data-current-hospital-nurse]');
    const currentLinkName = form.querySelector('[data-current-hospital-nurse-name]');
    const currentLinkEmail = form.querySelector('[data-current-hospital-nurse-email]');
    const removalWarning = form.querySelector('[data-hospital-link-removal]');
    const searchWrap = form.querySelector('[data-hospital-nurse-search-wrap]');
    const searchInput = form.querySelector('[data-hospital-nurse-search]');
    const submitButton = form.querySelector('[data-user-submit]');
    const submitLabel = form.querySelector('[data-user-submit-label]');
    const currentHospitalUserId = form.dataset.currentHospitalUserId || '';
    let rememberedHospitalUserId = currentHospitalUserId;
    let hospitalNurses = [];
    let nursesLoaded = false;
    let loadingNurses = false;

    function enforceNurseWarehouseLimit(changedInput) {
        if (roleSelect.value !== 'nurse' || !changedInput.checked) return;
        warehouseInputs.forEach(function (input) {
            if (input !== changedInput) input.checked = false;
        });
    }

    function renderHospitalNurseOptions(nurses, selectedId = '') {
        hospitalSelect.innerHTML = '<option value="">Selecciona una enfermera</option>';
        nurses.forEach(function (nurse) {
            const option = new Option(`${nurse.name} — ${nurse.email}`, nurse.hospital_user_id);
            option.selected = String(nurse.hospital_user_id) === String(selectedId);
            hospitalSelect.add(option);
        });
    }

    function renderCurrentHospitalNurse() {
        const nurse = hospitalNurses.find(function (item) {
            return String(item.hospital_user_id) === String(currentHospitalUserId);
        });
        currentLink.classList.toggle('d-none', !currentHospitalUserId);
        if (!currentHospitalUserId) return;

        currentLinkName.textContent = nurse?.name || `ID vinculado: ${currentHospitalUserId}`;
        currentLinkEmail.textContent = nurse?.email || 'Los datos descriptivos no están disponibles en este momento.';
    }

    function renderHospitalNurseLoading() {
        hospitalSelector.hidden = true;
        hospitalSelect.disabled = true;
        searchWrap.classList.add('d-none');
        hospitalStatus.innerHTML = '<div class="d-flex align-items-center gap-2 text-body-secondary py-2"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Consultando enfermeras de Hospitalización...</span></div>';
    }

    function renderHospitalNurseError() {
        hospitalStatus.innerHTML = '<div class="alert alert-warning mb-3" role="alert"><div class="fw-semibold">No fue posible consultar las enfermeras de Hospitalización.</div><div class="small mt-1">Verifica la conexión con Hospitalización e inténtalo nuevamente.</div><button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-retry-hospital-nurses>Reintentar</button></div>';
        currentLink.classList.toggle('d-none', !currentHospitalUserId);
        if (currentHospitalUserId) {
            currentLinkName.textContent = `ID vinculado: ${currentHospitalUserId}`;
            currentLinkEmail.textContent = 'La vinculación actual se conservará.';
            renderHospitalNurseOptions([], currentHospitalUserId);
            hospitalSelect.add(new Option(`Vinculación actual (${currentHospitalUserId})`, currentHospitalUserId, true, true));
            hospitalSelect.disabled = false;
        } else {
            hospitalStatus.insertAdjacentHTML('beforeend', '<div class="small text-body-secondary mt-2">La enfermera se creará sin vinculación con Hospitalización.</div>');
            hospitalSelect.disabled = true;
        }
        hospitalSelector.hidden = true;
        hospitalStatus.querySelector('[data-retry-hospital-nurses]').addEventListener('click', function (event) {
            event.currentTarget.disabled = true;
            loadHospitalNurses(true);
        });
    }

    function renderHospitalNurseList(payload) {
        hospitalNurses = payload.data || [];
        nursesLoaded = true;
        hospitalStatus.innerHTML = '';
        renderCurrentHospitalNurse();
        const selectedId = rememberedHospitalUserId || currentHospitalUserId;
        renderHospitalNurseOptions(hospitalNurses, selectedId);
        hospitalSelect.disabled = false;
        hospitalSelector.hidden = false;
        searchWrap.classList.toggle('d-none', hospitalNurses.length < 10);

        if (hospitalNurses.length === 0) {
            const message = Number(payload.meta?.total || 0) > 0
                ? 'Todas las enfermeras de Hospitalización ya están vinculadas.'
                : 'No hay enfermeras disponibles para vincular.';
            hospitalStatus.innerHTML = `<div class="alert alert-light border py-2 mb-3">${message}</div>`;
        }
    }

    async function loadHospitalNurses(force = false) {
        if (loadingNurses || (nursesLoaded && !force)) return;
        loadingNurses = true;
        renderHospitalNurseLoading();
        try {
            const response = await fetch(form.dataset.hospitalNursesUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Hospital nurse request failed.');
            renderHospitalNurseList(await response.json());
        } catch (error) {
            renderHospitalNurseError();
            if (force) {
                hospitalStatus.querySelector('[data-retry-hospital-nurses]')?.focus();
            }
        } finally {
            loadingNurses = false;
        }
    }

    function updateUserForm() {
        const isAdministrator = roleSelect.value === 'administrator';
        const isNurse = roleSelect.value === 'nurse';
        administratorNotice.hidden = !isAdministrator;
        warehouseSelection.hidden = isAdministrator;
        warehouseInputs.forEach(function (input) { input.disabled = isAdministrator; });
        warehouseSelectionHelp.textContent = isNurse
            ? 'Selecciona como máximo un almacén.'
            : 'Selecciona uno o varios almacenes.';
        hospitalSection.hidden = !isNurse;
        removalWarning.classList.toggle('d-none', isNurse || !currentHospitalUserId);

        if (isNurse) {
            if (rememberedHospitalUserId) hospitalSelect.value = rememberedHospitalUserId;
            loadHospitalNurses();
        } else {
            rememberedHospitalUserId = hospitalSelect.value || rememberedHospitalUserId;
            hospitalSelect.value = '';
            hospitalSelect.disabled = true;
        }
    }

    hospitalSelect.addEventListener('change', function () {
        rememberedHospitalUserId = hospitalSelect.value;
    });
    searchInput.addEventListener('input', function () {
        const query = searchInput.value.trim().toLocaleLowerCase('es');
        Array.from(hospitalSelect.options).forEach(function (option) {
            if (!option.value) return;
            option.hidden = query !== '' && !option.text.toLocaleLowerCase('es').includes(query);
        });
    });
    roleSelect.addEventListener('change', updateUserForm);
    warehouseInputs.forEach(function (input) {
        input.addEventListener('change', function () { enforceNurseWarehouseLimit(input); });
    });
    form.addEventListener('submit', function () {
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitLabel.textContent = 'Guardando...';
    });
    updateUserForm();
});

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

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-adjustment-form]');
    if (!form) return;

    const batches = JSON.parse(form.dataset.adjustmentBatches || '[]');
    const byId = new Map(batches.map(function (batch) { return [String(batch.id), batch]; }));
    const batchSelect = form.querySelector('[data-adjustment-batch]');
    const countedInput = form.querySelector('[data-adjustment-counted]');
    const submit = form.querySelector('[data-adjustment-submit]');
    const formatter = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 3 });
    const formatQuantity = function (value) { return formatter.format(Number.parseFloat(value) || 0); };
    const formatDate = function (value) {
        if (!value) return 'Sin caducidad';
        const parts = value.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    function updateAdjustment() {
        const batch = byId.get(batchSelect.value);
        const hasCount = countedInput.value !== '' && Number.isFinite(Number.parseFloat(countedInput.value));
        const counted = Number.parseFloat(countedInput.value);
        const current = batch ? Number.parseFloat(batch.availableQuantity) : 0;
        const difference = Math.round((counted - current) * 1000) / 1000;
        const equal = Boolean(batch && hasCount && Math.abs(difference) < 0.0005);
        const differenceElement = form.querySelector('[data-adjustment-difference]');

        form.querySelector('[data-adjustment-current]').textContent = batch ? formatQuantity(batch.availableQuantity) : '—';
        form.querySelector('[data-adjustment-manufacturer]').textContent = batch?.manufacturerLot || 'No indicado';
        form.querySelector('[data-adjustment-expiration]').textContent = batch ? formatDate(batch.expirationDate) : '—';
        form.querySelector('[data-adjustment-expired]').classList.toggle('d-none', !batch?.expired);
        form.querySelector('[data-adjustment-equal]').classList.toggle('d-none', !equal);
        differenceElement.textContent = batch && hasCount ? `${difference > 0 ? '+' : ''}${formatQuantity(difference)}` : '—';
        differenceElement.className = `badge fs-5 ${!batch || !hasCount ? 'text-bg-secondary' : difference > 0 ? 'text-bg-success' : difference < 0 ? 'text-bg-danger' : 'text-bg-secondary'}`;
        submit.disabled = !batch || !hasCount || counted < 0 || equal;
    }

    batchSelect.addEventListener('change', updateAdjustment);
    countedInput.addEventListener('input', updateAdjustment);
    updateAdjustment();
    form.addEventListener('submit', function () {
        if (!form.checkValidity()) return;
        submit.disabled = true;
        submit.setAttribute('aria-disabled', 'true');
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-nursing-voucher-form]');
    const section = document.querySelector('[data-nursing-voucher-items]');

    if (form && section) {
        const list = section.querySelector('[data-voucher-items-list]');
        const template = document.querySelector('[data-nursing-voucher-item-template]');
        const addButton = section.querySelector('[data-add-voucher-item]');
        const products = JSON.parse(section.dataset.productOptions || '[]');
        const byId = new Map(products.map(function (product) { return [String(product.id), product]; }));

        function selectedIds(exceptRow) {
            return new Set(Array.from(list.querySelectorAll('[data-voucher-item]'))
                .filter(function (row) { return row !== exceptRow; })
                .map(function (row) { return row.querySelector('[data-voucher-product]').value; })
                .filter(Boolean));
        }

        function updateRow(row) {
            const select = row.querySelector('[data-voucher-product]');
            const product = byId.get(select.value);
            const unavailable = selectedIds(row);
            Array.from(select.options).forEach(function (option) {
                if (option.value) option.disabled = unavailable.has(option.value);
            });
            row.querySelector('[data-voucher-unit]').textContent = product?.unit || 'Unidad';
            row.querySelector('[data-voucher-product-detail]').textContent = product
                ? [product.code, product.barcode].filter(Boolean).join(' · ') || 'Sin código adicional'
                : 'Selecciona por nombre, código o código de barras.';
        }

        function updateAll() {
            list.querySelectorAll('[data-voucher-item]').forEach(updateRow);
        }

        function reindex() {
            const rows = list.querySelectorAll('[data-voucher-item]');
            rows.forEach(function (row, index) {
                row.querySelector('[data-voucher-item-number]').textContent = index + 1;
                row.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`);
                });
                row.querySelector('[data-remove-voucher-item]').disabled = rows.length === 1;
            });
        }

        function initialize(row) {
            row.querySelector('[data-voucher-product]').addEventListener('change', updateAll);
            row.querySelector('[data-remove-voucher-item]').addEventListener('click', function () {
                if (list.querySelectorAll('[data-voucher-item]').length === 1) return;
                row.remove();
                reindex();
                updateAll();
            });
        }

        list.querySelectorAll('[data-voucher-item]').forEach(initialize);
        reindex();
        updateAll();
        addButton.addEventListener('click', function () {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', list.children.length).trim();
            const row = wrapper.firstElementChild;
            list.appendChild(row);
            initialize(row);
            reindex();
            updateAll();
            row.querySelector('[data-voucher-product]').focus();
        });
    }

    document.querySelectorAll('[data-disable-submit-form]').forEach(function (targetForm) {
        targetForm.addEventListener('submit', function () {
            targetForm.querySelectorAll('[data-submit-button]').forEach(function (button) {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });
        });
    });
});
