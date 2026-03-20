<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/tournaments', [DashboardController::class, 'store'])->name('dashboard.tournaments.store');

    Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}',
        'pages::event',
    )->name('tournaments.events.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}/stages/{stage}',
        'pages::event.stage',
    )->name('tournaments.events.stages.show');
});

require __DIR__.'/settings.php';
