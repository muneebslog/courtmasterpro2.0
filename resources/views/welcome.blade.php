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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
</head>

<body
    class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
    <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6">
        @if (Route::has('login'))
            <nav class="flex items-center justify-end gap-4">
                @auth
                    <a href="{{ url('/dashboard') }}"
                        class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm">
                        Log in
                    </a>
                @endauth
            </nav>
        @endif
    </header>

    <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow">
        <main
            class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row shadow-sm overflow-hidden border border-[#19140015] dark:border-[#ffffff10] rounded-lg">

            <div
                class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC]">
                <h1 class="mb-2 text-xl font-medium italic tracking-tight">CourtMaster Pro</h1>
                <p class="mb-6 text-[#706f6c] dark:text-[#A1A09A]">
                    Manage draws, track shuttlecock inventory, and oversee live match scoring with our unified
                    tournament dashboard.
                </p>

                <div class="flex flex-col sm:flex-row gap-3">

                    <a href="{{ url('/dashboard') }}"
                        class="flex items-center justify-center px-6 py-3 bg-[#1b1b18] text-white dark:bg-[#EDEDEC] dark:text-[#1b1b18] rounded-md font-medium transition-transform active:scale-95">
                        Officials Dashboard
                    </a>
                    <a href="{{ route('viewer.tournaments.index') }}"
                        class="flex items-center justify-center px-6 py-3 border border-[#19140035] dark:border-[#3E3E3A] hover:bg-[#f4f4f4] dark:hover:bg-[#1e1e1d] rounded-md font-medium transition-colors">
                        View Matches
                    </a>
                </div>

                <div class="mt-10 pt-6 border-t border-[#f0f0f0] dark:border-[#222]">
                    <p class="text-[11px] uppercase tracking-widest text-[#b5b5b0]"> Tournaments</p>
                    <ul class="mt-3 space-y-2 text-[#706f6c] dark:text-[#A1A09A]">
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            Buraq 62nd National Championship (2026) - Peshawar -<span class="italic">Live</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-700"></span>
                            National Junior Badminton Championship (2025) - Lahore -<span class="italic">Ended</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="relative overflow-hidden lg:w-1/3 min-h-[200px] lg:min-h-full border-b lg:border-b-0 lg:border-l border-[#19140010] dark:border-[#ffffff05]">
                <img src="{{ asset('imgs/hero.png') }}" alt="Badminton Court"
                    class="absolute inset-0 object-cover w-full h-full grayscale-[20%] hover:grayscale-0 transition-all duration-700" />
                <div class="absolute inset-0 bg-gradient-to-t from-[#1b1b18]/40 to-transparent"></div>
            </div>
        </main>
    </div>

    <footer class="py-10 text-center text-xs text-[#b5b5b0]">
        &copy; {{ date('Y') }} Badminton Tournament Manager. Built with Laravel.
        {{-- Hall screens: assign Court as 1–5 in the match control panel so it matches these links. --}}
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            @foreach (range(1, 5) as $screen)
                <a href="{{ route('live.court', ['court' => $screen]) }}"
                    class="inline-block rounded border border-[#19140020] dark:border-[#3E3E3A] px-2.5 py-1 text-[11px] font-medium text-[#706f6c] hover:border-[#19140035] hover:bg-[#f4f4f4] dark:text-[#A1A09A] dark:hover:bg-[#1e1e1d]">
                    Screen {{ $screen }}
                </a>
            @endforeach
        </div>
    </footer>
</body>

</html>

