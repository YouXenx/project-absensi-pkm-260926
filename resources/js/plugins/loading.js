/**
 * Full-page loading overlay for work that blocks the user (a page form posting many rows, an Ajax row action).
 * Modal forms and DataTables have their own local busy states (see modal.js and the .dt-processing styles).
 *
 *   showLoading('Memproses kenaikan kelas…');  hideLoading();
 *   <form data-loading="Menyimpan absensi…">   shows the overlay once the form really submits (after any confirmation)
 */

let overlay = null;
let pending = 0;
let showTimer = null;

function element() {
    if (overlay) {
        return overlay;
    }

    overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.hidden = true;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML = '<div class="loading-box"><span class="spinner"></span><span class="loading-text"></span></div>';
    document.body.append(overlay);

    return overlay;
}

/**
 * @param {string} text
 * @param {{ delay?: number }} options  delay (ms) avoids a flash for requests that finish quickly
 */
export function showLoading(text = 'Memproses…', { delay = 0 } = {}) {
    pending += 1;
    element().querySelector('.loading-text').textContent = text;

    clearTimeout(showTimer);
    showTimer = setTimeout(() => {
        if (pending > 0) {
            element().hidden = false;
            document.body.setAttribute('aria-busy', 'true');
        }
    }, delay);
}

export function hideLoading() {
    pending = Math.max(0, pending - 1);

    if (pending === 0) {
        clearTimeout(showTimer);
        element().hidden = true;
        document.body.removeAttribute('aria-busy');
    }
}

export async function withLoading(callback, text, options) {
    showLoading(text, options);

    try {
        return await callback();
    } finally {
        hideLoading();
    }
}

export function bindLoadingForms() {
    // Registered after the confirmation handler, so a submit it cancelled (to ask first) is ignored here.
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!event.defaultPrevented && form instanceof HTMLFormElement && form.dataset.loading) {
            pending = 0;
            showLoading(form.dataset.loading);
        }
    });

    // Coming back with the browser's back button restores the page from cache with the overlay still visible.
    window.addEventListener('pageshow', () => {
        pending = 0;
        clearTimeout(showTimer);

        if (overlay) {
            overlay.hidden = true;
        }
    });
}
