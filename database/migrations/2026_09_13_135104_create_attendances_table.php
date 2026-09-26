<?php

use App\AttendanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Depends on subjects and students.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id('attendance_id');

            // Restrict: attendance is a legal/academic record; parents cannot be deleted while it exists.
            $table->foreignId('subject_id')
                ->constrained('subjects', 'subject_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('student_id')
                ->constrained('students', 'student_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('date');
            $table->enum('status', array_column(AttendanceStatus::cases(), 'value'));
            $table->string('description')->nullable();
            $table->timestamps();

            // One record per student, per subject, per day.
            $table->unique(['subject_id', 'student_id', 'date']);
            $table->index(['subject_id', 'date']);
            $table->index(['student_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
