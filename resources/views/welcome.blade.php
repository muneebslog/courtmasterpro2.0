<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>CourtMaster Pro</title>

    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="description"
        content="Manage draws, track shuttlecock inventory, and oversee live match scoring with our unified tournament dashboard.">
    <meta name="robots" content="index,follow">

    <meta property="og:title" content="CourtMaster Pro">
    <meta property="og:description"
        content="Manage draws, track shuttlecock inventory, and oversee live match scoring with our unified tournament dashboard.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('imgs/hero.png') }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CourtMaster Pro">
    <meta name="twitter:description"
        content="Manage draws, track shuttlecock inventory, and oversee live match scoring with our unified tournament dashboard.">
    <meta name="twitter:image" content="{{ asset('imgs/hero.png') }}">

    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
</head>

<body class="wm-page">
    <header class="wm-header">
        @if (Route::has('login'))
            <nav class="wm-nav">
                @auth
                    <a href="{{ url('/dashboard') }}" class="wm-nav-link wm-nav-link--bordered">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="wm-nav-link">
                        Log in
                    </a>
                @endauth
            </nav>
        @endif
    </header>

    <div class="wm-shell">
        <main class="wm-card">

            <div class="wm-copy">
                <h1 class="wm-title">CourtMaster Pro</h1>
                <p class="wm-lead">
                    Manage draws, track shuttlecock inventory, and oversee live match scoring with our unified
                    tournament dashboard.
                </p>

                <div class="wm-actions">

                    <a href="{{ url('/dashboard') }}" class="wm-btn wm-btn--primary">
                        Officials Dashboard
                    </a>
                    <a href="{{ route('viewer.tournaments.index') }}" class="wm-btn wm-btn--outline">
                        View Matches
                    </a>
                </div>

                <div class="wm-tournaments">
                    <p class="wm-tournaments-label"> Tournaments</p>
                    <ul class="wm-tournaments-list">
                        <li>
                            <span class="wm-dot wm-dot--live" aria-hidden="true"></span>
                            Buraq 62nd National Championship (2026) - Peshawar -<span class="italic">Live</span>
                        </li>
                        <li>
                            <span class="wm-dot wm-dot--ended" aria-hidden="true"></span>
                            National Junior Badminton Championship (2025) - Lahore -<span class="italic">Ended</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="wm-hero">
                <img src="{{ asset('imgs/hero.png') }}" alt="Badminton Court" class="wm-hero-img" />
                <div class="wm-hero-overlay" aria-hidden="true"></div>
            </div>
        </main>
    </div>

    <footer class="wm-footer">
        &copy; {{ date('Y') }} Badminton Tournament Manager. Built By <a href="muneebbuilds.com">Muhammad Muneeb</a>.
        {{-- Hall screens: assign Court as 1–4 in the match control panel so it matches these links. --}}
        <div class="wm-screen-links">
            <a href="{{ route('live.all') }}" class="wm-screen-link wm-screen-link--all">
                {{ __('All screens') }}
            </a>
            @foreach (range(1, 4) as $screen)
                <a href="{{ route('live.court', ['court' => $screen]) }}" class="wm-screen-link">
                    Screen {{ $screen }}
                </a>
            @endforeach
        </div>

        <div class="wm-screen-links" style="margin-top: 10px;">
            <a href="{{ route('live.all', ['tv' => '1']) }}" class="wm-screen-link wm-screen-link--all">
                TV: {{ __('All screens') }}
            </a>
            <a href="{{ route('live.all', ['tv' => '1', 'debug' => '1']) }}" class="wm-screen-link wm-screen-link--all">
                TV Debug: {{ __('All screens') }}
            </a>
            @foreach (range(1, 4) as $screen)
                <a href="{{ route('live.court', ['court' => $screen, 'tv' => '1']) }}" class="wm-screen-link">
                    TV Screen {{ $screen }}
                </a>
                <a href="{{ route('live.court', ['court' => $screen, 'tv' => '1', 'debug' => '1']) }}" class="wm-screen-link">
                    TV Debug {{ $screen }}
                </a>
            @endforeach
        </div>
    </footer>
</body>

</html>
