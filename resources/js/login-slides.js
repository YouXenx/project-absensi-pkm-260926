/**
 * Login page: fades through the school photos behind the left panel ([data-slides]).
 * The interval comes from config/adminator.php (login.slide_interval). Loaded only on the login page.
 */
import { isMotionPaused } from './login-motion';

export function initSlides(root = document) {
    const container = root.querySelector('[data-slides]');
    const slides = container ? [...container.children] : [];

    if (slides.length < 2) {
        return;
    }

    const interval = Number(container.dataset.slidesInterval) || 5000;
    let current = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));

    // Photos after the first are not requested with the page: each one is fetched while the previous is on screen.
    const load = (slide) => {
        if (slide.dataset.slideSrc) {
            slide.style.backgroundImage = `url('${slide.dataset.slideSrc}')`;
            delete slide.dataset.slideSrc;
        }
    };

    if (container.offsetParent !== null) {
        load(slides[(current + 1) % slides.length]);
    }

    setInterval(() => {
        // Paused by the visitor, or the panel is hidden (small screens): do not cycle or download photos.
        if (isMotionPaused() || container.offsetParent === null) {
            return;
        }

        slides[current].classList.remove('is-active');
        current = (current + 1) % slides.length;
        load(slides[current]);
        slides[current].classList.add('is-active');
        load(slides[(current + 1) % slides.length]);
    }, interval);
}
