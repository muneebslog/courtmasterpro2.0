<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $table->foreignId('game_id')
                ->nullable()
                ->constrained('games')
                ->nullOnDelete();

            $driver = Schema::getConnection()->getDriverName();

            $eventTypes = [
                'match_started',
                'point',
                'undo',
                'occurrence',
                'game_ended',
                'bulk_score_entry',
                'match_ended',
                'match_reset',
                'player_edit',
                'score_correction',
            ];

            if ($driver === 'mysql') {
                $table->enum('event_type', $eventTypes);
                $table->enum('created_by', ['umpire', 'admin'])->default('umpire');
                $table->enum('side', ['a', 'b'])->nullable();
            } else {
                $table->string('event_type');
                $table->string('created_by')->default('umpire');
                $table->string('side')->nullable();
            }

            $table->string('player_name')->nullable();

            $table->unsignedInteger('score_a_at_time')->default(0);
            $table->unsignedInteger('score_b_at_time')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['match_id', 'game_id', 'created_at'], 'match_events_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
