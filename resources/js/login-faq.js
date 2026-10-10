/**
 * Login page: the FAQ modal ([data-faq], a native <dialog>). The content is rendered by partials/login-faq and
 * the browser handles focus and Escape. This script opens and closes the dialog, and makes the topics slide
 * open and shut instead of jumping (a plain <details> shows and hides its content instantly).
 */

const DURATION = 260;

function bindSmoothTopics(dialog) {
    const topics = [...dialog.querySelectorAll('details')];
    const instant = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const closing = new WeakMap();

    const collapse = (topic) => {
        if (!topic.classList.contains('is-expanded')) {
            return;
        }

        topic.classList.remove('is-expanded');
        // The answer can only be hidden for real once it has finished sliding shut.
        closing.set(topic, setTimeout(() => (topic.open = false), instant ? 0 : DURATION));
    };

    const expand = (topic) => {
        clearTimeout(closing.get(topic));
        topic.open = true;
        // Wait a frame so the browser draws the closed state first; otherwise there is nothing to animate from.
        requestAnimationFrame(() => requestAnimationFrame(() => topic.classList.add('is-expanded')));
    };

    topics.forEach((topic) => {
        // With a shared name the browser closes the other topics at once, without the animation.
        topic.removeAttribute('name');
        topic.classList.toggle('is-expanded', topic.open);

        topic.querySelector('summary').addEventListener('click', (event) => {
            event.preventDefault();

            if (topic.classList.contains('is-expanded')) {
                collapse(topic);

                return;
            }

            topics.filter((other) => other !== topic).forEach(collapse);
            expand(topic);
        });
    });

    // From here on the stylesheet animates by the is-expanded class instead of the open attribute.
    dialog.classList.add('is-animated');
}

export function initLoginFaq(root = document) {
    const dialog = root.querySelector('[data-faq]');

    if (!dialog?.showModal) {
        return;
    }

    bindSmoothTopics(dialog);

    root.querySelector('[data-faq-open]')?.addEventListener('click', () => dialog.showModal());
    dialog.querySelector('[data-faq-close]')?.addEventListener('click', () => dialog.close());

    // A click on the dimmed area around the modal lands on the <dialog> element itself.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
}
