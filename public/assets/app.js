document.addEventListener('DOMContentLoaded', () => {
    const printTrigger = document.querySelector('[data-print-trigger]');
    const body = document.body;
    const root = document.documentElement;
    const sidebar = document.querySelector('#app-sidebar');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toasts = document.querySelectorAll('.toast');
    const accessibilityPanel = document.querySelector('[data-accessibility-panel]');
    const accessibilityToggles = document.querySelectorAll('[data-accessibility-toggle]');
    const accessibilityCloseControls = document.querySelectorAll('[data-accessibility-close]');
    const accessibilityOptions = document.querySelectorAll('[data-a11y-setting]');
    const accessibilityReset = document.querySelector('[data-a11y-reset]');
    const desktopBreakpoint = window.matchMedia('(min-width: 992px)');
    const collapsedKey = 'sims-sidebar-collapsed';
    const accessibilityKey = 'sims-accessibility';
    const defaultAccessibilityState = {
        textSize: 'default',
        contrast: 'default',
        motion: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'reduced' : 'default',
    };

    const readAccessibilityState = () => {
        try {
            const raw = JSON.parse(localStorage.getItem(accessibilityKey) || '{}');

            return {
                textSize: typeof raw.textSize === 'string' ? raw.textSize : defaultAccessibilityState.textSize,
                contrast: typeof raw.contrast === 'string' ? raw.contrast : defaultAccessibilityState.contrast,
                motion: typeof raw.motion === 'string' ? raw.motion : defaultAccessibilityState.motion,
            };
        } catch (error) {
            return { ...defaultAccessibilityState };
        }
    };

    const writeAccessibilityState = (state) => {
        localStorage.setItem(accessibilityKey, JSON.stringify(state));
    };

    const applyAccessibilityState = (state) => {
        root.dataset.textSize = state.textSize;
        root.dataset.contrast = state.contrast;
        root.dataset.motion = state.motion;

        accessibilityOptions.forEach((option) => {
            const key = option.getAttribute('data-a11y-setting');
            const value = option.getAttribute('data-a11y-value');
            const active = key !== null && value !== null && state[key] === value;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    let accessibilityState = readAccessibilityState();
    applyAccessibilityState(accessibilityState);

    if (printTrigger) {
        printTrigger.addEventListener('click', () => {
            window.print();
        });
    }

    const syncSidebarState = () => {
        if (!sidebar || !overlay || !body.classList.contains('authenticated-shell')) {
            return;
        }

        if (desktopBreakpoint.matches) {
            overlay.classList.remove('active');
            sidebar.classList.remove('active');
            const isCollapsed = localStorage.getItem(collapsedKey) === 'true';
            body.classList.toggle('sidebar-collapsed', isCollapsed);
        } else {
            body.classList.remove('sidebar-collapsed');
        }
    };

    const toggleSidebar = () => {
        if (!sidebar || !overlay || !body.classList.contains('authenticated-shell')) {
            return;
        }

        if (desktopBreakpoint.matches) {
            const nextCollapsed = !body.classList.contains('sidebar-collapsed');
            body.classList.toggle('sidebar-collapsed', nextCollapsed);
            localStorage.setItem(collapsedKey, String(nextCollapsed));
            return;
        }

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', toggleSidebar);
    });

    if (desktopBreakpoint.addEventListener) {
        desktopBreakpoint.addEventListener('change', syncSidebarState);
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar?.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    syncSidebarState();

    if (window.bootstrap) {
        toasts.forEach((element) => {
            const toast = bootstrap.Toast.getOrCreateInstance(element);
            toast.show();
        });
    }

    const setAccessibilityPanelOpen = (isOpen) => {
        if (!accessibilityPanel) {
            return;
        }

        accessibilityPanel.hidden = !isOpen;
        body.classList.toggle('accessibility-open', isOpen);
        accessibilityToggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    };

    accessibilityToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const shouldOpen = accessibilityPanel?.hidden ?? true;
            setAccessibilityPanelOpen(shouldOpen);
        });
    });

    accessibilityCloseControls.forEach((control) => {
        control.addEventListener('click', () => {
            setAccessibilityPanelOpen(false);
        });
    });

    accessibilityOptions.forEach((option) => {
        option.addEventListener('click', () => {
            const key = option.getAttribute('data-a11y-setting');
            const value = option.getAttribute('data-a11y-value');
            if (!key || !value) {
                return;
            }

            accessibilityState = {
                ...accessibilityState,
                [key]: value,
            };

            applyAccessibilityState(accessibilityState);
            writeAccessibilityState(accessibilityState);
        });
    });

    if (accessibilityReset) {
        accessibilityReset.addEventListener('click', () => {
            accessibilityState = { ...defaultAccessibilityState };
            applyAccessibilityState(accessibilityState);
            writeAccessibilityState(accessibilityState);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setAccessibilityPanelOpen(false);
        }
    });
});
