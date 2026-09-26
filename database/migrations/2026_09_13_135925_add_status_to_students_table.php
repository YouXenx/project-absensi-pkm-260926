<?php

use App\StudentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Existing students stay "aktif".
     * A graduated student keeps their last class_id; only the status changes.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('status', array_column(StudentStatus::cases(), 'value'))
                ->default(StudentStatus::Active->value)
                ->after('gender')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
