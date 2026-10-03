{{--
    FAQ help widget, login page only. Fixed questions and answers from config/chatbot.php (passed in as $chatbot by
    LoginController); resources/js/login-chatbot.js runs the conversation. No requests are made: it is plain
    information and has no access to accounts.
--}}
<div class="chatbot" data-chatbot>
    <section id="chatbot-window" class="chatbot-window" role="dialog" aria-label="Bantuan login" aria-hidden="true" inert>
        <header class="chatbot-head">
            <div class="chatbot-avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.6 8.6 0 0 1-3.9-.9L3 21l1.9-5.6A8.4 8.4 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5z"/></svg>
            </div>
            <div class="chatbot-head-text">
                <div class="chatbot-title">Bantuan Login</div>
                <div class="chatbot-sub">Jawaban otomatis</div>
            </div>
            <button type="button" class="chatbot-close" data-chatbot-close aria-label="Tutup bantuan" title="Tutup">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="chatbot-messages" data-chatbot-messages aria-live="polite"></div>
        <div class="chatbot-replies" data-chatbot-replies></div>
    </section>

    <button type="button" class="chatbot-toggle" data-chatbot-toggle aria-expanded="false" aria-controls="chatbot-window" aria-label="Buka bantuan login" title="Bantuan login">
        <svg class="icon-chat" viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.6 8.6 0 0 1-3.9-.9L3 21l1.9-5.6A8.4 8.4 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5z"/></svg>
        <svg class="icon-close" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>

    <script type="application/json" data-chatbot-config>@json($chatbot)</script>
</div>
