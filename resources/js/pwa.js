/**
 * PWA: registers the service worker (public/sw.js) and wires the optional "install" button
 * ([data-pwa-install], hidden until the browser says the app can be installed).
 */
export function initPwa() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // No service worker (e.g. plain http on a phone): the app works as a normal website.
        });
    });

    const button = document.querySelector('[data-pwa-install]');
    let prompt = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        // Keep the browser's own mini banner back; the button below offers the same thing.
        event.preventDefault();
        prompt = event;

        if (button) {
            button.hidden = false;
        }
    });

    button?.addEventListener('click', async () => {
        if (!prompt) {
            return;
        }

        prompt.prompt();
        await prompt.userChoice;
        prompt = null;
        button.hidden = true;
    });

    window.addEventListener('appinstalled', () => {
        if (button) {
            button.hidden = true;
        }
    });
}
