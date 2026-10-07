/**
 * Login page: one switch for everything that moves on its own (photo slideshow, particles). The button
 * [data-motion-toggle] pauses and resumes both; the choice is remembered on this device.
 *
 * Starts paused for visitors whose system asks for reduced motion.
 */

const STORAGE_KEY = 'login-motion-paused';
const listeners = new Set();

function stored() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        // Private mode or blocked storage: fall back to the system setting.
        return null;
    }
}

let paused = (stored() ?? String(window.matchMedia('(prefers-reduced-motion: reduce)').matches)) === 'true';

export const isMotionPaused = () => paused;

/** Calls back with the new state every time the visitor pauses or resumes. */
export function onMotionChange(callback) {
    listeners.add(callback);
}

export function initMotionToggle(root = document) {
    const button = root.querySelector('[data-motion-toggle]');

    if (!button) {
        return;
    }

    const render = () => {
        const label = paused ? 'Lanjutkan animasi' : 'Jeda animasi';

        button.setAttribute('aria-pressed', String(paused));
        button.setAttribute('aria-label', label);
        button.dataset.tooltip = label;
    };

    render();

    button.addEventListener('click', () => {
        paused = !paused;

        try {
            localStorage.setItem(STORAGE_KEY, String(paused));
        } catch {
            // The choice then only lasts for this page view.
        }

        render();
        listeners.forEach((callback) => callback(paused));
    });
}
