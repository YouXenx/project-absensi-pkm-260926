/**
 * FAQ help widget on the login page ([data-chatbot]). Rule based: a fixed list of questions and answers that
 * arrives with the page (config/chatbot.php), shown as quick-reply buttons. It makes no requests.
 *
 * All text is inserted with textContent, never as HTML.
 */

const TYPING_DELAY = 450;

function element(tag, className, text) {
    const node = document.createElement(tag);

    node.className = className;

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
}

export function initLoginChatbot(root = document) {
    const widget = root.querySelector('[data-chatbot]');

    if (!widget) {
        return;
    }

    const config = JSON.parse(widget.querySelector('[data-chatbot-config]').textContent);
    const panel = widget.querySelector('.chatbot-window');
    const toggle = widget.querySelector('[data-chatbot-toggle]');
    const messages = widget.querySelector('[data-chatbot-messages]');
    const replies = widget.querySelector('[data-chatbot-replies]');
    const instant = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let started = false;

    const scrollDown = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const say = (text, from = 'bot') => {
        messages.append(element('div', `chatbot-bubble is-${from}`, text));
        scrollDown();
    };

    const sayLink = ({ label, url }, prefix) => {
        const bubble = element('div', 'chatbot-bubble is-bot', `${prefix}: `);
        const link = element('a', 'chatbot-link', label);

        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        bubble.append(link);
        messages.append(bubble);
        scrollDown();
    };

    const showMenu = () => {
        replies.replaceChildren(
            ...config.questions.map((question) => {
                const button = element('button', 'chatbot-reply', question.label);

                button.type = 'button';
                button.addEventListener('click', () => answer(question));

                return button;
            }),
        );
        // The buttons take room from the message list, so its last bubbles need scrolling back into view.
        scrollDown();
    };

    const showFollowUp = () => {
        const back = element('button', 'chatbot-reply', 'Kembali ke menu pertanyaan');

        back.type = 'button';
        back.addEventListener('click', () => {
            say('Ada lagi yang bisa dibantu?');
            showMenu();
            replies.querySelector('button')?.focus();
        });

        replies.replaceChildren(back);

        if (config.contact.whatsapp) {
            const whatsapp = element('a', 'chatbot-reply is-whatsapp', 'Hubungi Admin via WhatsApp');

            whatsapp.href = config.contact.whatsapp.url;
            whatsapp.target = '_blank';
            whatsapp.rel = 'noopener noreferrer';
            replies.append(whatsapp);
        }

        back.focus({ preventScroll: true });
        scrollDown();
    };

    const showContact = () => {
        const { whatsapp, email } = config.contact;

        if (whatsapp) {
            sayLink(whatsapp, 'WhatsApp');
        }

        if (email) {
            sayLink(email, 'Email');
        }

        if (!whatsapp && !email) {
            say('Kontak admin belum diisi di aplikasi ini. Silakan temui admin sekolah secara langsung.');
        }
    };

    function answer(question) {
        say(question.label, 'user');
        replies.replaceChildren();

        const typing = element('div', 'chatbot-bubble is-bot is-typing');

        typing.append(element('span', ''), element('span', ''), element('span', ''));
        typing.setAttribute('aria-hidden', 'true');
        messages.append(typing);
        scrollDown();

        setTimeout(
            () => {
                typing.remove();
                question.answer.forEach((line) => say(line));

                if (question.shows_contact) {
                    showContact();
                }

                showFollowUp();
            },
            instant ? 0 : TYPING_DELAY,
        );
    }

    const setOpen = (open) => {
        widget.classList.toggle('is-open', open);
        panel.inert = !open;
        panel.setAttribute('aria-hidden', String(!open));
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Tutup bantuan login' : 'Buka bantuan login');

        if (!open) {
            return;
        }

        if (!started) {
            started = true;
            config.greeting.forEach((line) => say(line));
            showMenu();
        }

        replies.querySelector('button, a')?.focus({ preventScroll: true });
    };

    toggle.addEventListener('click', () => setOpen(!widget.classList.contains('is-open')));

    widget.querySelector('[data-chatbot-close]').addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && widget.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
}
