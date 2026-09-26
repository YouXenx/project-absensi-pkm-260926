@extends('layouts.auth')

@section('title', 'Login')

@section('content')
<div class="auth-shell">
    <aside class="auth-aside">
        <div class="auth-brand">
            <div class="logo">
                <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z"/></svg>
            </div>
            <div class="name">{{ config('adminator.brand.name') }}</div>
        </div>

        <div class="auth-aside-body">
            <span class="auth-aside-eyebrow">{{ config('adminator.brand.tagline') }}</span>
            <h1>Absensi siswa jadi lebih rapi dan cepat.</h1>
            <p>Satu aplikasi untuk admin sekolah dan guru: kelola data kelas, jadwal, absensi, hingga nilai siswa.</p>
        </div>

        <div class="auth-aside-footer">
            <span>© {{ date('Y') }}</span> <span>{{ config('app.name') }}</span>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-main-top"></div>

        <div class="auth-card">
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

        <div class="auth-main-bottom">Lupa password? Hubungi administrator sekolah.</div>
    </main>
</div>
@endsection
