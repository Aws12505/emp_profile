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
        Schema::create('schedule_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emp_info_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preference_id')->constrained()->cascadeOnDelete();
            $table->integer('maximum_hours');
            $table->enum('employment_type', ['FT', 'PT']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_preferences');
    }
};
