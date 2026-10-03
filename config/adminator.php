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
