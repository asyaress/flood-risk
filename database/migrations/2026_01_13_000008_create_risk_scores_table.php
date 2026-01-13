<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->dateTime('observed_at');
            $table->double('risk_index');
            $table->string('risk_level');
            $table->json('detail_json')->nullable();
            $table->timestamps();

            $table->unique(['area_id', 'observed_at'], 'risk_area_obs_unique');
            $table->index(['observed_at'], 'risk_observed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
