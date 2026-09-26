@php($currentUser = auth()->user())

<aside class="d-sidebar">
    <div class="brand">
        <div class="brand-logo">
            <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
                <path fill="#ffffff" d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z"/>
            </svg>
        </div>
        <div class="brand-text">
            <div class="brand-name">{{ config('adminator.brand.name') }}</div>
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
