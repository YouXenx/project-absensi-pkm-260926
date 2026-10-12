<?php

namespace App\Imports;

use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Wali Kelas from Excel: one class and the email of its homeroom teacher, for the active academic year.
 */
class HomeroomImport extends MasterImport
{
    /** @var Collection<string, SchoolClass> Classes by lower-case name. */
    private Collection $classes;

    /** @var Collection<string, User> Active teachers by email. */
    private Collection $teachers;

    /** @var array<int, true> Classes that already have a homeroom teacher this year, or got one in an earlier row. */
    private array $assigned = [];

    public function title(): string
    {
        return 'Wali Kelas';
    }

    public function noun(): string
    {
        return 'wali kelas';
    }

    public function indexRoute(): string
    {
        return 'admin.wali-kelas.index';
    }

    public function needsActiveYear(): bool
    {
        return true;
    }

    public function headers(): array
    {
        return ['Kelas', 'Email Guru'];
    }

    public function samples(): array
    {
        $class = SchoolClass::orderBy('class_name')->value('class_name') ?? 'Kelas 7A';
        $email = User::teachers()->active()->orderBy('name')->value('email') ?? 'guru@sekolah.sch.id';

        return [[$class, $email]];
    }

    public function notes(): array
    {
        return [
            'Satu baris menetapkan satu wali kelas untuk tahun ajaran aktif. Setiap kelas hanya boleh punya satu wali.',
            'Kolom "Kelas" diisi nama kelas dan kolom "Email Guru" diisi email akun guru yang aktif, keduanya dari daftar di bawah.',
        ];
    }

    public function references(): array
    {
        return [
            'Nama kelas yang valid' => SchoolClass::orderBy('class_name')->pluck('class_name')->all(),
            'Email guru aktif' => User::teachers()->active()->orderBy('name')->get()->map(fn (User $teacher): string => "{$teacher->email}  ({$teacher->name})")->all(),
        ];
    }

    protected function prepare(Collection $rows): void
    {
        $this->classes = SchoolClass::all()->keyBy(fn (SchoolClass $class): string => mb_strtolower($class->class_name));
        $this->teachers = User::teachers()->active()->get()->keyBy(fn (User $teacher): string => mb_strtolower($teacher->email));
        $this->assigned = array_fill_keys(
            HomeroomTeacher::where('academic_year_id', $this->academicYear->academic_year_id)->pluck('class_id')->all(),
            true,
        );
    }

    protected function check(array $cells): array
    {
        [$className, $email] = $cells;
        $class = $this->classes->get(mb_strtolower($className));
        $teacher = $this->teachers->get(mb_strtolower($email));

        $error = match (true) {
            $className === '' => 'Kelas wajib diisi.',
            $class === null => "Kelas \"{$className}\" tidak ditemukan.",
            isset($this->assigned[$class->class_id]) => "{$class->class_name} sudah punya wali kelas tahun ajaran {$this->academicYear->year_name}, di aplikasi atau di baris sebelumnya.",
            $email === '' => 'Email guru wajib diisi.',
            $teacher === null => "Guru dengan email \"{$email}\" tidak ditemukan atau akunnya nonaktif.",
            default => null,
        };

        if ($error) {
            return [null, $error];
        }

        $this->assigned[$class->class_id] = true;

        return [['class_id' => $class->class_id, 'user_id' => $teacher->id, 'academic_year_id' => $this->academicYear->academic_year_id], null];
    }

    protected function create(array $attributes): void
    {
        HomeroomTeacher::create($attributes);
    }
}
