<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>

{{-- Adminator bundles: runtime must load first, 2026.js last (all deferred, order preserved). --}}
<script defer src="{{ asset('adminator/js/runtime.js') }}"></script>
<script defer src="{{ asset('adminator/js/vendor-fullcalendar.js') }}"></script>
<script defer src="{{ asset('adminator/js/vendor-chartjs.js') }}"></script>
<script defer src="{{ asset('adminator/js/vendors.js') }}"></script>
<script defer src="{{ asset('adminator/js/2026.js') }}"></script>
<link href="{{ asset('adminator/css/style.css') }}" rel="stylesheet">

@vite('resources/js/admin.js')
@stack('styles')
