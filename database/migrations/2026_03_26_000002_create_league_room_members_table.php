<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_room_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('league_room_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_ready')->default(false);
            $table->timestamps();

            $table->foreign('league_room_id')->references('id')->on('league_rooms')->cascadeOnDelete();
            $table->unique(['league_room_id', 'user_id']);
            $table->unique(['league_room_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_room_members');
    }
};
