<?php

/**
 * Branding for the Adminator layout.
 *
 * Sidebar menus live in resources/views/partials/sidebar-admin.blade.php and
 * sidebar-guru.blade.php, so each role only ever renders its own menu.
 */
return [

    'brand' => [
        'name' => env('APP_NAME', 'Bina Indonesia Gemilang Boarding School'),

        // Used where the full name does not fit: the 248px sidebar and the mobile login header.
        'short_name' => 'BIG Boarding School',

        'tagline' => 'Absensi Siswa',

        // Paths relative to public/. The logo shown on screen and the favicons are small copies of the
        // original, images/logo-big.jpg (918px, 160 KB), which is too heavy for a 44px mark.
        'logo' => 'images/logo-192.webp',
        'favicon' => 'images/favicon-32.png',
        'touch_icon' => 'images/apple-touch-icon.png',
    ],

    // Installable app (PWA): /manifest.webmanifest is built from these values and the brand above.
    // The icons in public/images/pwa are generated from images/logo-big.jpg.
    'pwa' => [
        'theme_color' => '#2563eb',
        'background_color' => '#f0f4f8',
        'icons' => [
            ['src' => 'images/pwa/icon-192.png', 'sizes' => '192x192', 'purpose' => 'any'],
            ['src' => 'images/pwa/icon-512.png', 'sizes' => '512x512', 'purpose' => 'any'],
            ['src' => 'images/pwa/icon-maskable-192.png', 'sizes' => '192x192', 'purpose' => 'maskable'],
            ['src' => 'images/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'purpose' => 'maskable'],
        ],
    ],

    // Photos behind the left panel of the login page (paths relative to public/), shown one after another.
    'login' => [
        'slides' => [
            'images/login/slide-1.jpg',
            'images/login/slide-2.jpg',
            'images/login/slide-3.jpg',
            'images/login/slide-4.jpg',
        ],
        'slide_interval' => 3000,
    ],

];
