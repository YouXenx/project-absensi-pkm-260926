<?php

namespace App\Models;

use App\Models\Concerns\AssignedToTeacher;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapel as a teaching assignment: subject name + guru + class + academic year.
 */
#[Table(name: 'subjects', key: 'subject_id')]
#[Fillable(['subject_name', 'user_id', 'academic_year_id', 'class_id'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use AssignedToTeacher, HasFactory;

    /**
     * Subjects whose class has a homeroom teacher in the subject's academic year (step 3 of the yearly flow).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withHomeroomTeacher(Builder $query): void
    {
        $query->whereExists(fn ($exists) => $exists
            ->from('homeroom_teachers')
            ->whereColumn('homeroom_teachers.class_id', 'subjects.class_id')
            ->whereColumn('homeroom_teachers.academic_year_id', 'subjects.academic_year_id'));
    }

    public function hasHomeroomTeacher(): bool
    {
        return HomeroomTeacher::query()
            ->where('class_id', $this->class_id)
            ->where('academic_year_id', $this->academic_year_id)
            ->exists();
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id', 'class_id');
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'subject_id', 'subject_id');
    }
}
