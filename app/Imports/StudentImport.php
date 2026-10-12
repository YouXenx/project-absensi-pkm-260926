<?php

namespace App\Imports;

use App\Gender;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Data Siswa from Excel: NIS, name, class and gender. Students are created active.
 */
class StudentImport extends MasterImport
{
    /** @var array<string, true> */
    private array $takenNis = [];

    /** @var Collection<string, SchoolClass> Classes by lower-case name. */
    private Collection $classes;

    public function title(): string
    {
        return 'Data Siswa';
    }

    public function noun(): string
    {
        return 'siswa';
    }

    public function indexRoute(): string
    {
        return 'admin.siswa.index';
    }

    public function headers(): array
    {
        return ['NIS', 'Nama Siswa', 'Kelas', 'Jenis Kelamin'];
    }

    public function samples(): array
    {
        $class = SchoolClass::orderBy('class_name')->value('class_name') ?? 'Kelas 7A';

        return [
            ['2026000123', 'Contoh: Budi Santoso', $class, 'L'],
            ['2026000124', 'Contoh: Siti Aminah', $class, 'P'],
        ];
    }

    public function notes(): array
    {
        return [
            'NIS hanya berisi angka, paling banyak 20 digit, dan tidak boleh sama dengan siswa lain.',
            'Kolom "Kelas" diisi salah satu nama kelas di bawah, persis seperti tertulis.',
            'Kolom "Jenis Kelamin" diisi L atau P (boleh juga Laki-laki atau Perempuan).',
        ];
    }

    public function references(): array
    {
        return ['Nama kelas yang valid' => SchoolClass::orderBy('class_name')->pluck('class_name')->all()];
    }

    protected function prepare(Collection $rows): void
    {
        $this->classes = SchoolClass::all()->keyBy(fn (SchoolClass $class): string => mb_strtolower($class->class_name));
        $this->takenNis = array_fill_keys(Student::whereIn('nis', $rows->pluck(0)->filter()->unique())->pluck('nis')->all(), true);
    }

    protected function check(array $cells): array
    {
        [$nis, $name, $className, $genderText] = $cells;
        $class = $this->classes->get(mb_strtolower($className));
        $gender = match (mb_strtolower($genderText)) {
            'l', 'laki-laki', 'laki laki' => Gender::Male,
            'p', 'perempuan' => Gender::Female,
            default => null,
        };

        $error = match (true) {
            $nis === '' => 'NIS wajib diisi.',
            ! ctype_digit($nis) => 'NIS hanya boleh berisi angka.',
            strlen($nis) > 20 => 'NIS maksimal 20 digit.',
            isset($this->takenNis[$nis]) => 'NIS sudah digunakan, di aplikasi atau di baris sebelumnya.',
            $name === '' => 'Nama siswa wajib diisi.',
            mb_strlen($name) > 255 => 'Nama siswa maksimal 255 karakter.',
            $className === '' => 'Kelas wajib diisi.',
            $class === null => "Kelas \"{$className}\" tidak ditemukan. Gunakan nama kelas dari sheet Petunjuk.",
            $genderText === '' => 'Jenis kelamin wajib diisi.',
            $gender === null => 'Jenis kelamin harus L atau P.',
            default => null,
        };

        if ($error) {
            return [null, $error];
        }

        $this->takenNis[$nis] = true;

        return [['nis' => $nis, 'name' => $name, 'class_id' => $class->class_id, 'gender' => $gender], null];
    }

    protected function create(array $attributes): void
    {
        Student::create($attributes);
    }
}
