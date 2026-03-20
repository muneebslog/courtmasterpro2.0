<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function show(Tournament $tournament): View
    {
        return view('tournaments.show', [
            'tournament' => $tournament,
        ]);
    }
}
