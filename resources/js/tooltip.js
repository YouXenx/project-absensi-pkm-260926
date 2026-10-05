/**
 * Small hover label for icon-only buttons ("Hapus", "Edit", ...). The text comes from the button's own title or
 * aria-label, so every icon button gets one without extra markup, including rows DataTables adds later.
 * Add data-tooltip="..." to give any other element a label.
 *
 * One element is positioned over the page (position: fixed), so a label is never cut off by a scrolling table.
 * Mouse and keyboard only: on touch screens a label would appear after the tap, when it is no longer useful.
 */

const TARGETS = 'button, a, [role="button"], [data-tooltip]';
const DELAY = 120;
const GAP = 8;

function labelFor(element) {
    if (element.dataset.tooltip) {
        return element.dataset.tooltip;
    }

    // Buttons that already show text need no label.
    if (element.innerText.trim() !== '' || !element.querySelector('svg, img')) {
        return '';
    }

    // textContent covers labels that are in the markup but hidden, like the menu names of the collapsed sidebar.
    return (
        element.getAttribute('title') ||
        element.dataset.tooltipTitle ||
        element.getAttribute('aria-label') ||
        element.textContent.trim().replace(/\s+/g, ' ')
    );
}

export function bindTooltips() {
    const bubble = document.createElement('div');
    let current = null;
    let timer = null;

    bubble.className = 'tooltip-bubble';
    bubble.setAttribute('role', 'tooltip');
    bubble.hidden = true;
    document.body.appendChild(bubble);

    const hide = () => {
        clearTimeout(timer);
        bubble.hidden = true;
        bubble.classList.remove('is-visible');
        current = null;
    };

    const place = (element) => {
        const box = element.getBoundingClientRect();
        const width = bubble.offsetWidth;
        const height = bubble.offsetHeight;
        const above = box.top - height - GAP >= 4;
        const left = Math.min(Math.max(box.left + box.width / 2 - width / 2, 6), window.innerWidth - width - 6);

        bubble.style.left = `${left}px`;
        bubble.style.top = `${above ? box.top - height - GAP : box.bottom + GAP}px`;
        bubble.classList.toggle('is-below', !above);
        // The arrow keeps pointing at the button when the bubble was pushed away from a screen edge.
        bubble.style.setProperty('--arrow', `${box.left + box.width / 2 - left}px`);
    };

    const show = (element) => {
        const text = labelFor(element);

        if (!text || element === current) {
            return;
        }

        hide();
        current = element;

        // The browser's own tooltip would appear a second later on top of this one.
        if (element.hasAttribute('title')) {
            element.dataset.tooltipTitle = element.getAttribute('title');
            element.removeAttribute('title');
        }

        timer = setTimeout(() => {
            if (!element.isConnected) {
                hide();

                return;
            }

            bubble.textContent = text;
            bubble.hidden = false;
            place(element);
            bubble.classList.add('is-visible');
        }, DELAY);
    };

    document.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        const element = event.target.closest?.(TARGETS);

        if (element) {
            show(element);
        } else if (current) {
            hide();
        }
    });

    document.addEventListener('focusin', (event) => {
        const element = event.target.closest?.(TARGETS);

        if (element?.matches(':focus-visible')) {
            show(element);
        }
    });

    document.addEventListener('focusout', hide);
    document.addEventListener('pointerdown', hide);
    document.addEventListener('keydown', (event) => event.key === 'Escape' && hide());
    window.addEventListener('scroll', hide, { capture: true, passive: true });
    document.addEventListener('pointerleave', hide);

    // A table redraw can remove the button under the cursor without any "leave" event.
    document.addEventListener('pointermove', () => {
        if (current && !current.isConnected) {
            hide();
        }
    });
}
