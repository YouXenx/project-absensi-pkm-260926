{{--
    Shown by the service worker (public/sw.js) when a page is requested without a connection.
    Self-contained on purpose: it is stored on the device and cannot rely on other files being available.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="{{ config('adminator.pwa.theme_color') }}">
    <title>Sedang offline - {{ config('app.name') }}</title>
    <style>
        body { align-items: center; background: #f0f4f8; color: #1e293b; display: flex; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; justify-content: center; margin: 0; min-height: 100vh; padding: 24px; text-align: center; }
        main { max-width: 360px; }
        img { border-radius: 50%; height: 88px; width: 88px; }
        h1 { font-size: 22px; margin: 20px 0 8px; }
        p { color: #64748b; font-size: 14.5px; line-height: 1.6; margin: 0 0 22px; }
        button { background: #2563eb; border: 0; border-radius: 10px; color: #fff; cursor: pointer; font: 600 14px/1 inherit; padding: 13px 22px; }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/pwa/icon-192.png') }}" alt="Logo {{ config('app.name') }}" width="88" height="88">
        <h1>Sedang offline</h1>
        <p>Aplikasi absensi membutuhkan koneksi internet. Periksa koneksi Anda, lalu coba lagi.</p>
        <button type="button" onclick="location.reload()">Coba lagi</button>
    </main>
</body>
</html>
