/**
 * Layout behaviour that used to come from Adminator's 640 KB of bundles (2026.js + vendors), of which the app only
 * used two things: the mobile sidebar drawer and the topbar dropdown. Class names match Adminator's stylesheet.
 */

function bindDrawer() {
    const body = document.body;

    if (!document.querySelector('.d-sidebar')) {
        return;
    }

    const close = () => body.classList.remove('has-drawer-open');
    const backdrop = document.createElement('div');

    backdrop.className = 'drawer-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.addEventListener('click', close);
    body.appendChild(backdrop);

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-drawer-open]')) {
            event.preventDefault();
            body.classList.add('has-drawer-open');

            return;
        }

        // Following a menu link closes the drawer before the next page shows.
        if (body.classList.contains('has-drawer-open') && event.target.closest('.d-sidebar a[href]')) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => event.key === 'Escape' && close());
    window.matchMedia('(min-width: 901px)').addEventListener('change', (query) => query.matches && close());
}

function bindDropdowns() {
    const closeAll = (except) => {
        document.querySelectorAll('.dd-wrap.is-open').forEach((wrap) => wrap !== except && wrap.classList.remove('is-open'));
    };

    const toggle = (trigger) => {
        const wrap = trigger.closest('.dd-wrap');

        if (!wrap) {
            return;
        }

        const open = !wrap.classList.contains('is-open');

        closeAll(wrap);
        wrap.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', String(open));
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-dropdown]');

        if (trigger) {
            toggle(trigger);
        } else if (!event.target.closest('.dd-wrap')) {
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        const trigger = event.target.closest?.('[data-dropdown]');

        if (event.key === 'Escape') {
            closeAll();
        } else if (trigger && (event.key === 'Enter' || event.key === ' ')) {
            // The avatar is a div with role="button", so it needs the keyboard activation a real button has.
            event.preventDefault();
            toggle(trigger);
        }
    });
}

export function bindShell() {
    bindDrawer();
    bindDropdowns();
}
