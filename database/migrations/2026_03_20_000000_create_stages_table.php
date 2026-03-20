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
        Schema::create('stages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name');

            // Allowed values: 1, 3, 5
            $table->unsignedTinyInteger('best_of');

            $table->unsignedInteger('order_index');

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            } else {
                // Keep SQLite tests compatible with MySQL enum requirements.
                $table->string('status')->default('pending');
            }

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
