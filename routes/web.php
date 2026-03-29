<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveScoreController;
use App\Http\Controllers\MatchPdfController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TiePdfController;
use App\Http\Controllers\TournamentController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');

Route::get('live/court/{court}', [LiveScoreController::class, 'courtView'])
    ->where('court', '[1-4]')
    ->name('live.court');
Route::get('live/all', [LiveScoreController::class, 'allView'])->name('live.all');

Route::get('api/live/court/{court}', [LiveScoreController::class, 'courtScore'])
    ->middleware('throttle:live-court-score')
    ->where('court', '[1-4]')
    ->name('api.live.court.score');

Route::livewire('viewer/tournaments', 'pages::viewer.tournaments')->name('viewer.tournaments.index');
Route::livewire('viewer/tournaments/{tournament}', 'pages::viewer.tournament')->name('viewer.tournaments.show');
Route::livewire('viewer/tournaments/{tournament}/events/{event}', 'pages::viewer.event')->name('viewer.events.show');
Route::livewire('viewer/tournaments/{tournament}/events/{event}/stages/{stage}', 'pages::viewer.stage')->name('viewer.stages.show');
Route::livewire('viewer/tournaments/{tournament}/events/{event}/stages/{stage}/ties/{tie}', 'pages::viewer.tie')->name('viewer.ties.show');
Route::livewire('viewer/tournaments/{tournament}/events/{event}/stages/{stage}/matches/{match}', 'pages::viewer.match')->name('viewer.matches.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/tournaments', [DashboardController::class, 'store'])->name('dashboard.tournaments.store');

    Route::get('guide', function () {
        $user = Auth::user();

        abort_unless(
            $user instanceof User
                && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_UMPIRES], true),
            403
        );

        return view('pages.guide');
    })->name('guide');

    Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}',
        'pages::event',
    )->name('tournaments.events.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}/stages/{stage}',
        'pages::event.stage',
    )->name('tournaments.events.stages.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}/stages/{stage}/ties/{tie}',
        'pages::event.tie',
    )->name('tournaments.events.stages.ties.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}/stages/{stage}/matches/{match}',
        'pages::event.match',
    )->name('tournaments.events.stages.matches.show');

    Route::livewire(
        'tournaments/{tournament}/events/{event}/stages/{stage}/matches/{match}/controlpanel',
        'pages::event.match.controlpanel',
    )->name('tournaments.events.stages.matches.controlpanel');

    Route::get(
        'tournaments/{tournament}/events/{event}/stages/{stage}/matches/{match}/pdf',
        [MatchPdfController::class, 'download']
    )->name('tournaments.events.stages.matches.pdf');

    Route::get(
        'tournaments/{tournament}/events/{event}/stages/{stage}/ties/{tie}/pdf',
        [TiePdfController::class, 'download']
    )->name('tournaments.events.stages.ties.pdf');
});

require __DIR__.'/settings.php';
