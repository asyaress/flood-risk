<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inarisk_floods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->double('value_raw');
            $table->double('score');
            $table->timestamps();

            $table->unique(['area_id'], 'inarisk_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inarisk_floods');
    }
};
