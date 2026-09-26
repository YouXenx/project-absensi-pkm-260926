<nav class="nav-section">
    <div class="nav-label">Utama</div>

    <x-sidebar.link route="guru.dashboard" label="Dashboard">
        <path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/>
    </x-sidebar.link>
</nav>

<nav class="nav-section">
    <div class="nav-label">Mengajar</div>

    <x-sidebar.link route="guru.mapel.index" label="Mapel yang Diampu" active="guru.mapel.*">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
    </x-sidebar.link>

    <x-sidebar.link route="guru.absensi.index" label="Absensi Siswa" active="guru.absensi.*">
        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
    </x-sidebar.link>

    <x-sidebar.link route="guru.riwayat.index" label="Riwayat Absensi" active="guru.riwayat.*">
        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>
    </x-sidebar.link>

    <x-sidebar.link route="guru.nilai.index" label="Input Nilai" active="guru.nilai.*">
        <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/>
    </x-sidebar.link>

    <x-sidebar.link route="guru.rekap.index" label="Rekap Absensi Mapel" active="guru.rekap.index">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>
    </x-sidebar.link>
    <x-sidebar.link route="guru.rekap.mapel" label="Rekap per Mapel Saya" active="guru.rekap.mapel">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg>
    </x-sidebar.link>
</nav>

<nav class="nav-section">
    <div class="nav-label">Akun</div>

    <x-sidebar.link route="guru.profil.edit" label="Profil Saya" active="guru.profil.*">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
    </x-sidebar.link>
</nav>
