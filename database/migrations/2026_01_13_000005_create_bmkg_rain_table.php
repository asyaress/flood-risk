<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bmkg_rains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->dateTime('observed_at');
            $table->double('value_raw');
            $table->double('score');
            $table->timestamps();

            $table->unique(['area_id', 'observed_at'], 'bmkg_area_obs_unique');
            $table->index(['observed_at'], 'bmkg_observed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bmkg_rains');
    }
};
