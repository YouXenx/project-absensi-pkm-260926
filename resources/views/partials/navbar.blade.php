{{-- Breadcrumbs come from the page: @section('breadcrumbs', 'Master Data | Data Siswa'). The short @section form already escapes the value. --}}
@php
    $breadcrumbs = array_values(array_filter(array_map('trim', explode('|', $__env->yieldContent('breadcrumbs')))));
    $currentUser = auth()->user();
    $profileRoute = $currentUser->isAdmin() ? 'admin.akun.edit' : 'guru.profil.edit';
@endphp

<header class="d-topbar">
    <div class="crumbs">
        <button class="hamburger" data-drawer-open aria-label="Buka navigasi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        @foreach ($breadcrumbs as $crumb)
            @unless ($loop->first)
                <svg class="sep" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            @endunless
            <span @class(['current' => $loop->last])>{!! $crumb !!}</span>
        @endforeach
    </div>

    <div class="topbar-actions">
        <div class="dd-wrap">
            <div class="avatar" data-dropdown tabindex="0" role="button" aria-label="Menu akun">{{ $currentUser->initials() }}</div>
            <div class="dd-menu dd-profile" role="menu">
                <div class="dd-profile-head">
                    <div class="dd-profile-name">{{ $currentUser->name }}</div>
                    <div class="dd-profile-email">{{ $currentUser->email }} · {{ $currentUser->role->label() }}</div>
                </div>
                <a class="dd-menu-item" href="{{ route($profileRoute) }}">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ $currentUser->isAdmin() ? 'Pengaturan Akun' : 'Profil Saya' }}
                </a>
                <div class="dd-divider"></div>
                <form method="POST" action="{{ route('logout') }}" data-confirm="Keluar dari aplikasi?">
                    @csrf
                    <button type="submit" class="dd-menu-item danger logout-btn">
                        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
