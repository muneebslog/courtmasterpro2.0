<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stage_id')
                ->constrained('stages')
                ->cascadeOnDelete();

            $table->foreignId('team_a_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('team_b_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('winner_team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            } else {
                $table->string('status')->default('pending');
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ties');
    }
};
