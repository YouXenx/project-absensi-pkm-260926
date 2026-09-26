<?php

use App\PromotionResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * History of the class-promotion process: where each student came from and went to
     * when a given academic year was opened. students.class_id only holds the current class,
     * so this table keeps last year's placement visible after the mass update.
     */
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id('promotion_id');

            // The academic year the student was promoted INTO.
            $table->foreignId('academic_year_id')
                ->constrained('academic_years', 'academic_year_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // The log belongs to the student; removing a student removes their log.
            $table->foreignId('student_id')
                ->constrained('students', 'student_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Classes referenced by history cannot be deleted.
            $table->foreignId('from_class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // NULL when the student graduated.
            $table->foreignId('to_class_id')
                ->nullable()
                ->constrained('classes', 'class_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->enum('result', array_column(PromotionResult::cases(), 'value'));

            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            // A student is processed at most once per academic year (prevents 1A -> 2A -> 3A chains).
            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'from_class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
