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
        Schema::create('rome_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rome_code_id')->constrained('rome_codes');
            $table->foreignId('run_id')->nullable()->constrained('rome_stats_runs');
            $table->dateTime('execution_datetime');

            $table->decimal('avg_salary', 10, 2)->nullable();
            $table->decimal('urgent_rate', 5, 2)->nullable();
            $table->decimal('avg_days_open', 5, 2)->nullable();
            $table->integer('offer_count');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rome_stats');
    }
};
