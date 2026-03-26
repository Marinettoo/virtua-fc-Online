<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_room_matchdays', function (Blueprint $table) {
            $table->id();
            $table->uuid('league_room_id');
            $table->unsignedInteger('matchday_number');
            $table->enum('status', ['pending', 'ready', 'simulated'])->default('pending');
            $table->timestamp('auto_advance_at')->nullable();
            $table->timestamps();

            $table->foreign('league_room_id')->references('id')->on('league_rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_room_matchdays');
    }
};
