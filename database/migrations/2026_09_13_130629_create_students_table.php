<?php

use App\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Depends on the "classes" table.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id('student_id');
            $table->string('nis', 20)->unique();
            $table->string('name');
            $table->foreignId('class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->enum('gender', array_column(Gender::cases(), 'value'));
            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
