/**
 * Login page: particles.js (github.com/VincentGarreau/particles.js) behind the form, on the panel's own grey
 * background. Dots and their connecting lines are dark blue; lines reach out to the mouse cursor ("grab").
 * Loaded only on the login page.
 *
 * The library is from 2015 and uses arguments.callee, which throws in strict mode, so it cannot be imported as a
 * module. Vite hands out the URL of the untouched file and it is loaded as a classic script instead.
 */
import particlesUrl from 'particles.js/particles.js?url';

const DARK_BLUE = '#1e3a8a';

function loadLibrary() {
    return new Promise((resolve, reject) => {
        if (window.particlesJS) {
            resolve();

            return;
        }

        const script = document.createElement('script');

        script.src = particlesUrl;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

export async function initLoginParticles(root = document) {
    const container = root.querySelector('[data-particles]');

    if (!container) {
        return;
    }

    await loadLibrary();

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const mouse = window.matchMedia('(pointer: fine)').matches;

    window.particlesJS(container.id, {
        particles: {
            number: { value: 120, density: { enable: true, value_area: 900 } },
            color: { value: DARK_BLUE },
            shape: { type: 'circle' },
            opacity: { value: 0.9, random: false },
            size: { value: 3.5, random: true },
            line_linked: { enable: true, distance: 150, color: DARK_BLUE, opacity: 0.45, width: 1 },
            move: { enable: !still, speed: 1.6, direction: 'none', random: false, straight: false, out_mode: 'out', bounce: false },
        },
        interactivity: {
            // "window", because the canvas sits behind the form and never receives mouse events itself.
            detect_on: 'window',
            events: {
                onhover: { enable: mouse && !still, mode: 'grab' },
                onclick: { enable: false },
                resize: true,
            },
            modes: {
                grab: { distance: 190, line_linked: { opacity: 0.85 } },
            },
        },
        retina_detect: true,
    });

    const instance = window.pJSDom[window.pJSDom.length - 1]?.pJS;

    if (!instance || !mouse || still) {
        return;
    }

    // In "window" mode the library takes the cursor position relative to the window, but this canvas only covers
    // the right half of the page. This listener runs after the library's own and corrects the position.
    window.addEventListener('mousemove', (event) => {
        const box = instance.canvas.el.getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;

        if (!inside) {
            instance.interactivity.mouse.pos_x = null;
            instance.interactivity.mouse.pos_y = null;
            instance.interactivity.status = 'mouseleave';

            return;
        }

        instance.interactivity.mouse.pos_x = (event.clientX - box.left) * instance.canvas.pxratio;
        instance.interactivity.mouse.pos_y = (event.clientY - box.top) * instance.canvas.pxratio;
        instance.interactivity.status = 'mousemove';
    });
}
