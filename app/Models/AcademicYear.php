<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Tahun ajaran, e.g. "2025/2026". At most one row is active (enforced by a unique generated column).
 */
#[Table(name: 'academic_years', key: 'academic_year_id')]
#[Fillable(['year_name', 'is_active'])]
#[Hidden(['active_marker'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * Semester 1 runs July–December of the first calendar year, semester 2 January–June of the second.
     */
    public const SEMESTER_MONTHS = [1 => [7, 12], 2 => [1, 6]];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The currently active academic year, if any.
     */
    public static function current(): ?self
    {
        return static::query()->active()->first();
    }

    /**
     * Make this the only active academic year.
     */
    public function activate(): void
    {
        DB::transaction(function (): void {
            static::query()->whereKeyNot($this->getKey())->where('is_active', true)->update(['is_active' => false]);

            $this->forceFill(['is_active' => true])->save();
        });
    }

    /**
     * First calendar year of "2025/2026" => 2025.
     */
    public function startYear(): int
    {
        return (int) substr($this->year_name, 0, 4);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function semesterRange(int $semester): array
    {
        [$fromMonth, $toMonth] = self::SEMESTER_MONTHS[$semester];
        $calendarYear = $semester === 1 ? $this->startYear() : $this->startYear() + 1;

        return [
            CarbonImmutable::create($calendarYear, $fromMonth, 1)->startOfDay(),
            CarbonImmutable::create($calendarYear, $toMonth, 1)->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * The semester containing today, or semester 1 when today is outside this academic year.
     */
    public function currentSemester(): int
    {
        [$semesterTwoStart, $semesterTwoEnd] = $this->semesterRange(2);

        return now()->between($semesterTwoStart, $semesterTwoEnd->endOfDay()) ? 2 : 1;
    }

    /**
     * Whether this is the oldest academic year in the system (no previous year to promote from).
     */
    public function isFirstYear(): bool
    {
        return ! static::query()->where('year_name', '<', $this->year_name)->exists();
    }

    /**
     * Active students enrolled before this year was created who have not been through the promotion
     * process of this year yet. Students added after the year was created are new intake and need no promotion.
     *
     * @return Builder<Student>
     */
    public function studentsAwaitingPromotion(): Builder
    {
        return Student::query()
            ->active()
            ->where('students.created_at', '<=', $this->created_at)
            ->whereDoesntHave('promotions', fn ($query) => $query->where('academic_year_id', $this->academic_year_id));
    }

    /**
     * Step 2 is finished when every enrolled student has a promotion result (naik, tinggal or lulus) for this year.
     */
    public function promotionCompleted(): bool
    {
        return $this->isFirstYear() || ! $this->studentsAwaitingPromotion()->exists();
    }

    /**
     * Checklist for the yearly flow: promotion -> homeroom teachers -> subjects.
     * Only classes that currently have active students are expected to be covered.
     *
     * @return array{complete: bool, steps: list<array{key: string, label: string, done: bool, detail: string}>}
     */
    public function setupProgress(): array
    {
        $classesWithStudents = SchoolClass::query()->whereHas('students', fn ($query) => $query->active());
        $expectedClasses = (clone $classesWithStudents)->count();

        $withoutHomeroom = (clone $classesWithStudents)
            ->whereDoesntHave('homeroomTeachers', fn ($query) => $query->where('academic_year_id', $this->academic_year_id))
            ->orderBy('class_name')
            ->pluck('class_name');

        $withoutSubjects = (clone $classesWithStudents)
            ->whereDoesntHave('subjects', fn ($query) => $query->where('academic_year_id', $this->academic_year_id))
            ->orderBy('class_name')
            ->pluck('class_name');

        $processedStudents = $this->promotions()->count();
        $promotionNotNeeded = $this->isFirstYear();
        $awaitingPromotion = $promotionNotNeeded ? 0 : $this->studentsAwaitingPromotion()->count();

        $steps = [
            [
                'key' => 'promotion',
                'label' => 'Proses kenaikan kelas',
                'done' => $awaitingPromotion === 0,
                'detail' => match (true) {
                    $promotionNotNeeded => 'Tidak diperlukan (tahun ajaran pertama).',
                    $awaitingPromotion === 0 => "Semua {$processedStudents} siswa sudah diproses.",
                    default => "{$processedStudents} siswa sudah diproses, {$awaitingPromotion} siswa belum.",
                },
            ],
            [
                'key' => 'homeroom',
                'label' => 'Wali kelas',
                'done' => $withoutHomeroom->isEmpty(),
                'detail' => $withoutHomeroom->isEmpty()
                    ? "Semua {$expectedClasses} kelas sudah punya wali kelas."
                    : 'Belum ada wali: '.$withoutHomeroom->implode(', ').'.',
            ],
            [
                'key' => 'subjects',
                'label' => 'Mata pelajaran',
                'done' => $withoutSubjects->isEmpty(),
                'detail' => $withoutSubjects->isEmpty()
                    ? "Semua {$expectedClasses} kelas sudah punya mapel."
                    : 'Belum ada mapel: '.$withoutSubjects->implode(', ').'.',
            ],
        ];

        return [
            'complete' => collect($steps)->every(fn (array $step): bool => $step['done']),
            'steps' => $steps,
        ];
    }

    /**
     * @return HasMany<HomeroomTeacher, $this>
     */
    public function homeroomTeachers(): HasMany
    {
        return $this->hasMany(HomeroomTeacher::class, 'academic_year_id', 'academic_year_id');
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'academic_year_id', 'academic_year_id');
    }

    /**
     * @return HasMany<StudentPromotion, $this>
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'academic_year_id', 'academic_year_id');
    }
}
