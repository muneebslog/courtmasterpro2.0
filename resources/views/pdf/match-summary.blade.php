<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Match #{{ $match->id }} - {{ $match->side_a_label }} vs {{ $match->side_b_label }}@if($match->match_order) ({{ $match->match_order }})@endif</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1a1a1a; padding: 20mm; }
        h1 { font-size: 16pt; font-weight: bold; margin-bottom: 4pt; border-bottom: 1pt solid #333; padding-bottom: 6pt; }
        h2 { font-size: 12pt; font-weight: bold; margin-top: 12pt; margin-bottom: 6pt; }
        .meta { font-size: 10pt; color: #444; margin-bottom: 12pt; }
        .scores { width: 100%; border-collapse: collapse; margin: 12pt 0; }
        .scores th, .scores td { padding: 8pt 12pt; text-align: left; border: 1pt solid #ccc; }
        .scores th { background: #f5f5f5; font-weight: bold; font-size: 10pt; }
        .scores .score-cell { text-align: center; font-weight: bold; font-family: monospace; }
        .competitors { display: flex; align-items: center; gap: 12pt; margin: 12pt 0; font-size: 12pt; }
        .vs { font-weight: bold; color: #666; }
        .winner { background: #e8f5e9; padding: 6pt 10pt; border-radius: 2pt; font-weight: bold; margin-top: 8pt; }
        .officials { display: flex; flex-wrap: wrap; gap: 24pt; margin-top: 12pt; font-size: 10pt; }
        .footer { margin-top: 20pt; padding-top: 8pt; border-top: 1pt solid #ddd; font-size: 9pt; color: #666; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $tournament->tournament_name }}</h1>
    @php
        $matchStatusLabels = [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'walkover' => 'Walkover',
            'retired' => 'Retired',
            'not_required' => 'Not Required',
        ];

        $matchStatusLabel = $matchStatusLabels[$match->status] ?? (string) $match->status;

        $tieWinsA = 0;
        $tieWinsB = 0;
        $tieWinnerLabel = null;
        if ($match->tie_id && $match->tie) {
            $tieWinsA = $match->tie->matches
                ->whereIn('status', ['completed', 'walkover'])
                ->where('winner_side', 'a')
                ->count();
            $tieWinsB = $match->tie->matches
                ->whereIn('status', ['completed', 'walkover'])
                ->where('winner_side', 'b')
                ->count();

            $tieWinnerLabel = $match->tie->winner_team_id
                ? ($match->tie->team_a_id === $match->tie->winner_team_id ? $match->tie->teamA?->name : $match->tie->teamB?->name)
                : null;
        }
    @endphp
    <div class="meta">
        {{ $event->event_name }} — {{ $stage->name }}<br>
        Match #{{ $match->id }}
        @if ($match->court)
            · Court {{ $match->court }}
        @endif
        @if ($match->match_order)
            · Order {{ $match->match_order }}
        @endif
        · Status: {{ $matchStatusLabel }}
        · {{ now()->format('M j, Y') }}
    </div>

    <h2>Match</h2>
    <div class="competitors">
        <span>{{ $match->side_a_label }}</span>
        <span class="vs">vs</span>
        <span>{{ $match->side_b_label }}</span>
    </div>

    @if ($match->tie_id && $match->tie)
        <div class="meta">
            Tie: {{ $match->tie->teamA?->name }} vs {{ $match->tie->teamB?->name }}
            ({{ $tieWinsA }}-{{ $tieWinsB }})
            @if ($tieWinnerLabel)
                · Winner: {{ $tieWinnerLabel }}
            @endif
        </div>
    @endif

    @if ($match->status === 'not_required')
        <div class="winner">
            Not Required (tie decided early)
        </div>
    @endif

    @if ($match->games->isNotEmpty())
        <table class="scores">
            <thead>
                <tr>
                    <th>Game</th>
                    <th class="score-cell">{{ $match->side_a_label }}</th>
                    <th class="score-cell">{{ $match->side_b_label }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($match->games as $game)
                    <tr>
                        <td>Game {{ $game->game_number }}</td>
                        <td class="score-cell">{{ $game->score_a }}</td>
                        <td class="score-cell">{{ $game->score_b }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($match->winner_side)
        <div class="winner">
            Winner: {{ $match->winner_side === 'a' ? $match->side_a_label : $match->side_b_label }}
        </div>
    @endif

    <h2>Details</h2>
    <div class="officials">
        @if ($match->umpire_name)
            <span><strong>Umpire:</strong> {{ $match->umpire_name }}</span>
        @endif
        @if ($match->service_judge_name)
            <span><strong>Service Judge:</strong> {{ $match->service_judge_name }}</span>
        @endif
        @if ($match->court)
            <span><strong>Court:</strong> {{ $match->court }}</span>
        @endif
        @if ($match->started_at)
            <span><strong>Started:</strong> {{ $match->started_at->format('M j, Y H:i') }}</span>
        @endif
        @if ($match->ended_at)
            <span><strong>Ended:</strong> {{ $match->ended_at->format('M j, Y H:i') }}</span>
        @endif
    </div>

    <div class="footer">
        Generated {{ now()->format('M j, Y H:i') }} · CourtMaster
    </div>
</body>
</html>
