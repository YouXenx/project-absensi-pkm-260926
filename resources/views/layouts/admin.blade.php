<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
    <div class="shell">
        @include('partials.sidebar')

        <div class="main">
            @include('partials.navbar')

            <main class="content">
                @yield('content')
            </main>

            @include('partials.footer')
        </div>
    </div>

    @include('partials.flash')
    @stack('scripts')
</body>
</html>
