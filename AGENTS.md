# CourtMaster Pro — Agent Guidelines

> Badminton Tournament Management System built with Laravel + Livewire (Flux UI).
> This document provides essential context for AI coding agents working on this codebase.

---

## Project Overview

CourtMaster Pro is a web-based tournament management system designed for badminton federations. It manages tournaments from creation through to final results, providing real-time live scores to spectators.

### Key Capabilities

- **Tournament Management**: Create and manage tournaments with multiple events
- **Event Types**: Support for Singles, Doubles, and Team events
- **Bracket Management**: Automatic stage progression with power-of-2 bracket validation
- **Live Scoring**: Real-time point-by-point match scoring with undo support
- **Team Events**: Fixed 5-match tie structure (S1, D1, S2, D2, S3)
- **Public Viewer**: Read-only tournament viewing with live score updates
- **PDF Generation**: Match and tie summary exports
- **Audit Trail**: Complete event logging for all match actions

---

## Technology Stack

| Component | Version | Purpose |
|-----------|---------|---------|
| PHP | ^8.3 | Server-side language |
| Laravel | ^13.0 | Backend framework |
| Livewire | ^4.1 | Reactive UI components |
| Flux UI | ^2.12 | UI component library (Livewire-based) |
| Tailwind CSS | ^4.0.7 | Styling framework |
| Vite | ^8.0 | Build tool |
| Pest | ^4.4 | Testing framework |
| Laravel Fortify | ^1.34 | Authentication backend |
| Laravel Pint | ^1.27 | Code formatting |

### Key Dependencies

```json
// composer.json
"require": {
    "php": "^8.3",
    "laravel/framework": "^13.0",
    "laravel/fortify": "^1.34",
    "livewire/livewire": "^4.1",
    "livewire/flux": "^2.12.0",
    "barryvdh/laravel-dompdf": "^3.1"
}
```

---

## Project Structure

```
courtmaster/
├── app/
│   ├── Actions/Fortify/       # Fortify auth actions (CreateNewUser, etc.)
│   ├── Concerns/              # Shared traits (PasswordValidationRules, etc.)
│   ├── Http/Controllers/      # Standard controllers
│   ├── Livewire/              # Livewire components (actions, concerns)
│   ├── Models/                # Eloquent models
│   ├── Providers/             # Service providers
│   ├── Services/              # Business logic (NextStageService, TieResultService)
│   └── Support/               # Helper classes (BracketStageNaming, etc.)
├── config/                    # Laravel configuration
├── database/
│   ├── factories/             # Model factories
│   ├── migrations/            # Database migrations
│   └── seeders/               # Database seeders
├── resources/
│   ├── css/app.css            # Tailwind CSS entry
│   ├── js/app.js              # JavaScript entry
│   └── views/                 # Blade templates
│       ├── components/        # Blade components (⚡ prefix = Livewire)
│       ├── layouts/           # App layouts (app, auth)
│       ├── pages/             # Page templates (⚡ prefix = Livewire)
│       ├── pdf/               # PDF templates
│       └── live/              # Live scoreboard views
├── routes/
│   ├── web.php                # Web routes
│   ├── settings.php           # User settings routes
│   └── console.php            # Console commands
└── tests/
    ├── Feature/               # Feature tests (Pest)
    └── Unit/                  # Unit tests (Pest)
```

---

## Build and Development Commands

### Setup (Fresh Installation)

```bash
composer setup
```

This runs:
1. `composer install`
2. Copies `.env.example` to `.env` if needed
3. Generates application key
4. Runs migrations
5. Installs npm dependencies
6. Builds frontend assets

### Development Server

```bash
composer dev
```

Runs concurrently:
- `php artisan serve` (Laravel server)
- `php artisan queue:listen` (Queue worker)
- `npm run dev` (Vite dev server)

### Frontend Build

```bash
npm run dev     # Development mode
npm run build   # Production build
```

### Code Quality

```bash
# Run linter (auto-fix)
composer lint

# Check code style (CI)
composer lint:check

# Run all tests
composer test

# Quick CI check
composer ci:check
```

---

## Code Style Guidelines

### PHP Standards

- **Preset**: Laravel (via Laravel Pint)
- **Formatting**: Run `vendor/bin/pint --dirty --format agent` after modifying PHP files
- **Constructor Property Promotion**: Use for dependency injection

```php
// Good
public function __construct(public GitHub $github) { }

// Avoid: Empty constructors
public function __construct() { }
```

### Type Declarations

Always use explicit return types and parameter type hints:

```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    // ...
}
```

### Comments

- Prefer PHPDoc blocks over inline comments
- Never use comments within code unless logic is exceptionally complex
- Use array shape type definitions when appropriate

### Naming Conventions

- **Variables/Methods**: Descriptive names (`isRegisteredForDiscounts`, not `discount()`)
- **Enums**: TitleCase keys (`FavoritePerson`, `BestLake`)
- **Database**: Snake_case columns, plural table names

---

## Testing

### Framework

- **Test Runner**: Pest PHP 4
- **Base Class**: `Tests\TestCase`
- **Database**: `RefreshDatabase` trait applied automatically in Feature tests

### Running Tests

```bash
# Run all tests compactly
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/DashboardTest.php

# Run with filter
php artisan test --compact --filter=testName
```

### Creating Tests

```bash
# Feature test
php artisan make:test --pest FeatureNameTest

# Unit test
php artisan make:test --pest --unit UnitNameTest
```

### Test Structure

```php
// Feature test example
it('allows admins to create tournaments', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    
    $this->actingAs($admin)
        ->post(route('dashboard.tournaments.store'), [
            'tournament_name' => 'Test Tournament',
            'location' => 'Test Venue',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
        ])
        ->assertRedirect();
    
    expect(Tournament::count())->toBe(1);
});
```

---

## Architecture Patterns

### Domain Model Hierarchy

```
Tournament
└── Event (singles | doubles | team)
    ├── Stage (round)
    │   ├── Tie (team events only)
    │   │   └── Match (S1, D1, S2, D2, S3)
    │   └── Match (individual events)
    │       ├── Game
    │       └── MatchEvent (audit timeline)
    └── Team (team events only)
        └── TeamPlayer
```

### Key Business Rules

1. **Admin = 1 Tournament**: One admin account manages one active tournament
2. **Events are Independent**: Different events can be at different stages simultaneously
3. **Stages are Sequential**: Within an event, stages progress one at a time
4. **Auto-Advance**: System auto-creates next stage when current stage completes
5. **Team Tie Structure**: Exactly 5 matches in fixed order (S1, D1, S2, D2, S3)
6. **Player Eligibility**: Max 1 singles + 1 doubles per player per tie
7. **BYE Handling**: Blank opponent = auto-advance, no match created
8. **Deuce Cap**: 20-20 → win by 2 → hard cap at 30-29
9. **Reset Protection**: Cannot reset match if winner's next match has started

### Eloquent Patterns

```php
// Prefer relationship methods over raw queries
$tournament->events()->create([...]);

// Eager loading to prevent N+1
Tie::with(['teamA', 'teamB', 'matches'])->get();

// Use Model::query() instead of DB::table()
MatchModel::query()->where('status', 'completed')->get();
```

### Service Classes

Complex business logic lives in Services:

- `NextStageService`: Stage progression, winner advancement, bracket generation
- `TieResultService`: Tie winner calculation, match result propagation

---

## Livewire Conventions

### Component Naming

Livewire components use the `⚡` prefix in filenames:

```
resources/views/components/⚡tournament-details.blade.php
resources/views/pages/⚡event.blade.php
resources/views/pages/event/⚡match.blade.php
```

### Route Registration

```php
// In routes/web.php
Route::livewire('viewer/tournaments', 'pages::viewer.tournaments')
    ->name('viewer.tournaments.index');
```

### State Management

- Keep state server-side
- Validate and authorize in actions (like HTTP requests)
- Use Alpine.js for client-side interactions

---

## Authentication & Authorization

### User Roles

```php
User::ROLE_ADMIN    = 'admin';     // Full tournament control
User::ROLE_UMPIRES  = 'umpires';   // Match scoring (shared account)
```

### Middleware

```php
// Auth routes
Route::middleware(['auth', 'verified'])->group(function () {
    // ...
});

// Role-specific (in controllers)
abort_unless(
    $user instanceof User && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_UMPIRES], true),
    403
);
```

### Fortify Features

- Login / Registration
- Email verification
- Password reset
- Two-factor authentication (optional)
- Profile updates

---

## Database Conventions

### Migrations

```bash
php artisan make:migration create_tablename_table
```

### MySQL vs SQLite

Several migrations branch on driver for enum support:

```php
if (Schema::getConnection()->getDriverName() === 'sqlite') {
    $table->string('status')->default('pending');
} else {
    $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
}
```

### Relationships

| Model | Relationship | Target |
|-------|-------------|--------|
| User | tournaments() | Tournament (many-to-many) |
| Tournament | events() | Event (has-many) |
| Tournament | users() | User (many-to-many) |
| Event | stages() | Stage (has-many) |
| Event | teams() | Team (has-many) |
| Stage | ties() | Tie (has-many) |
| Stage | matches() | MatchModel (has-many) |
| Tie | matches() | MatchModel (has-many) |
| MatchModel | matchPlayers() | MatchPlayer (has-many) |
| MatchModel | games() | Game (has-many) |
| MatchModel | matchEvents() | MatchEvent (has-many) |
| Team | teamPlayers() | TeamPlayer (has-many) |

---

## Frontend Conventions

### CSS Framework

- **Tailwind CSS v4** with Vite plugin
- **Flux UI** components imported from `vendor/livewire/flux`

### CSS Entry Point

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
```

### Blade Components

```blade
{{-- Flux UI components --}}
<flux:button variant="primary">Save</flux:button>
<flux:input wire:model="name" label="Tournament Name" />

{{-- Custom app components --}}
<x-app-logo />
<x-action-message on="saved">Saved!</x-action-message>
```

---

## Common Tasks

### Creating a New Model

```bash
php artisan make:model -f -s -c -r ModelName
```

Options:
- `-f` Factory
- `-s` Seeder
- `-c` Controller
- `-r` Resource controller

### Creating a Livewire Component

Create a Blade file with `⚡` prefix:

```bash
# Create resources/views/pages/⚡component.blade.php
# Register in routes/web.php with Route::livewire()
```

### Adding a Route

```php
// Public route
Route::view('/', 'welcome')->name('home');

// Controller route
Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])
    ->name('tournaments.show');

// Livewire route
Route::livewire('viewer/tournaments', 'pages::viewer.tournaments')
    ->name('viewer.tournaments.index');
```

---

## Security Considerations

1. **Authentication**: Fortify-based, with verified email middleware where required
2. **Authorization**: Role-based checks in controllers; abort_unless() for permission gates
3. **CSRF**: Automatic via Laravel's web middleware
4. **XSS**: Blade's `{{ }}` escaping by default
5. **Rate Limiting**: Throttle applied to live score API (`throttle:live-court-score`)
6. **Password Hashing**: Bcrypt via Laravel's default
7. **Two-Factor Auth**: Optional TOTP support via Fortify

---

## Key Files Reference

| Purpose | File |
|---------|------|
| Main routes | `routes/web.php` |
| Settings routes | `routes/settings.php` |
| App config | `config/app.php` |
| Fortify config | `config/fortify.php` |
| Database config | `config/database.php` |
| Pint config | `pint.json` |
| Vite config | `vite.config.js` |
| Package deps | `composer.json`, `package.json` |
| Full spec | `info.md` |
| Client overview | `client.md` |
| Database docs | `db.md` |

---

## Troubleshooting

### Vite Manifest Error

If you see "Unable to locate file in Vite manifest":

```bash
npm run build
# or
npm run dev
```

### Test Database Issues

Tests use `RefreshDatabase` trait with SQLite by default. Ensure `phpunit.xml` is configured correctly.

### Queue Workers

For production, use `php artisan queue:work` instead of `queue:listen`.

---

*Last updated: 2026-04-06*
*CourtMaster Pro v1.0*
