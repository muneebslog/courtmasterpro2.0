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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('flag', 16)->nullable()->after('name');
        });

        Schema::table('match_players', function (Blueprint $table) {
            $table->string('flag', 16)->nullable()->after('player_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('flag');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('flag');
        });
    }
};
