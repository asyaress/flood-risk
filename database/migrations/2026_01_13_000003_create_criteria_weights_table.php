<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('criteria_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criteria_id')->constrained('criteria')->cascadeOnDelete();
            $table->unsignedBigInteger('version')->default(1);
            $table->double('l');
            $table->double('m');
            $table->double('u');
            $table->double('weight_crisp');
            $table->timestamps();

            $table->index(['version'], 'critw_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria_weights');
    }
};
