# Courtmaster — Database structure

This document reflects the **Laravel migrations** in `database/migrations` as of the current codebase. MySQL uses `enum` where noted; **SQLite** (e.g. tests) uses `string` columns for the same logical values.

---

## Entity overview

```
users
  └── tournaments.admin_id
  └── tournament_user (pivot)

tournaments
  └── events
        ├── stages
        │     ├── ties
        │     │     └── matches (tie_id set)
        │     └── matches (tie_id null for singles/doubles)
        └── teams
              └── team_players

matches
  ├── match_players
  ├── games
  └── match_events
```

---

## Application domain tables

### `users`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `name` | string | |
| `email` | string, unique | |
| `role` | string, default `umpires` | App: `admin`, `umpires` (`User::ROLE_*`) |
| `email_verified_at` | timestamp, nullable | |
| `password` | string | hashed |
| `two_factor_secret` | text, nullable | |
| `two_factor_recovery_codes` | text, nullable | |
| `two_factor_confirmed_at` | timestamp, nullable | |
| `remember_token` | string, nullable | |
| `created_at`, `updated_at` | timestamps | |

---

### `tournaments`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `tournament_name` | string | |
| `location` | string | |
| `start_date` | date | |
| `end_date` | date | |
| `admin_id` | FK → `users.id`, nullable, null on delete | owning admin |
| `status` | string, default `draft` | app: `draft`, `published` |
| `created_at`, `updated_at` | timestamps | |

---

### `tournament_user`

Pivot: users assigned to a tournament (e.g. umpires).

| Column | Type | Notes |
|--------|------|--------|
| `tournament_id` | FK → `tournaments.id`, cascade delete | |
| `user_id` | FK → `users.id`, cascade delete | |
| `created_at`, `updated_at` | timestamps | |
| | | **Unique:** (`tournament_id`, `user_id`) |

---

### `events`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `tournament_id` | FK → `tournaments.id`, cascade delete | |
| `event_name` | string | |
| `event_type` | string | `singles`, `doubles`, `team` |
| `created_at`, `updated_at` | timestamps | |

---

### `stages`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `event_id` | FK → `events.id`, cascade delete | |
| `name` | string | e.g. round name |
| `best_of` | unsigned tinyint | `1`, `3`, or `5` |
| `order_index` | unsigned integer | |
| `status` | enum/string, default `pending` | `pending`, `active`, `completed` |
| `created_at`, `updated_at` | timestamps | |

---

### `teams` (team events)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `event_id` | FK → `events.id`, cascade delete | scoped to event, not stage |
| `name` | string | |
| `created_at`, `updated_at` | timestamps | |

---

### `team_players`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `team_id` | FK → `teams.id`, cascade delete | |
| `player_name` | string | |
| `created_at`, `updated_at` | timestamps | |

---

### `ties` (team events, per stage)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `stage_id` | FK → `stages.id`, cascade delete | |
| `team_a_id` | FK → `teams.id`, cascade delete | |
| `team_b_id` | FK → `teams.id`, cascade delete | |
| `winner_team_id` | FK → `teams.id`, nullable, null on delete | |
| `status` | enum/string, default `pending` | `pending`, `in_progress`, `completed` |
| `created_at`, `updated_at` | timestamps | |

---

### `matches`

Eloquent model: `App\Models\MatchModel` (table name **`matches`**).

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `stage_id` | FK → `stages.id`, cascade delete | |
| `tie_id` | FK → `ties.id`, nullable, null on delete | set for team tie line matches |
| `side_a_label` | string | display label (names / team names) |
| `side_b_label` | string | |
| `match_order` | enum/string, nullable | `S1`, `D1`, `S2`, `D2`, `S3` for team matches |
| `status` | enum/string, default `pending` | see **Match status** below |
| `winner_side` | enum/string, nullable | `a`, `b` |
| `best_of` | unsigned tinyint | copied from stage at creation |
| `umpire_name` | string, nullable | |
| `service_judge_name` | string, nullable | |
| `court` | string, nullable | |
| `started_at` | timestamp, nullable | |
| `ended_at` | timestamp, nullable | |
| `created_at`, `updated_at` | timestamps | |

**Match status (MySQL enum / app values):**  
`pending`, `in_progress`, `completed`, `retired`, `walkover`, `not_required`

---

### `match_players`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `match_id` | FK → `matches.id`, cascade delete | |
| `side` | enum/string | `a`, `b` |
| `player_name` | string | |
| `position` | unsigned tinyint, default `1` | `1` or `2` for doubles |
| `created_at`, `updated_at` | timestamps | |

---

### `games` (games / “rounds” within a match)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `match_id` | FK → `matches.id`, cascade delete | |
| `game_number` | unsigned integer | 1-based |
| `score_a` | unsigned integer, default `0` | |
| `score_b` | unsigned integer, default `0` | |
| `winner_side` | enum/string, nullable | `a`, `b` |
| `entry_mode` | enum/string, default `live` | `live`, `bulk` |
| `started_at` | timestamp, nullable | |
| `ended_at` | timestamp, nullable | |
| `created_at`, `updated_at` | timestamps | |
| | | **Unique:** (`match_id`, `game_number`) |

---

### `match_events` (scoring / audit timeline)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint, PK | |
| `match_id` | FK → `matches.id`, cascade delete | |
| `game_id` | FK → `games.id`, nullable, null on delete | |
| `event_type` | enum/string | see **Event types** below |
| `created_by` | enum/string, default `umpire` | `umpire`, `admin` |
| `side` | enum/string, nullable | `a`, `b` |
| `player_name` | string, nullable | |
| `score_a_at_time` | unsigned integer, default `0` | |
| `score_b_at_time` | unsigned integer, default `0` | |
| `notes` | text, nullable | e.g. JSON for occurrence subtype |
| `created_at`, `updated_at` | timestamps | |
| | | **Index:** (`match_id`, `game_id`, `created_at`) — `match_events_timeline_idx` |

**Event types (MySQL enum):**  
`match_started`, `point`, `undo`, `occurrence`, `game_ended`, `bulk_score_entry`, `match_ended`, `match_reset`, `player_edit`, `score_correction`

---

## Laravel framework / infrastructure tables

### `password_reset_tokens`

| Column | Type |
|--------|------|
| `email` | string, PK |
| `token` | string |
| `created_at` | timestamp, nullable |

### `sessions`

| Column | Type |
|--------|------|
| `id` | string, PK |
| `user_id` | FK, nullable, indexed |
| `ip_address` | string(45), nullable |
| `user_agent` | text, nullable |
| `payload` | longText |
| `last_activity` | integer, indexed |

### `cache` / `cache_locks`

Standard Laravel database cache store.

### `jobs` / `job_batches` / `failed_jobs`

Standard Laravel queue tables.

---

## Relationships (summary)

| From | To | Relationship |
|------|-----|----------------|
| `User` | `Tournament` | `admin_id` on tournaments; many-to-many via `tournament_user` |
| `Tournament` | `Event` | one-to-many |
| `Event` | `Stage` | one-to-many |
| `Event` | `Team` | one-to-many |
| `Stage` | `Tie` | one-to-many |
| `Stage` | `Match` | one-to-many |
| `Tie` | `Match` | one-to-many (`matches.tie_id`) |
| `Match` | `MatchPlayer`, `Game`, `MatchEvent` | one-to-many each |
| `Team` | `TeamPlayer` | one-to-many |
| `Tie` | `Team` | `team_a_id`, `team_b_id`, `winner_team_id` |

---

## MySQL vs SQLite

Several migrations branch on `Schema::getConnection()->getDriverName()`:

- **MySQL:** `enum` columns for constrained string sets (`stages.status`, `ties.status`, `matches.match_order`, `matches.status`, `matches.winner_side`, `games.winner_side`, `games.entry_mode`, `match_events.event_type`, `match_events.created_by`, `match_events.side`, `match_players.side`).
- **SQLite:** those columns are `string` with the same allowed values enforced in application logic.

---

## Maintenance

After schema changes, update this file or regenerate from migrations (e.g. `php artisan schema:dump` for a SQL snapshot; this document is hand-maintained for readability).
