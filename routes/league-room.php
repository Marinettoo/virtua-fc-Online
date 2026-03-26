<?php

use App\Http\Controllers\LeagueRoomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/league-room/create', [LeagueRoomController::class, 'create'])->name('league-room.create');
    Route::post('/league-room', [LeagueRoomController::class, 'store'])->name('league-room.store');

    Route::get('/league-room/join', [LeagueRoomController::class, 'join'])->name('league-room.join');
    Route::post('/league-room/join', [LeagueRoomController::class, 'joinStore'])->name('league-room.join.store');

    Route::get('/league-room/{leagueRoom}/lobby', [LeagueRoomController::class, 'lobby'])->name('league-room.lobby');
    Route::post('/league-room/{leagueRoom}/choose-team', [LeagueRoomController::class, 'chooseTeam'])->name('league-room.choose-team');
    Route::post('/league-room/{leagueRoom}/start', [LeagueRoomController::class, 'start'])->name('league-room.start');

    Route::get('/league-room/{leagueRoom}/dashboard', [LeagueRoomController::class, 'dashboard'])->name('league-room.dashboard');
    Route::post('/league-room/{leagueRoom}/ready', [LeagueRoomController::class, 'ready'])->name('league-room.ready');
});
