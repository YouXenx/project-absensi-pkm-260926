{{--
    FAQ on the login page: a short tutorial on how to log in, in a modal. Texts come from config/faq.php (passed
    in as $faq by LoginController). Everything is rendered here; resources/js/login-faq.js only opens and closes
    the dialog. Static information, with no access to accounts.
--}}
<button type="button" class="faq-toggle" data-faq-open aria-haspopup="dialog" aria-controls="login-faq" aria-label="Buka {{ $faq['title'] }}" title="{{ $faq['title'] }}">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.2 9.2a2.9 2.9 0 0 1 5.6 1c0 1.9-2.8 2.4-2.8 4.1"/><path d="M12 17.4h.01"/></svg>
</button>

<dialog id="login-faq" class="faq-modal" data-faq aria-labelledby="login-faq-title">
    <header class="faq-head">
        <div class="faq-head-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.2 9.2a2.9 2.9 0 0 1 5.6 1c0 1.9-2.8 2.4-2.8 4.1"/><path d="M12 17.4h.01"/></svg>
        </div>
        <div class="faq-head-text">
            <h2 class="faq-title" id="login-faq-title">{{ $faq['title'] }}</h2>
            <div class="faq-sub">{{ $faq['subtitle'] }}</div>
        </div>
        <button type="button" class="faq-close" data-faq-close aria-label="Tutup {{ $faq['title'] }}" title="Tutup">
            <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </header>

    <div class="faq-body">
        @foreach ($faq['questions'] as $question)
            {{-- The shared name makes the browser close the other topics when one is opened. --}}
            <details class="faq-item" name="login-faq" @if ($loop->first) open @endif>
                <summary>
                    {{ $question['label'] }}
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                {{-- Two boxes: the outer one is animated open and shut, the inner one holds the text. --}}
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        @foreach ($question['answer'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    @if ($faq['contact']['whatsapp'] || $faq['contact']['email'])
        <footer class="faq-foot">
            <span class="faq-foot-label">Masih butuh bantuan? Hubungi admin sekolah.</span>
            @if ($faq['contact']['whatsapp'])
                <a class="faq-contact is-whatsapp" href="{{ $faq['contact']['whatsapp']['url'] }}" target="_blank" rel="noopener noreferrer">WhatsApp {{ $faq['contact']['whatsapp']['label'] }}</a>
            @endif
            @if ($faq['contact']['email'])
                <a class="faq-contact is-email" href="{{ $faq['contact']['email']['url'] }}">{{ $faq['contact']['email']['label'] }}</a>
            @endif
        </footer>
    @endif
</dialog>
