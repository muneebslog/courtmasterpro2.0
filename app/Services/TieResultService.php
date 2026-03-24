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
     * Recompute tie winner based on inner match results and mark the remaining
     * unplayed matches as `not_required`.
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

        $orderIndexByOrder = array_flip(self::MATCH_ORDER);

        $winsA = 0;
        $winsB = 0;
        $tieDecidedAtOrderIndex = null;
        $winnerTeamSide = null; // 'a' | 'b'

        foreach (self::MATCH_ORDER as $order) {
            $innerMatch = $tie->matches->firstWhere('match_order', $order);
            if (! $innerMatch) {
                continue;
            }

            if (! in_array($innerMatch->status, ['completed', 'walkover'], true)) {
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

            if ($winsA >= 3 || $winsB >= 3) {
                $winnerTeamSide = $winsA >= 3 ? 'a' : 'b';
                $tieDecidedAtOrderIndex = $orderIndexByOrder[$order] ?? null;
                break;
            }
        }

        if (! $winnerTeamSide || $tieDecidedAtOrderIndex === null) {
            return; // tie not decided yet
        }

        $winnerTeamId = $winnerTeamSide === 'a' ? $tie->team_a_id : $tie->team_b_id;

        $tie->update([
            'winner_team_id' => $winnerTeamId,
            'status' => 'completed',
        ]);

        // Mark remaining, still-pending matches as NOT REQUIRED.
        foreach ($tie->matches as $innerMatch) {
            if ($innerMatch->status !== 'pending') {
                continue;
            }

            $innerIndex = $orderIndexByOrder[$innerMatch->match_order] ?? null;
            if ($innerIndex === null) {
                continue;
            }

            if ($innerIndex > $tieDecidedAtOrderIndex) {
                $innerMatch->update([
                    'status' => 'not_required',
                ]);
            }
        }
    }
}
