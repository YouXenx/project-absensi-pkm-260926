<?php

namespace App\Models;

use App\AttendanceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'attendances', key: 'attendance_id')]
#[Fillable(['subject_id', 'student_id', 'date', 'status', 'description'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * Stored as a plain Y-m-d string on every database driver, so "date = today" comparisons and the
     * (subject_id, student_id, date) unique index behave the same everywhere. Read back as a Carbon date.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value)->startOfDay(),
            set: fn (mixed $value): ?string => $value === null ? null : CarbonImmutable::parse($value)->toDateString(),
        );
    }

    public function isToday(): bool
    {
        return $this->date?->isToday() ?? false;
    }

    /**
     * Admins see every record; a guru only sees attendance of subjects they teach.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereIn(
            $this->qualifyColumn('subject_id'),
            Subject::query()->forTeacher($user)->select('subject_id'),
        );
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
