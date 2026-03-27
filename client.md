# CourtMaster Pro (Client Overview)

This document explains, in plain language, what CourtMaster Pro does for your tournament, your officials, and your spectators. It covers both the “happy path” and the special situations our system handles.

---

## 1. Who uses CourtMaster Pro?

1. **Spectators (Public view)**
   - No login required.
   - Can follow tournaments that are published for viewing.
2. **Tournament Admin**
   - Manages tournaments: creates events and rounds, sets up the bracket, and applies corrections when needed.
3. **Umpires (Match officials)**
   - Runs matches: starts matches, records points, logs match events, and finalizes results.

---

## 2. Tournament Hub (Spectator / Public)

Spectators can open the tournament and see:

1. **Published tournament list**
   - Only tournaments marked as published are visible.
2. **Live viewer**
   - A step-by-step viewing experience:
     - Tournament -> Event -> Stage (round) -> Tie/Match -> Match details.
3. **Live match score**
   - Scores update in real time during a match.
4. **Bracket view**
   - A clear view of all matches and results for the current stage.
5. **Match timeline (full history)**
   - After a match is finalized, spectators can view the match timeline showing what happened (points, key events, and any recorded corrections).

### Public behavior during connectivity issues

- If the match feed temporarily disconnects, spectators see a clear “score update paused” message instead of stale data.
- When the connection returns, live updates continue automatically.

---

## 3. Admin features (Tournament Setup & Control)

### A. Tournament setup

Admins can:

- Create a tournament with:
  - Tournament name
  - Venue/location
  - Start and end dates
- Use tournament statuses to control visibility:
  - **Draft** (not visible to the public)
  - **Published** (visible to spectators)
  - **Completed**

### B. Rules around tournament edits

- Tournament details can be edited as needed before completion.
- Once matches have started, the tournament cannot be deleted.

### C. Event creation (what type of competition?)

Admins create events within a tournament. Events support:

- **Singles**
- **Doubles**
- **Team event**

Each event runs independently, so you can have different events at different stages at the same time.

### D. Stage / round creation

Admins create stages (rounds) within an event, including:

- Stage name (e.g., Round of 64, Quarter Final, Final)
- “Best of” setting for match format (**best of 1 / 3 / 5 games**)

### E. Entering the draw (who plays whom)

Admins enter the bracket using draw entry forms that match the event type:

1. **Singles and Doubles draw entry**
   - Each bracket row represents one match.
   - If a side is left blank, that slot becomes a **BYE** (the player/team advances automatically and no match is created).
2. **Team event draw entry**
   - Team ties are created first (Team A vs Team B).
   - After that, the 5-match structure inside each tie is generated automatically (S1, D1, S2, D2, S3).
   - Admins manage the team roster so umpires can select eligible players when each match begins.

### F. Auto-advancing through stages

CourtMaster Pro automatically creates the next stage when a stage is fully finished, based on match outcomes (including completed matches and special outcomes like walkovers/retirements).

### G. Team roster management

For team events, admins can add/edit team players so umpires can select who plays each inner match.

---

## 4. Umpire features (Running Matches)

### A. Match list and match opening

Umpires can:

- See the matches assigned to them (by status: pending, in progress, completed).
- Open a match and view the key details needed to begin.

### B. Pre-start setup (before scoring)

Before scoring starts, umpires enter:

- Umpire name (text)
- Service judge name (text, optional)
- Court number (text, if applicable)

For team events, umpires also select the players for that specific inner match from the team roster.

### C. Player eligibility rules in team ties

In a team tie, each player can play:

- **Max 1 singles match**
- **Max 1 doubles match**

Once a player has already reached their limit, they can no longer be selected for the remaining inner matches in that tie.

### D. Live scoring (point-by-point)

During a match, umpires record points using a simple “tap” style scoring screen:

- Tap the left side to award a point to Side A
- Tap the right side to award a point to Side B

Spectators see these updates in real time.

### E. Undo last point

- Umpires can undo the most recent point at any time during live play.
- Undo requires confirmation to reduce accidental corrections.
- Undo is limited to one step (the last point only).

### F. Logging match occurrences

Umpires can record important match events at any time, such as:

- Shuttle change (informational)
- Cards (yellow/red with federation-defined effect)
- Injury
- Retirement (if a player cannot continue)
- Walkover (if a player/team does not show up before the match starts)

Each occurrence is recorded so it becomes part of the match history timeline.

### G. Game end and game-to-game progression

CourtMaster Pro automatically detects when a game is won, based on badminton scoring rules, and then prompts the umpire to confirm:

- After confirmation, the next game begins.
- The match progresses correctly through the required number of games (based on best-of).

### H. Match end states

At the end, the system records match outcomes including:

- **Completed** (finished normally)
- **Walkover** (opponent no-show before start)
- **Retired** (injury/withdrawal mid-match)
- **Not Required** (only for team events, when the tie is already decided early)

---

## 5. Scoring rules CourtMaster Pro follows

CourtMaster Pro follows standard badminton scoring rules for every game:

- Games are played to **21 points**
- If tied at **20–20**, the game continues until one side leads by **2**
- There is a hard cap so games end at **30–29** at the latest

---

## 6. Offline / “We missed it” scenarios (Bulk score entry)

CourtMaster Pro supports a practical fallback when internet is lost or a game cannot be entered point-by-point.

### Bulk score entry behavior

- Umpires can enter the **final game score** in one submission.
- That game’s timeline is marked clearly as an offline/bulk entry (so viewers know it’s not point-by-point).

### Mixed mode within the same match

It is supported that some games in the same match are entered live (point-by-point) while other games are entered by bulk score—both are handled correctly.

---

## 7. Special bracket cases (Handled automatically)

CourtMaster Pro handles these bracket scenarios consistently:

1. **BYE**
   - When a draw slot is left blank, the player/team advances automatically.
   - No match is created for that BYE.
2. **Walkover**
   - If a player/team does not show up before the match starts, the opponent advances without playing.
3. **Retirement**
   - If a player cannot continue mid-match, the match ends with the opponent declared the winner.
   - Scores up to that point are preserved.
4. **Not Required (team events)**
   - In a team tie, once one team reaches **3 wins**, any remaining inner matches are marked “Not Required” and never started.

---

## 8. Admin corrections & resets (without creating confusion)

CourtMaster Pro supports corrections while preventing unsafe changes that would break bracket consistency.

### A. What admins can edit freely

- **Before a match begins** (pending): admins can update player names and certain match details safely.

### B. What admins cannot change directly (during live play)

- Umpires control point-by-point scoring during live matches.

### C. Reset match (full wipe of score + history)

If a match is finished and a full redo is required, admins can use a reset:

- The match goes back to a “not started” state.
- The match winner’s bracket slot is cleared so the bracket can be rebuilt correctly.

Reset is blocked if the winner’s next match has already started (so the bracket cannot become inconsistent).

### D. Score correction (minor corrections)

For situations where a correction is needed but the match winner should not change, admins can apply a correction option:

- The system records the change transparently.
- If the correction would change the winner, a full reset is required instead.
- Corrections are also blocked if the winner has already played the next match.

### E. Full transparency

Every correction/reset is recorded so there is an auditable trail for the tournament admin team.

---

## 9. Team event tie handling (how winners are decided)

Team events are structured like this:

- Each tie contains exactly **5 inner matches** in a fixed order:
  - S1, D1, S2, D2, S3
- The tie winner is the first team to win **3** inner matches.
- If the tie is decided early:
  - remaining inner matches are marked **Not Required**
  - they are not started

When inner matches finish, the tie results stay consistent automatically.

---

## 10. Live score displays and in-venue use

CourtMaster Pro provides live score viewing designed for in-venue use, including:

- A live view for **all courts**
- Live views by **court number (1 to 5)**

These views provide fast, spectator-friendly score updates without exposing full admin controls.

---

## 11. PDF match summaries

Authorized users can download an **A4 PDF summary** for a match.

---

## 12. Authentication and secure access

CourtMaster Pro uses secure login for officials and supports:

- Role-based access (Admin vs Umpires)
- Two-factor authentication (2FA) management
- Password update workflows

---

## 13. Public discovery (SEO)

The public pages used for viewing tournaments and live scoreboards are included in the site’s sitemap so they are easier to discover.

