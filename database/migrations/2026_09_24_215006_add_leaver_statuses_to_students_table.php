<?php

use App\StudentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Students who leave during the year keep their history: the enum gains "pindah" (moved to another school)
 * and "keluar" (dropped out) next to "aktif" and "lulus".
 */
return new class extends Migration
{
    private const NEW_VALUES = ['aktif', 'pindah', 'lulus', 'keluar'];

    private const OLD_VALUES = ['aktif', 'lulus'];

    public function up(): void
    {
        $this->setStatusValues(self::NEW_VALUES);
    }

    public function down(): void
    {
        // The narrowed enum has no room for leavers; they become "lulus" so no row is lost.
        DB::table('students')
            ->whereIn('status', [StudentStatus::Transferred->value, StudentStatus::Dropped->value])
            ->update(['status' => StudentStatus::Graduated->value]);

        $this->setStatusValues(self::OLD_VALUES);
    }

    /**
     * @param  list<string>  $values
     */
    private function setStatusValues(array $values): void
    {
        // SQLite (used by the test suite) stores the column as text, so only MySQL/MariaDB needs the ALTER.
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $list = collect($values)->map(fn (string $value): string => "'{$value}'")->implode(', ');

        DB::statement("ALTER TABLE students MODIFY status ENUM({$list}) NOT NULL DEFAULT 'aktif'");
    }
};
