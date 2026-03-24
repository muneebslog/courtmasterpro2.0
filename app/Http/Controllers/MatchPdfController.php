<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class MatchPdfController extends Controller
{
    public function download(Tournament $tournament, Event $event, Stage $stage, MatchModel $match): Response
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournament),
            403
        );

        abort_unless((int) $event->tournament_id === (int) $tournament->id, 404);
        abort_unless((int) $stage->event_id === (int) $event->id, 404);
        abort_unless((int) $match->stage_id === (int) $stage->id, 404);

        $match->load([
            'games' => fn ($q) => $q->orderBy('game_number'),
            'tie.teamA',
            'tie.teamB',
            'tie.matches',
        ]);

        $filename = sprintf(
            'Match-%d-%s-vs-%s.pdf',
            $match->id,
            Str::slug(Str::limit(strip_tags($match->side_a_label), 20)),
            Str::slug(Str::limit(strip_tags($match->side_b_label), 20))
        );

        return Pdf::loadView('pdf.match-summary', [
            'match' => $match,
            'event' => $event,
            'stage' => $stage,
            'tournament' => $tournament,
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function userCanViewTournament(User $user, Tournament $tournament): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return (int) $tournament->admin_id === (int) $user->id;
        }

        if ($user->role === User::ROLE_UMPIRES) {
            return $tournament->users()
                ->whereKey($user->id)
                ->where('role', User::ROLE_UMPIRES)
                ->exists();
        }

        return false;
    }
}
