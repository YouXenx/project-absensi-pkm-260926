{{--
    School logo from config('adminator.brand.logo').
    <x-brand-logo class="brand-logo" :size="44" />
    "size" is the displayed size in px; it goes on the <img> so the box is right before any stylesheet applies.
    While the file is missing it shows Adminator's letter mark, or nothing with :fallback="false".
--}}
@props(['fallback' => true, 'size' => 44])

@php($logo = config('adminator.brand.logo'))

@if ($logo && is_file(public_path($logo)))
    <div {{ $attributes->class(['has-image']) }}>
        <img src="{{ asset($logo) }}" alt="Logo {{ config('adminator.brand.name') }}" width="{{ $size }}" height="{{ $size }}">
    </div>
@elseif ($fallback)
    <div {{ $attributes }}>
        <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
            <path fill="#ffffff" d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z"/>
        </svg>
    </div>
@endif
