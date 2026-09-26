<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Depends on users, academic_years and classes.
     * A subject row is a teaching assignment: subject + guru + class + academic year.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id('subject_id');
            $table->string('subject_name', 100);

            // Restrict everywhere: a subject owns attendance history that must not vanish by accident.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years', 'academic_year_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            // The same subject is taught once per class per academic year.
            $table->unique(['class_id', 'academic_year_id', 'subject_name']);
            $table->index(['user_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
