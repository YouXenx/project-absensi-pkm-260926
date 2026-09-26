<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school class ("kelas"). Named SchoolClass because "Class" is reserved in PHP.
 */
#[Table(name: 'classes', key: 'class_id')]
#[Fillable(['class_name'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id', 'class_id');
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function activeStudents(): HasMany
    {
        return $this->students()->active();
    }

    /**
     * Promotion history rows that reference this class as origin or destination.
     */
    public function hasPromotionHistory(): bool
    {
        return StudentPromotion::query()
            ->where('from_class_id', $this->class_id)
            ->orWhere('to_class_id', $this->class_id)
            ->exists();
    }

    /**
     * Promotions that started from this class (one row per student per academic year).
     *
     * @return HasMany<StudentPromotion, $this>
     */
    public function promotionsFrom(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'from_class_id', 'class_id');
    }

    /**
     * Wali kelas assignments, one per academic year.
     *
     * @return HasMany<HomeroomTeacher, $this>
     */
    public function homeroomTeachers(): HasMany
    {
        return $this->hasMany(HomeroomTeacher::class, 'class_id', 'class_id');
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'class_id', 'class_id');
    }
}
