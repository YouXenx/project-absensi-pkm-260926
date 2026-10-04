/**
 * Login page: tsParticles (particles.matteobruni.it) behind the form, on the panel's own grey background.
 * Dots and their connecting lines are dark blue; lines reach out to the mouse cursor ("grab").
 * Loaded only on the login page.
 */
import { tsParticles } from '@tsparticles/engine';
import { loadSlim } from '@tsparticles/slim';

const DARK_BLUE = '#1e3a8a';

export async function initLoginParticles(root = document) {
    const container = root.querySelector('[data-particles]');

    if (!container) {
        return;
    }

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const mouse = window.matchMedia('(pointer: fine)').matches;

    await loadSlim(tsParticles);

    await tsParticles.load({
        id: container.id,
        options: {
            // The canvas fills its own box (.auth-particles), not the whole page, and paints no background.
            fullScreen: { enable: false },
            detectRetina: true,
            fpsLimit: 60,
            pauseOnOutsideViewport: true,
            particles: {
                // 120 per 1000x900 px: about 87 on a desktop panel, 44 on a phone.
                number: { value: 120, density: { enable: true, width: 1000, height: 900 } },
                paint: { color: { value: DARK_BLUE }, fill: { enable: true } },
                shape: { type: 'circle' },
                opacity: { value: 0.9 },
                size: { value: { min: 1, max: 3.5 } },
                links: { enable: true, distance: 150, color: DARK_BLUE, opacity: 0.45, width: 1 },
                move: { enable: !still, speed: 1.6, direction: 'none', random: false, straight: false, outModes: 'out' },
            },
            interactivity: {
                // "window", because the canvas sits behind the form and never receives mouse events itself.
                detectsOn: 'window',
                events: {
                    onHover: { enable: mouse && !still, mode: 'grab' },
                    onClick: { enable: false },
                },
                modes: {
                    grab: { distance: 190, links: { opacity: 0.85 } },
                },
            },
        },
    });
}
