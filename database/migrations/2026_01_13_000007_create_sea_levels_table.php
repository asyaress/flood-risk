<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sea_levels', function (Blueprint $table) {
            $table->id();
            $table->dateTime('observed_at');
            $table->double('value_raw');
            $table->double('score');
            $table->timestamps();

            $table->unique(['observed_at'], 'sea_observed_at_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sea_levels');
    }
};
