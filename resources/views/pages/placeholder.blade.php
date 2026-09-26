{{-- Menu items whose feature is not built yet. Expects $title. --}}
@extends('layouts.admin')

@section('title', $title)
@section('breadcrumbs', auth()->user()->role->label().' | '.$title)

@section('content')
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Belum tersedia</span>
        <h1 class="hero-title">{{ $title }}</h1>
        <p class="hero-sub">Fitur ini belum dibuat.</p>
    </div>
</section>
@endsection
