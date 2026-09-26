{{--
    Hands Laravel session flash messages to resources/js/flash.js (SweetAlert2).

    From a controller:
        return redirect()->route('dashboard')->with('success', 'Data tersimpan.');
        return back()->with('error', 'Gagal menyimpan.');
        return back()->with('alert', ['icon' => 'info', 'title' => 'Info', 'text' => '...', 'toast' => false]);

    Supported keys: success, error, warning, info, question, alert (custom options).
    Validation errors ($errors) are shown as a single error dialog.
--}}
@php
    $flashMessages = collect(['success', 'error', 'warning', 'info', 'question'])
        ->filter(fn (string $type): bool => session()->has($type))
        ->map(fn (string $type): array => ['icon' => $type, 'text' => session($type)])
        ->values();

    if (session()->has('alert')) {
        $flashMessages->push((array) session('alert'));
    }

    // The same message can repeat for many rows (e.g. a status missing on several students); show it once.
    $errorMessages = collect($errors->all())->unique()->values();

    if ($errorMessages->count() === 1) {
        $flashMessages->push([
            'icon' => 'error',
            'title' => 'Gagal',
            'text' => $errorMessages->first(),
            'toast' => false,
        ]);
    } elseif ($errors->any()) {
        $flashMessages->push([
            'icon' => 'error',
            'title' => 'Periksa kembali input Anda',
            'html' => '<ul style="text-align:left;margin:0;padding-left:1.2em">'.$errorMessages->map(fn (string $message): string => '<li>'.e($message).'</li>')->implode('').'</ul>',
            'toast' => false,
        ]);
    }
@endphp

@if ($flashMessages->isNotEmpty())
    <script type="application/json" id="flash-messages">@json($flashMessages)</script>
@endif
