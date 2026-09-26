<?php

namespace App\Models;

use App\Gender;
use App\StudentStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'students', key: 'student_id')]
#[Fillable(['nis', 'name', 'class_id', 'gender', 'status'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
        ];
    }

    /**
     * Students still enrolled (not graduated).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), StudentStatus::Active);
    }

    /**
     * Current class. For a graduated student this is the last class they attended.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id', 'class_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id', 'student_id');
    }

    /**
     * Class-promotion history, one row per academic year.
     *
     * @return HasMany<StudentPromotion, $this>
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'student_id', 'student_id');
    }
}
