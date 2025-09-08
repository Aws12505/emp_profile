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
        Schema::create('daily_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('date_of_day');
            $table->foreignId('emp_info_id')->constrained()->cascadeOnDelete();
            $table->time('scheduled_start_time');
            $table->time('scheduled_end_time');
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->boolean('vci')->nullable();
            $table->foreignId('status_id')->constrained()->cascadeOnDelete();
            $table->boolean('agree_on_exception')->default(false);
            $table->text('exception_notes')->nullable();
            $table->timestamps();
            
            // Index for performance
            $table->index(['date_of_day', 'emp_info_id']);
            $table->index('date_of_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_schedules');
    }
};
