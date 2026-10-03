<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') - @endif{{ config('app.name') }}</title>
<meta name="description" content="Aplikasi absensi siswa {{ config('app.name') }} untuk admin sekolah dan guru.">
<meta name="application-name" content="{{ config('app.name') }}">
{{-- Home-screen labels are cut off after a few words, so they get the short name. --}}
<meta name="apple-mobile-web-app-title" content="{{ config('adminator.brand.short_name') }}">

@if (is_file(public_path(config('adminator.brand.favicon'))))
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset(config('adminator.brand.favicon')) }}">
    <link rel="apple-touch-icon" href="{{ asset(config('adminator.brand.touch_icon')) }}">
@endif

{{-- Inter, Inter Tight and JetBrains Mono, self-hosted by the Vite fonts plugin (vite.config.js): preloads + @font-face. --}}
@fonts

<link href="{{ asset('adminator/css/style.css') }}" rel="stylesheet">

{{-- admin.css is its own entry so it arrives as a render-blocking <link>, also in dev mode. Imported from the
     script it was injected after the first paint, and the logo and toolbars jumped into place on every load. --}}
@vite(['resources/css/admin.css', 'resources/js/admin.js'])
@stack('styles')
