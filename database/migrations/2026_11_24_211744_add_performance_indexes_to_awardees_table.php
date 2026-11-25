<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awardees', function (Blueprint $table) {
            $table->index(['decree_id', 'award_id', 'is_posthumous'], 'awardees_decree_award_posthumous_index');
            $table->index(['award_id', 'is_posthumous'], 'awardees_award_posthumous_index');
        });
    }

    public function down(): void
    {
        Schema::table('awardees', function (Blueprint $table) {
            $table->dropIndex('awardees_decree_award_posthumous_index');
            $table->dropIndex('awardees_award_posthumous_index');
        });
    }
};
