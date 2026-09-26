<?php

/**
 * Branding for the Adminator layout.
 *
 * Sidebar menus live in resources/views/partials/sidebar-admin.blade.php and
 * sidebar-guru.blade.php, so each role only ever renders its own menu.
 */
return [

    'brand' => [
        'name' => env('APP_NAME', 'Adminator'),
        'tagline' => 'Absensi Siswa',
    ],

];
