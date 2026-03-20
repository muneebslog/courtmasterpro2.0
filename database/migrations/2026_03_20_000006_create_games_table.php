<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $table->unsignedInteger('game_number');

            $table->unsignedInteger('score_a')->default(0);
            $table->unsignedInteger('score_b')->default(0);

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $table->enum('winner_side', ['a', 'b'])->nullable();
                $table->enum('entry_mode', ['live', 'bulk'])->default('live');
            } else {
                $table->string('winner_side')->nullable();
                $table->string('entry_mode')->default('live');
            }

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->unique(['match_id', 'game_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
