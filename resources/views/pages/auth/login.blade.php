@extends('layouts.auth')

@section('title', 'Login')

@php($slides = array_values(array_filter(config('adminator.login.slides', []), fn (string $slide): bool => is_file(public_path($slide)))))

@section('content')
<div class="auth-shell">
    {{-- tsParticles draws into this box (resources/js/login-particles.js). It spans the whole page, so the
         particles also show around the rounded photo card. --}}
    <div id="login-particles" class="auth-particles" data-particles aria-hidden="true"></div>

    <aside @class(['auth-aside', 'has-slides' => $slides !== []])>
        @if ($slides !== [])
            {{-- School photos behind the panel; resources/js/login-slides.js fades to the next one. --}}
            <div class="auth-slides" data-slides data-slides-interval="{{ config('adminator.login.slide_interval') }}" aria-hidden="true">
                @foreach ($slides as $slide)
                    @if ($loop->first)
                        <div class="auth-slide is-active" style="background-image:url('{{ asset($slide) }}')"></div>
                    @else
                        <div class="auth-slide" data-slide-src="{{ asset($slide) }}"></div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="auth-brand">
            <x-brand-logo class="logo" />
            <div class="name">{{ config('adminator.brand.name') }}</div>
        </div>

        <div class="auth-aside-body">
            <span class="auth-aside-eyebrow">{{ config('adminator.brand.tagline') }}</span>
            <h1>Absensi siswa jadi lebih rapi dan cepat.</h1>
            <p>Satu aplikasi untuk admin sekolah dan guru: kelola data kelas dan siswa, isi absensi harian, hingga rekap kehadiran per mata pelajaran.</p>
        </div>

        <div class="auth-aside-footer">
            <span>© {{ date('Y') }}</span> <span>{{ config('app.name') }}</span>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-main-top"></div>

        <div class="auth-card">
            <div class="auth-school">
                <x-brand-logo class="auth-school-logo" :size="76" :fallback="false" />
                <div class="auth-school-name">{{ config('adminator.brand.name') }}</div>
            </div>

            <h2>Selamat datang</h2>
            <p class="sub">Masuk sebagai admin atau guru menggunakan akun yang terdaftar.</p>

            <form class="auth-form" method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="field">
                    <label class="field-label" for="email">Email</label>
                    <div class="input-icon">
                        <span class="ico"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nama@sekolah.sch.id" autocomplete="email" required autofocus
                            @class(['input', 'is-invalid' => $errors->has('email')])>
                    </div>
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="password">Password</label>
                    <div class="input-icon">
                        <span class="ico"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                        <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required
                            @class(['input', 'is-invalid' => $errors->has('password')])>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <label class="check">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span class="box"></span> Ingat saya
                </label>

                <button class="btn btn--primary auth-submit" type="submit">
                    Masuk <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </button>
            </form>
        </div>

        <div class="auth-main-bottom">
            Lupa password? Hubungi administrator sekolah.
            {{-- Shown by resources/js/pwa.js once the browser allows installing the app. --}}
            <button type="button" class="pwa-install" data-pwa-install hidden>
                <svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg> Pasang aplikasi di perangkat ini
            </button>
        </div>
    </main>
</div>

{{-- Pauses and resumes the slideshow and the particles (resources/js/login-motion.js). --}}
<button type="button" class="motion-toggle" data-motion-toggle aria-pressed="false" aria-label="Jeda animasi">
    <svg class="icon-pause" viewBox="0 0 24 24"><path d="M9 5v14M15 5v14"/></svg>
    <svg class="icon-play" viewBox="0 0 24 24"><path d="M7 4.5v15l12-7.5z"/></svg>
</button>

@include('partials.login-chatbot')
@endsection
