<?php

namespace App\Imports;

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Collection;

/**
 * Data Guru from Excel: name, email and first password. Accounts are created active, with the guru role.
 */
class TeacherImport extends MasterImport
{
    /** @var array<string, true> */
    private array $takenEmails = [];

    public function title(): string
    {
        return 'Data Guru';
    }

    public function noun(): string
    {
        return 'akun guru';
    }

    public function indexRoute(): string
    {
        return 'admin.guru.index';
    }

    public function headers(): array
    {
        return ['Nama Lengkap', 'Email', 'Password'];
    }

    public function samples(): array
    {
        return [
            ['Contoh: Siti Aminah, S.Pd', 'siti.aminah@sekolah.sch.id', 'GantiSaya123'],
            ['Contoh: Budi Santoso, S.Pd', 'budi.santoso@sekolah.sch.id', 'GantiSaya456'],
        ];
    }

    public function notes(): array
    {
        return [
            'Email dipakai untuk login dan tidak boleh sama dengan akun lain. Huruf besar otomatis dijadikan huruf kecil.',
            'Password minimal 8 karakter, bebas huruf besar, huruf kecil, angka, dan simbol. Guru bisa menggantinya di menu Profil Saya.',
            'Semua akun yang diimpor langsung aktif dan berperan sebagai guru.',
        ];
    }

    public function hiddenColumns(): array
    {
        return [2];
    }

    protected function prepare(Collection $rows): void
    {
        $emails = $rows->map(fn (array $cells): string => mb_strtolower($cells[1]))->filter()->unique();

        $this->takenEmails = array_fill_keys(User::whereIn('email', $emails)->pluck('email')->map(fn (string $email): string => mb_strtolower($email))->all(), true);
    }

    protected function check(array $cells): array
    {
        [$name, $email, $password] = $cells;
        $email = mb_strtolower($email);

        $error = match (true) {
            $name === '' => 'Nama guru wajib diisi.',
            mb_strlen($name) > 255 => 'Nama guru maksimal 255 karakter.',
            $email === '' => 'Email wajib diisi.',
            mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false => 'Format email tidak valid.',
            isset($this->takenEmails[$email]) => 'Email sudah digunakan, di aplikasi atau di baris sebelumnya.',
            $password === '' => 'Password wajib diisi.',
            mb_strlen($password) < 8 => 'Password minimal 8 karakter.',
            default => null,
        };

        if ($error) {
            return [null, $error];
        }

        $this->takenEmails[$email] = true;

        return [['name' => $name, 'email' => $email, 'password' => $password], null];
    }

    protected function create(array $attributes): void
    {
        // The "hashed" cast on User::$password hashes it on the way in.
        User::create([...$attributes, 'role' => UserRole::Guru, 'is_active' => true]);
    }
}
