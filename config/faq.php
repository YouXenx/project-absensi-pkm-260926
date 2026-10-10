<?php

/**
 * FAQ on the login page (resources/views/partials/login-faq.blade.php): a short tutorial on how to log in,
 * shown in a modal as a list of topics that open and close. Static text, not connected to user data.
 * Edit the texts below, or set the admin contact in .env.
 */
return [

    'title' => 'FAQ',
    'subtitle' => 'Panduan login',

    /*
    | Admin contact shown at the bottom of the modal. Leave a value empty to hide it.
    | FAQ_WHATSAPP: the number as people write it (0812-3456-7890 or +62 812 3456 7890); it is converted to the
    | wa.me format automatically.
    */
    'contact' => [
        'whatsapp' => env('FAQ_WHATSAPP'),
        'whatsapp_message' => 'Halo Admin, saya mengalami kendala login di aplikasi absensi siswa.',
        'email' => env('FAQ_EMAIL'),
    ],

    // One entry per topic. "answer" is a list of paragraphs.
    'questions' => [
        [
            'id' => 'cara-login',
            'label' => 'Cara login',
            'answer' => [
                '1. Ketik email akun Anda di kolom Email, misalnya nama@sekolah.sch.id.',
                '2. Ketik password di kolom Password. Huruf besar dan kecil dibedakan.',
                '3. Tekan tombol Masuk.',
                'Jika email dan password benar, Anda langsung dibawa ke dashboard.',
            ],
        ],
        [
            'id' => 'akun-dari-mana',
            'label' => 'Dari mana saya mendapat akun?',
            'answer' => [
                'Akun tidak dibuat sendiri. Admin sekolah yang mendaftarkan guru lewat menu Data Guru.',
                'Email dan password Anda diberikan oleh admin sekolah.',
            ],
        ],
        [
            'id' => 'ingat-saya',
            'label' => 'Apa fungsi "Ingat saya"?',
            'answer' => [
                'Jika dicentang, Anda tetap masuk di perangkat ini walaupun browser ditutup.',
                'Centang hanya di ponsel atau laptop pribadi. Jangan dicentang di komputer yang dipakai bersama.',
            ],
        ],
        [
            'id' => 'setelah-login',
            'label' => 'Setelah login, ke mana?',
            'answer' => [
                'Guru masuk ke Dashboard Guru. Dari menu di sebelah kiri: Mapel yang Diampu, Absensi Siswa, Riwayat Absensi, dan rekap.',
                'Admin masuk ke Dashboard Admin, dengan menu Master Data, Tahun Ajaran, Koreksi Absensi, dan Rekap Absensi.',
                'Di ponsel, menu dibuka lewat tombol garis tiga di pojok kiri atas.',
            ],
        ],
        [
            'id' => 'cara-keluar',
            'label' => 'Cara keluar (logout)',
            'answer' => [
                'Tekan ikon keluar di bagian bawah menu, di samping nama Anda.',
                'Atau tekan lingkaran berisi inisial nama di pojok kanan atas, lalu pilih Logout.',
                'Selalu logout setelah selesai jika memakai perangkat bersama.',
            ],
        ],
    ],

];
