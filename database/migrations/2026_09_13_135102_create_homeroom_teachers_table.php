<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Depends on classes, users and academic_years.
     */
    public function up(): void
    {
        Schema::create('homeroom_teachers', function (Blueprint $table) {
            $table->id('homeroom_id');

            // Pure assignment row: deleting a class (only possible once it has no students) removes it.
            $table->foreignId('class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Guru accounts are deactivated, not deleted; keep the assignment history.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years', 'academic_year_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            // One homeroom teacher per class per academic year.
            $table->unique(['class_id', 'academic_year_id']);
            $table->index(['user_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homeroom_teachers');
    }
};
