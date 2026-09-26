<?php

namespace App\Models;

use App\Models\Concerns\AssignedToTeacher;
use Database\Factories\HomeroomTeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wali kelas: one guru assigned to one class for one academic year.
 */
#[Table(name: 'homeroom_teachers', key: 'homeroom_id')]
#[Fillable(['class_id', 'user_id', 'academic_year_id'])]
class HomeroomTeacher extends Model
{
    /** @use HasFactory<HomeroomTeacherFactory> */
    use AssignedToTeacher, HasFactory;

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
}
