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
        Schema::create('emp_infos', function (Blueprint $table) {
            $table->id();
            $table->string('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->boolean('has_family')->default(false);
            $table->boolean('has_car')->default(false);
            $table->boolean('is_arabic_team')->default(false);
            $table->text('notes')->nullable();
            $table->enum('status', ['Active', 'Suspension', 'Terminated', 'On Leave'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_infos');
    }
};
