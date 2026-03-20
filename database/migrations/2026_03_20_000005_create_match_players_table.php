<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $table->enum('side', ['a', 'b']);
            } else {
                $table->string('side');
            }

            $table->string('player_name');
            $table->unsignedTinyInteger('position')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
