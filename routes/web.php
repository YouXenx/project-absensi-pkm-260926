<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HomeroomTeacherController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\PromotionImportController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\AttendanceRecapController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Guru\AttendanceController as GuruAttendanceController;
use App\Http\Controllers\Guru\DashboardController as GuruDashboardController;
use App\Http\Controllers\Guru\SubjectController as GuruSubjectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubjectRecapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    return redirect()->route($user ? $user->role->dashboardRoute() : 'login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
| Admin area: every route (pages, DataTables endpoints, writes) sits behind auth + admin.
| The controllers repeat the check with the "access-admin" gate.
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

    // Create forms are modals on the index pages; old create URLs open that modal (#tambah).
    Route::redirect('/guru/create', '/admin/guru#tambah');
    Route::redirect('/kelas/create', '/admin/kelas#tambah');
    Route::redirect('/siswa/create', '/admin/siswa#tambah');

    Route::get('/guru/data', [TeacherController::class, 'data'])->name('guru.data');
    Route::patch('/guru/{teacher}/status', [TeacherController::class, 'updateStatus'])->name('guru.status');
    Route::patch('/guru/{teacher}/reset-password', [TeacherController::class, 'resetPassword'])->name('guru.reset-password');
    Route::resource('guru', TeacherController::class)
        ->except(['show', 'create'])
        ->parameters(['guru' => 'teacher']);

    Route::get('/kelas/data', [SchoolClassController::class, 'data'])->name('kelas.data');
    Route::resource('kelas', SchoolClassController::class)
        ->except(['show', 'create'])
        ->parameters(['kelas' => 'schoolClass']);

    Route::get('/siswa/data', [StudentController::class, 'data'])->name('siswa.data');
    Route::get('/siswa/{student}/status', [StudentController::class, 'statusForm'])->name('siswa.status');
    Route::patch('/siswa/{student}/status', [StudentController::class, 'status'])->name('siswa.status.update');
    Route::get('/siswa/{student}/pindah', [StudentController::class, 'transferForm'])->name('siswa.pindah');
    Route::patch('/siswa/{student}/pindah', [StudentController::class, 'transfer'])->name('siswa.pindah.update');
    Route::resource('siswa', StudentController::class)
        ->except(['show', 'create'])
        ->parameters(['siswa' => 'student']);

    /*
    | Yearly flow: tahun ajaran -> kenaikan kelas -> wali kelas -> mapel.
    | Every step after the first needs an active academic year ("active-year").
    */
    Route::get('/tahun-ajaran', [AcademicYearController::class, 'index'])->name('tahun-ajaran.index');
    Route::get('/tahun-ajaran/data', [AcademicYearController::class, 'data'])->name('tahun-ajaran.data');
    Route::delete('/tahun-ajaran/{academicYear}', [AcademicYearController::class, 'destroy'])->name('tahun-ajaran.destroy');
    Route::get('/tahun-ajaran/create', [AcademicYearController::class, 'create'])->name('tahun-ajaran.create');
    Route::post('/tahun-ajaran', [AcademicYearController::class, 'store'])->name('tahun-ajaran.store');

    // Index pages also show earlier years as read-only history, so they work without an active year.
    Route::get('/wali-kelas', [HomeroomTeacherController::class, 'index'])->name('wali-kelas.index');
    Route::get('/wali-kelas/data', [HomeroomTeacherController::class, 'data'])->name('wali-kelas.data');
    Route::get('/mapel/data', [SubjectController::class, 'data'])->name('mapel.data');
    Route::get('/mapel', [SubjectController::class, 'index'])->name('mapel.index');

    Route::middleware('active-year')->group(function () {
        Route::get('/kenaikan-kelas', [PromotionController::class, 'index'])->name('kenaikan.index');
        Route::post('/kenaikan-kelas/preview', [PromotionController::class, 'preview'])->name('kenaikan.preview');
        Route::post('/kenaikan-kelas', [PromotionController::class, 'store'])->name('kenaikan.store');

        // Bulk promotion from an Excel file: template -> upload -> preview -> confirm.
        Route::get('/kenaikan-kelas/template', [PromotionImportController::class, 'template'])->name('kenaikan.template');
        Route::post('/kenaikan-kelas/impor', [PromotionImportController::class, 'preview'])->name('kenaikan.impor');
        Route::post('/kenaikan-kelas/impor/proses', [PromotionImportController::class, 'store'])->name('kenaikan.impor.store');

        Route::resource('wali-kelas', HomeroomTeacherController::class)
            ->except(['index', 'show'])
            ->parameters(['wali-kelas' => 'homeroomTeacher']);

        Route::resource('mapel', SubjectController::class)
            ->except(['index', 'show'])
            ->parameters(['mapel' => 'subject']);
    });

    Route::get('/laporan', [AttendanceRecapController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/mapel', [SubjectRecapController::class, 'index'])->name('laporan.mapel');

    // Koreksi absensi: any date, edit and delete (AttendancePolicy).
    Route::get('/absensi', [AttendanceRecordController::class, 'index'])->name('absensi.index');
    Route::get('/absensi/data', [AttendanceRecordController::class, 'data'])->name('absensi.data');
    Route::get('/absensi/{attendance}/edit', [AttendanceRecordController::class, 'edit'])->name('absensi.edit');
    Route::put('/absensi/{attendance}', [AttendanceRecordController::class, 'update'])->name('absensi.update');
    Route::delete('/absensi/{attendance}', [AttendanceRecordController::class, 'destroy'])->name('absensi.destroy');

    // Not in the ERD yet (no schedules table).
    Route::view('/jadwal', 'pages.placeholder', ['title' => 'Jadwal Pelajaran'])->name('jadwal.index');

    Route::get('/akun', [ProfileController::class, 'edit'])->name('akun.edit');
    Route::put('/akun', [ProfileController::class, 'update'])->name('akun.update');
});

/*
| Guru area: auth + guru. Guru data is always scoped to the signed-in guru in the queries.
*/
Route::middleware(['auth', 'guru'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('/dashboard', GuruDashboardController::class)->name('dashboard');

    // Daily operations. Subjects/attendance are always scoped to the signed-in guru (queries + AttendancePolicy).
    Route::get('/mapel', [GuruSubjectController::class, 'index'])->name('mapel.index');
    Route::get('/mapel/data', [GuruSubjectController::class, 'data'])->name('mapel.data');

    Route::get('/absensi', [GuruAttendanceController::class, 'index'])->name('absensi.index');
    Route::post('/absensi/{subject}', [GuruAttendanceController::class, 'store'])->name('absensi.store');
    Route::post('/absensi/{subject}/siswa/{student}', [GuruAttendanceController::class, 'storeOne'])->name('absensi.store-one');

    Route::get('/riwayat-absensi', [AttendanceRecordController::class, 'index'])->name('riwayat.index');
    Route::get('/riwayat-absensi/data', [AttendanceRecordController::class, 'data'])->name('riwayat.data');
    Route::get('/riwayat-absensi/{attendance}/edit', [AttendanceRecordController::class, 'edit'])->name('riwayat.edit');
    Route::put('/riwayat-absensi/{attendance}', [AttendanceRecordController::class, 'update'])->name('riwayat.update');

    Route::get('/rekap-absensi', [AttendanceRecapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap-mapel', [SubjectRecapController::class, 'index'])->name('rekap.mapel');

    // Not built yet.
    Route::view('/nilai', 'pages.placeholder', ['title' => 'Input Nilai'])->name('nilai.index');
    Route::view('/jadwal', 'pages.placeholder', ['title' => 'Jadwal Mengajar'])->name('jadwal.index');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
});
