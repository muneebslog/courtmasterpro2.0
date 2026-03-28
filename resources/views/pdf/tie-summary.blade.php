<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tie #{{ $tie->id }} — {{ $tie->teamA?->name }} vs {{ $tie->teamB?->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 18mm; }
        h1 { font-size: 15pt; font-weight: bold; margin-bottom: 6pt; border-bottom: 1pt solid #333; padding-bottom: 6pt; }
        h2 { font-size: 11pt; font-weight: bold; margin-top: 12pt; margin-bottom: 6pt; }
        .meta { font-size: 9pt; color: #444; margin-bottom: 10pt; }
        .teams { font-size: 12pt; font-weight: bold; margin: 10pt 0; }
        .rubber { font-size: 10pt; margin-bottom: 12pt; }
        table.rubbers { width: 100%; border-collapse: collapse; margin-top: 8pt; }
        table.rubbers th, table.rubbers td { padding: 6pt 8pt; border: 1pt solid #ccc; text-align: left; vertical-align: top; }
        table.rubbers th { background: #f5f5f5; font-size: 9pt; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; }
        .footer-meta { margin-top: 14pt; font-size: 8pt; color: #666; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $tournament->tournament_name }}</h1>
    @php
        $matchOrderIndex = ['S1' => 0, 'D1' => 1, 'S2' => 2, 'D2' => 3, 'S3' => 4];
        $innerMatches = $tie->matches
            ->sortBy(fn ($m) => $matchOrderIndex[$m->match_order] ?? 999)
            ->values();
        $rubberStatuses = ['completed', 'walkover', 'retired', 'not_required'];
        $winsA = $tie->matches->whereIn('status', $rubberStatuses)->where('winner_side', 'a')->count();
        $winsB = $tie->matches->whereIn('status', $rubberStatuses)->where('winner_side', 'b')->count();
        $tieWinnerLabel = $tie->winner_team_id
            ? ($tie->team_a_id === $tie->winner_team_id ? $tie->teamA?->name : $tie->teamB?->name)
            : null;
        $statusLabels = [
            'pending' => 'Pending',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'walkover' => 'Walkover',
            'retired' => 'Retired',
            'not_required' => 'Not required',
        ];
        $tieStatusLabel = $statusLabels[$tie->status] ?? (string) $tie->status;
    @endphp
    <div class="meta">
        {{ $event->event_name }} — {{ $stage->name }}<br>
        Tie #{{ $tie->id }} · Status: {{ $tieStatusLabel }} · {{ now()->format('M j, Y') }}
    </div>

    <div class="teams">
        {{ $tie->teamA?->flag ? $tie->teamA->flag.' ' : '' }}{{ $tie->teamA?->name }}
        <span style="font-weight: normal; color: #666;">vs</span>
        {{ $tie->teamB?->flag ? $tie->teamB->flag.' ' : '' }}{{ $tie->teamB?->name }}
    </div>
    <div class="rubber">
        <strong>Rubber score:</strong> {{ $winsA }}–{{ $winsB }}
        @if ($tieWinnerLabel)
            · <strong>Winner:</strong> {{ $tieWinnerLabel }}
        @endif
    </div>

    <h2>Rubbers</h2>
    <table class="rubbers">
        <thead>
            <tr>
                <th>#</th>
                <th>Order</th>
                <th>Status</th>
                <th>Side A</th>
                <th>Side B</th>
                <th>Games</th>
                <th>Winner</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($innerMatches as $idx => $m)
                @php
                    $la = $m->matchPlayers->where('side', 'a')->sortBy('position')->pluck('player_name')->filter()->implode(' / ');
                    $lb = $m->matchPlayers->where('side', 'b')->sortBy('position')->pluck('player_name')->filter()->implode(' / ');
                    $gamesStr = $m->games->map(fn ($g) => 'G'.$g->game_number.': '.$g->score_a.'–'.$g->score_b)->implode('; ');
                    $winLabel = $m->winner_side === 'a' ? $m->side_a_label : ($m->winner_side === 'b' ? $m->side_b_label : '—');
                    $st = $statusLabels[$m->status] ?? (string) $m->status;
                @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $m->match_order ?? '—' }}</td>
                    <td>{{ $st }}</td>
                    <td>
                        <div>{{ $m->side_a_label }}</div>
                        @if ($la !== '')
                            <div class="mono" style="margin-top: 2pt; color: #444;">{{ $la }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $m->side_b_label }}</div>
                        @if ($lb !== '')
                            <div class="mono" style="margin-top: 2pt; color: #444;">{{ $lb }}</div>
                        @endif
                    </td>
                    <td class="mono">{{ $gamesStr !== '' ? $gamesStr : '—' }}</td>
                    <td>{{ $winLabel }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer-meta">
        Printed at {{ now()->format('M j, Y H:i') }}
    </div>
</body>
</html>
