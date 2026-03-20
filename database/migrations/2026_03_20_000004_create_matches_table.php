<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stage_id')
                ->constrained('stages')
                ->cascadeOnDelete();

            $table->foreignId('tie_id')
                ->nullable()
                ->constrained('ties')
                ->nullOnDelete();

            $table->string('side_a_label');
            $table->string('side_b_label');

            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                $table->enum('match_order', ['S1', 'D1', 'S2', 'D2', 'S3'])->nullable();
                $table->enum('status', ['pending', 'in_progress', 'completed', 'retired', 'walkover', 'not_required'])
                    ->default('pending');
                $table->enum('winner_side', ['a', 'b'])->nullable();
            } else {
                $table->string('match_order')->nullable();
                $table->string('status')->default('pending');
                $table->string('winner_side')->nullable();
            }

            // Stored here as well even though it is derived from the stage.
            $table->unsignedTinyInteger('best_of');

            $table->string('umpire_name')->nullable();
            $table->string('service_judge_name')->nullable();
            $table->string('court')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
