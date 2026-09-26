<nav class="nav-section">
    <div class="nav-label">Utama</div>

    <x-sidebar.link route="admin.dashboard" label="Dashboard">
        <path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/>
    </x-sidebar.link>
</nav>

<nav class="nav-section">
    <div class="nav-label">Master Data</div>

    <x-sidebar.link route="admin.guru.index" label="Data Guru" active="admin.guru.*">
        <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
    </x-sidebar.link>

    <x-sidebar.link route="admin.kelas.index" label="Data Kelas" active="admin.kelas.*">
        <path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>
    </x-sidebar.link>

    <x-sidebar.link route="admin.siswa.index" label="Data Siswa" active="admin.siswa.*">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </x-sidebar.link>
</nav>

{{-- Ordered as the yearly flow: create year -> promote -> homeroom -> subjects. --}}
<nav class="nav-section">
    <div class="nav-label">Tahun Ajaran</div>

    <x-sidebar.link route="admin.tahun-ajaran.index" label="Tahun Ajaran" active="admin.tahun-ajaran.*">
        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h3"/>
    </x-sidebar.link>

    <x-sidebar.link route="admin.kenaikan.index" label="Kenaikan Kelas" active="admin.kenaikan.*">
        <path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>
    </x-sidebar.link>

    <x-sidebar.link route="admin.wali-kelas.index" label="Wali Kelas" active="admin.wali-kelas.*">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>
    </x-sidebar.link>

    <x-sidebar.link route="admin.mapel.index" label="Mata Pelajaran" active="admin.mapel.*">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
    </x-sidebar.link>
</nav>

<nav class="nav-section">
    <div class="nav-label">Lainnya</div>

    <x-sidebar.link route="admin.absensi.index" label="Koreksi Absensi" active="admin.absensi.*">
        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
    </x-sidebar.link>

    <x-sidebar.link route="admin.laporan.index" label="Rekap Absensi" active="admin.laporan.index">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>
    </x-sidebar.link>
    <x-sidebar.link route="admin.laporan.mapel" label="Rekap per Mapel" active="admin.laporan.mapel">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg>
    </x-sidebar.link>

    <x-sidebar.link route="admin.akun.edit" label="Pengaturan Akun" active="admin.akun.*">
        <circle cx="12" cy="12" r="3"/><path d="M12 1v3M12 20v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M1 12h3M20 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>
    </x-sidebar.link>
</nav>
