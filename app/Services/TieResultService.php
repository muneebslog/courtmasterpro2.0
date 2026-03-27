<?php

namespace App\Services;

use App\Models\MatchModel;
use App\Models\Tie;

class TieResultService
{
    /**
     * Fixed order inside a team tie: S1, D1, S2, D2, S3.
     *
     * @var array<int, string>
     */
    private const MATCH_ORDER = ['S1', 'D1', 'S2', 'D2', 'S3'];

    /**
     * Recompute tie winner and status based on all inner match results.
     *
     * This is intentionally idempotent so it can be called after any inner match
     * transitions to a terminal state.
     */
    public function updateTieAfterInnerMatch(MatchModel $match): void
    {
        if (! $match->tie_id) {
            return;
        }

        $tie = Tie::query()
            ->with(['matches'])
            ->whereKey($match->tie_id)
            ->first();

        if (! $tie) {
            return;
        }

        $winsA = 0;
        $winsB = 0;
        $allFinished = true;

        foreach (self::MATCH_ORDER as $order) {
            $innerMatch = $tie->matches->firstWhere('match_order', $order);
            if (! $innerMatch) {
                $allFinished = false;

                continue;
            }

            if (! in_array($innerMatch->status, ['completed', 'walkover', 'retired', 'not_required'], true)) {
                $allFinished = false;

                continue;
            }

            if (! in_array($innerMatch->winner_side, ['a', 'b'], true)) {
                continue;
            }

            if ($innerMatch->winner_side === 'a') {
                $winsA++;
            } else {
                $winsB++;
            }
        }

        $winnerTeamId = null;
        if ($winsA > $winsB) {
            $winnerTeamId = $tie->team_a_id;
        } elseif ($winsB > $winsA) {
            $winnerTeamId = $tie->team_b_id;
        }

        $tie->update([
            'winner_team_id' => $winnerTeamId,
            'status' => $allFinished && $winnerTeamId !== null ? 'completed' : 'in_progress',
        ]);
    }
}
