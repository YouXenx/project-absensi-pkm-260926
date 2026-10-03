<?php

/**
 * FAQ help widget on the login page (resources/views/partials/login-chatbot.blade.php).
 *
 * Everything the widget says lives here: it is a fixed list of questions and answers, not an AI and not
 * connected to user data. Edit the texts below, or set the admin contact in .env.
 */
return [

    'greeting' => [
        'Halo! Ada kendala login?',
        'Pilih salah satu pertanyaan di bawah ini.',
    ],

    /*
    | Admin contact shown by the widget. Leave a value empty to hide it.
    | CHATBOT_WHATSAPP: the number as people write it (0812-3456-7890 or +62 812 3456 7890); it is converted
    | to the wa.me format automatically.
    */
    'contact' => [
        'whatsapp' => env('CHATBOT_WHATSAPP'),
        'whatsapp_message' => 'Halo Admin, saya mengalami kendala login di aplikasi absensi siswa.',
        'email' => env('CHATBOT_EMAIL'),
    ],

    /*
    | One entry per quick-reply button. "answer" is a list of chat bubbles.
    | "shows_contact" => true adds the admin contact above to the answer.
    */
    'questions' => [
        [
            'id' => 'lupa-password',
            'label' => 'Lupa password',
            'answer' => [
                'Reset password mandiri belum tersedia di aplikasi ini.',
                'Hubungi admin sekolah. Admin akan membuatkan password sementara untuk akun Anda lewat menu Data Guru.',
                'Setelah berhasil login, ganti password sementara itu di menu Profil Saya.',
            ],
        ],
        [
            'id' => 'akun-tidak-bisa-login',
            'label' => 'Akun tidak bisa login / dinonaktifkan',
            'answer' => [
                'Periksa dulu email dan password Anda. Password membedakan huruf besar dan kecil.',
                'Jika muncul pesan "Akun Anda dinonaktifkan", hanya admin sekolah yang bisa mengaktifkannya kembali.',
                'Jika muncul pesan "Terlalu banyak percobaan login", tunggu sebentar sesuai waktu yang tertera, lalu coba lagi.',
            ],
        ],
        [
            'id' => 'siswa-tidak-bisa-diabsen',
            'label' => 'Siswa saya tidak bisa diabsen',
            'answer' => [
                'Absensi hanya bisa diisi untuk mata pelajaran yang Anda ampu di tahun ajaran aktif.',
                'Setelah login, buka menu Mapel yang Diampu. Jika mapel atau kelasnya belum muncul, minta admin menugaskan Anda ke mapel tersebut.',
                'Siswa berstatus pindah, keluar, atau lulus memang tidak muncul di daftar absensi.',
            ],
        ],
        [
            'id' => 'kontak-admin',
            'label' => 'Cara lain menghubungi admin',
            'answer' => [
                'Anda bisa menghubungi admin sekolah lewat kontak berikut.',
            ],
            'shows_contact' => true,
        ],
    ],

];
