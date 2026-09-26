{{-- Own account settings for either role. Expects $user (always the signed-in user). --}}
@extends('layouts.admin')

@php($pageTitle = $user->isAdmin() ? 'Pengaturan Akun' : 'Profil Saya')
@php($updateRoute = $user->isAdmin() ? 'admin.akun.update' : 'guru.profil.update')

@section('title', $pageTitle)
@section('breadcrumbs', $user->role->label().' | '.$pageTitle)

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">{{ $user->role->label() }}</span>
        <h1 class="hero-title">{{ $pageTitle }}</h1>
        <p class="hero-sub">Perbarui nama, email, atau password akun Anda.</p>
    </div>
</section>

<section class="card">
    <form method="POST" action="{{ route($updateRoute) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="name">Nama Lengkap <span class="req">*</span></label>
                <input id="name" name="name" type="text" maxlength="255" required
                    value="{{ old('name', $user->name) }}"
                    @class(['input', 'is-invalid' => $errors->has('name')])>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label class="field-label" for="email">Email <span class="req">*</span></label>
                <input id="email" name="email" type="email" maxlength="255" required
                    value="{{ old('email', $user->email) }}"
                    @class(['input', 'is-invalid' => $errors->has('email')])>
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <h3 class="section-h" style="margin-top:28px">Ganti Password</h3>

        <div class="form-grid">
            <div class="field span-2">
                <label class="field-label" for="current_password">Password Saat Ini</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                    @class(['input', 'is-invalid' => $errors->has('current_password')])>
                <div class="field-help">Wajib diisi hanya jika Anda mengganti password.</div>
                @error('current_password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label class="field-label" for="password">Password Baru</label>
                <input id="password" name="password" type="password" autocomplete="new-password"
                    @class(['input', 'is-invalid' => $errors->has('password')])>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label class="field-label" for="password_confirmation">Konfirmasi Password Baru</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input">
            </div>
        </div>

        <div class="form-actions">
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Simpan Perubahan</button>
        </div>
    </form>
</section>
@endsection
