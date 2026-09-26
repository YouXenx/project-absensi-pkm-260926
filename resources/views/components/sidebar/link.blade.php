{{--
    Sidebar menu item.
    <x-sidebar.link route="admin.siswa.index" label="Data Siswa"><path d="..."/></x-sidebar.link>
    The slot holds the inner SVG markup (24x24 viewBox). "active" defaults to the route name.
--}}
@props(['route', 'label', 'active' => null])

<a @class(['nav-link', 'is-active' => request()->routeIs($active ?? $route)]) href="{{ route($route) }}">
    <svg viewBox="0 0 24 24">{{ $slot }}</svg>
    <span>{{ $label }}</span>
</a>
