<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pairwise_comparisons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id')->default(1);
            $table->foreignId('criteria_i_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('criteria_j_id')->constrained('criteria')->cascadeOnDelete();
            $table->double('l');
            $table->double('m');
            $table->double('u');
            $table->timestamps();

$table->unique(['expert_id', 'criteria_i_id', 'criteria_j_id'], 'pc_expert_ci_cj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairwise_comparisons');
    }
};
