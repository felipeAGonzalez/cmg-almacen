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
