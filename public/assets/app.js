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
    const tabLists = document.querySelectorAll('[data-tab-list]');
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

    tabLists.forEach((tabList) => {
        const triggers = Array.from(tabList.querySelectorAll('[data-tab-trigger]'));
        if (triggers.length === 0) {
            return;
        }

        const tabIds = triggers
            .map((trigger) => trigger.getAttribute('data-tab-target'))
            .filter((value) => typeof value === 'string' && value !== '');
        const panels = tabIds
            .map((id) => document.getElementById(id))
            .filter((panel) => panel !== null);

        const activateTab = (targetId) => {
            triggers.forEach((trigger) => {
                const isActive = trigger.getAttribute('data-tab-target') === targetId;
                trigger.classList.toggle('is-active', isActive);
                trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
                trigger.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            panels.forEach((panel) => {
                panel.hidden = panel.id !== targetId;
            });
        };

        const initialTrigger = triggers.find((trigger) => trigger.classList.contains('is-active')) || triggers[0];
        const initialTarget = initialTrigger.getAttribute('data-tab-target');
        if (initialTarget) {
            activateTab(initialTarget);
        }

        triggers.forEach((trigger, index) => {
            trigger.addEventListener('click', () => {
                const targetId = trigger.getAttribute('data-tab-target');
                if (targetId) {
                    activateTab(targetId);
                }
            });

            trigger.addEventListener('keydown', (event) => {
                if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft' && event.key !== 'Home' && event.key !== 'End') {
                    return;
                }

                event.preventDefault();
                let nextIndex = index;

                if (event.key === 'ArrowRight') {
                    nextIndex = (index + 1) % triggers.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (index - 1 + triggers.length) % triggers.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = triggers.length - 1;
                }

                const nextTrigger = triggers[nextIndex];
                const targetId = nextTrigger.getAttribute('data-tab-target');
                if (!targetId) {
                    return;
                }

                activateTab(targetId);
                nextTrigger.focus();
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setAccessibilityPanelOpen(false);
        }
    });
});
