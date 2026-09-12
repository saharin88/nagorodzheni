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
        Schema::create('awardees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decree_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('award_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('full_name');
            $table->string('rank')->index();
            $table->boolean('is_posthumous')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('awardees');
    }
};
