<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stage;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TiePdfController extends Controller
{
    public function download(Tournament $tournament, Event $event, Stage $stage, Tie $tie): Response
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournament),
            403
        );

        abort_unless((int) $event->tournament_id === (int) $tournament->id, 404);
        abort_unless((int) $stage->event_id === (int) $event->id, 404);
        abort_unless((int) $tie->stage_id === (int) $stage->id, 404);
        abort_unless((string) $event->event_type === Event::EVENT_TYPE_TEAM, 404);

        $tie->load([
            'teamA',
            'teamB',
            'matches.matchPlayers',
            'matches.games' => fn ($q) => $q->orderBy('game_number'),
        ]);

        $slugA = Str::slug(Str::limit(strip_tags((string) $tie->teamA?->name), 24, ''));
        $slugB = Str::slug(Str::limit(strip_tags((string) $tie->teamB?->name), 24, ''));

        $filename = sprintf(
            'Tie-%d-%s-vs-%s.pdf',
            $tie->id,
            $slugA ?: 'team-a',
            $slugB ?: 'team-b'
        );

        return Pdf::loadView('pdf.tie-summary', [
            'tie' => $tie,
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
