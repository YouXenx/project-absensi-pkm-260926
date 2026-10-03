@php($currentUser = auth()->user())

<aside class="d-sidebar">
    <div class="brand">
        <x-brand-logo class="brand-logo" />
        <div class="brand-text">
            {{-- Short name: the full school name does not fit the sidebar width. --}}
            <div class="brand-name" title="{{ config('adminator.brand.name') }}">{{ config('adminator.brand.short_name') }}</div>
            <div class="brand-tag">{{ config('adminator.brand.tagline') }}</div>
        </div>
    </div>

    {{-- Server-side switch: only the partial for the signed-in role is ever rendered. --}}
    @if ($currentUser->isAdmin())
        @include('partials.sidebar-admin')
    @elseif ($currentUser->isGuru())
        @include('partials.sidebar-guru')
    @endif

    <div class="sidebar-footer">
        <div class="workspace">
            <div class="workspace-avatar">{{ $currentUser->initials() }}</div>
            <div class="workspace-text">
                <div class="workspace-name">{{ $currentUser->name }}</div>
                <div class="workspace-role">{{ $currentUser->role->label() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="workspace-chev" data-confirm="Keluar dari aplikasi?">
                @csrf
                <button type="submit" class="logout-btn" aria-label="Logout" title="Logout">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                </button>
            </form>
        </div>
    </div>
</aside>
