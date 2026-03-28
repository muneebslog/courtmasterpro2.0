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
        Schema::table('match_players', function (Blueprint $table) {
            $table->foreignId('team_player_id')
                ->nullable()
                ->after('position')
                ->constrained('team_players')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_player_id');
        });
    }
};
