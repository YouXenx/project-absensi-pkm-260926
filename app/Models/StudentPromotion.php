<?php

namespace App\Models;

use App\PromotionResult;
use Database\Factories\StudentPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per student per academic year recording the outcome of the class-promotion process.
 */
#[Table(name: 'student_promotions', key: 'promotion_id')]
#[Fillable(['academic_year_id', 'student_id', 'from_class_id', 'to_class_id', 'result', 'processed_by'])]
class StudentPromotion extends Model
{
    /** @use HasFactory<StudentPromotionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => PromotionResult::class,
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id', 'class_id');
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function toClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id', 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
