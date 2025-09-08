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
        Schema::create('emp_employment_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emp_info_id')->constrained('emp_infos')->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->json('paychex_ids');
            $table->enum('employment_type', ['1099', 'W2']);
            $table->date('hired_date');
            $table->decimal('base_pay', 10, 2);
            $table->decimal('performance_pay', 10, 2);
            $table->boolean('has_uniform')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_employment_infos');
    }
};
