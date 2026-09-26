<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * For assignment models with a "user_id" column that must point at a guru account.
 * A foreign key cannot check the role, so it is enforced whenever the model is saved.
 */
trait AssignedToTeacher
{
    public static function bootAssignedToTeacher(): void
    {
        static::saving(function (self $model): void {
            if (! $model->isDirty('user_id')) {
                return;
            }

            $isGuru = User::teachers()->whereKey($model->user_id)->exists();

            if (! $isGuru) {
                throw new InvalidArgumentException('user_id harus mengacu ke akun dengan role guru.');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope rows to one guru, e.g. Subject::forTeacher(auth()->user()).
     *
     * @param  Builder<static>  $query
     */
    public function scopeForTeacher(Builder $query, User|int $teacher): void
    {
        $query->where($this->qualifyColumn('user_id'), $teacher instanceof User ? $teacher->getKey() : $teacher);
    }
}
