# CourtMaster Pro
Badminton tournament management system built with Laravel + Livewire (Flux UI).

## Features
- Tournament hub (spectator-facing)
  - Shows only published tournaments.
  - “Live hub” viewer that lets users follow draws and live scores without needing an account.
- Tournament administration (authenticated)
  - Role-based access control for `admin` and `umpires`.
  - Admins create tournaments (stored as `draft`) and expose them to spectators once published.
- Event management (per tournament)
  - Events support `Singles`, `Doubles`, and `Team`.
  - Event page can create the first bracket stage, and lets authorized users edit/delete events when safe.
  - Stage creation is bracket-driven: the number of matches must be a power of two (round naming follows common badminton bracket labels like Final / Semi Final / Quarter Final / etc.).
- Stage, tie, and match navigation
  - Viewer pages provide a clear drill-down flow: Tournament -> Event -> Stage -> (Tie | Match) -> Match details.
  - Team events render “ties” containing the line matches (e.g., S1 -> D1 -> S2 -> D2 -> S3).
- Live match scoring (authenticated umpires)
  - Arena-style control panel to start a match, record points, undo the last point, and advance through games/rounds.
  - Logs match occurrences (cards, injury, walkover) and produces a match event history.
  - End-game and end-match handling for statuses like completed, walkover, retired, and not-required.
- Bulk score entry (authenticated)
  - Enter multiple game scores in one submission (useful for quick offline/after-match input).
  - Validates badminton scoring rules (including 21 with 2-point lead and the 30–29 extension case).
  - Submitting bulk scores starts the match automatically if it is currently pending.
- Tie result propagation (team events)
  - When inner matches finish, tie calculations are updated so the tie winner and “not required” matches are kept consistent.
- Spectator match viewer
  - Shows round scores and a match timeline.
  - Supports polling while matches are `in_progress` and provides a “show full timeline” toggle.
- Live score displays + API
  - UI endpoints:
    - `GET /live/court/{court}` (court 1–5)
    - `GET /live/all`
  - JSON API:
    - `GET /api/live/court/{court}` (throttled)
  - Supports in-venue scoreboards without exposing full admin tools.
- PDF match summaries
  - Authorized users can download an A4 PDF summary for a match.
- Security & user settings
  - Fortify-based authentication.
  - Two-factor authentication (2FA) management, plus password update workflows.
- SEO support
  - `sitemap.xml` includes home and live scoreboard URLs.

## Project URLs (main entry points)
- Viewer:
  - `/` (home page)
  - Live spectator mode: `/viewer/tournaments` and nested viewer pages
- Live score:
  - `/live/all`
  - `/live/court/{court}`

## Local development (quick start)
1. Install dependencies:
   - `composer install`
   - `npm install`
2. Configure environment:
   - Copy `.env.example` to `.env` if needed, then run `php artisan key:generate`
3. Database:
   - `php artisan migrate --force`
4. Frontend:
   - `npm run dev` (or `npm run build` for production-like builds)

