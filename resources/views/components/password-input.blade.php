{{--
    Password input with a show/hide (eye) button, wired by resources/js/password-toggle.js.
    <x-password-input id="teacher-modal-password" name="password" autocomplete="new-password" />
    Extra attributes go to the <input>.
--}}
<div class="password-field">
    <input {{ $attributes->merge(['type' => 'password'])->class(['input']) }}>
    <button type="button" class="password-toggle" data-password-toggle aria-pressed="false" aria-label="Tampilkan password" title="Tampilkan password">
        <svg class="icon-show" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="icon-hide" viewBox="0 0 24 24"><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 10 8 10 8a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3 8 10 8a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
    </button>
</div>
