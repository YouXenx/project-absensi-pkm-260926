<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id('academic_year_id');
            $table->string('year_name', 20)->unique();
            $table->boolean('is_active')->default(false);

            // "Only one active year" enforced by the database: 1 for the active row, NULL otherwise.
            // A unique index allows many NULLs but only a single 1.
            $table->unsignedTinyInteger('active_marker')
                ->nullable()
                ->storedAs('(case when is_active = 1 then 1 else null end)')
                ->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
