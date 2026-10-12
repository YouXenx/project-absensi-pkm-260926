<?php

namespace App\Imports;

use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Mata Pelajaran from Excel: subject name, class and the email of the teacher, for the active academic year.
 * Same rules as the form: the class needs its homeroom teacher first, and a subject name is unique per class.
 */
class SubjectImport extends MasterImport
{
    /** @var Collection<string, SchoolClass> Classes by lower-case name. */
    private Collection $classes;

    /** @var Collection<string, User> Active teachers by email. */
    private Collection $teachers;

    /** @var array<int, true> Classes with a homeroom teacher this year. */
    private array $withHomeroom = [];

    /** @var array<string, true> "class id|subject name" pairs that exist this year or came earlier in the file. */
    private array $taken = [];

    public function title(): string
    {
        return 'Mata Pelajaran';
    }

    public function noun(): string
    {
        return 'mata pelajaran';
    }

    public function indexRoute(): string
    {
        return 'admin.mapel.index';
    }

    public function needsActiveYear(): bool
    {
        return true;
    }

    public function headers(): array
    {
        return ['Nama Mapel', 'Kelas', 'Email Guru'];
    }

    public function samples(): array
    {
        $class = SchoolClass::orderBy('class_name')->value('class_name') ?? 'Kelas 7A';
        $email = User::teachers()->active()->orderBy('name')->value('email') ?? 'guru@sekolah.sch.id';

        return [['Contoh: Matematika', $class, $email], ['Contoh: Bahasa Indonesia', $class, $email]];
    }

    public function notes(): array
    {
        return [
            'Satu baris berarti satu mapel di satu kelas untuk tahun ajaran aktif. Mapel yang sama di kelas lain ditulis di baris baru.',
            'Kelas harus sudah punya wali kelas di tahun ajaran aktif. Nama mapel paling banyak 100 karakter dan tidak boleh dobel di kelas yang sama.',
            'Kolom "Kelas" dan "Email Guru" diisi dari daftar di bawah.',
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
        $yearId = $this->academicYear->academic_year_id;

        $this->classes = SchoolClass::all()->keyBy(fn (SchoolClass $class): string => mb_strtolower($class->class_name));
        $this->teachers = User::teachers()->active()->get()->keyBy(fn (User $teacher): string => mb_strtolower($teacher->email));
        $this->withHomeroom = array_fill_keys(HomeroomTeacher::where('academic_year_id', $yearId)->pluck('class_id')->all(), true);
        $this->taken = Subject::where('academic_year_id', $yearId)
            ->get(['class_id', 'subject_name'])
            ->mapWithKeys(fn (Subject $subject): array => [$subject->class_id.'|'.mb_strtolower($subject->subject_name) => true])
            ->all();
    }

    protected function check(array $cells): array
    {
        [$name, $className, $email] = $cells;
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $class = $this->classes->get(mb_strtolower($className));
        $teacher = $this->teachers->get(mb_strtolower($email));
        $key = ($class?->class_id).'|'.mb_strtolower($name);

        $error = match (true) {
            $name === '' => 'Nama mapel wajib diisi.',
            mb_strlen($name) > 100 => 'Nama mapel maksimal 100 karakter.',
            $className === '' => 'Kelas wajib diisi.',
            $class === null => "Kelas \"{$className}\" tidak ditemukan.",
            ! isset($this->withHomeroom[$class->class_id]) => "Tetapkan wali kelas tahun ajaran {$this->academicYear->year_name} untuk {$class->class_name} terlebih dahulu.",
            isset($this->taken[$key]) => "Mapel {$name} sudah ada untuk {$class->class_name}, di aplikasi atau di baris sebelumnya.",
            $email === '' => 'Email guru wajib diisi.',
            $teacher === null => "Guru dengan email \"{$email}\" tidak ditemukan atau akunnya nonaktif.",
            default => null,
        };

        if ($error) {
            return [null, $error];
        }

        $this->taken[$key] = true;

        return [[
            'subject_name' => $name,
            'class_id' => $class->class_id,
            'user_id' => $teacher->id,
            'academic_year_id' => $this->academicYear->academic_year_id,
        ], null];
    }

    protected function create(array $attributes): void
    {
        Subject::create($attributes);
    }
}
