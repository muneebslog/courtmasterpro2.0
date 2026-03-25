@props([
    'title' => null,
    'description' => null,
    'image' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scheme-dark dark">
    <head>
        @php
            $pageTitle = filled($title ?? null) ? $title.' — '.config('app.name') : config('app.name');
            $pageDescription = filled($description ?? null)
                ? $description
                : 'Public badminton scores and live match scoring for tournament brackets.';
            $pageImage = filled($image ?? null) ? $image : asset('imgs/hero.png');
            $canonicalUrl = url()->current();
        @endphp

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=syne:600,700|dm-sans:400,500,600" rel="stylesheet" />

        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $pageTitle }}</title>
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta name="description" content="{{ $pageDescription }}">
        <meta name="robots" content="index,follow">

        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $pageImage }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ $pageImage }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => $canonicalUrl,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    </head>
    <body
        class="min-h-screen bg-[#0c1210] bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(34,197,94,0.12),transparent)] font-[family-name:var(--font-dm)] text-[#e8ece9] antialiased [--font-dm:'DM_Sans',ui-sans-serif,system-ui,sans-serif]"
        style="font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;"
    >
        <div class="pointer-events-none fixed inset-0 opacity-[0.03] mix-blend-overlay"
            style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22n%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23n)%22/%3E%3C/svg%3E');">
        </div>

        <header
            class="relative z-10 border-b border-emerald-900/40 bg-[#0c1210]/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}"
                    class="group flex items-center gap-3 no-underline">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-lg font-bold text-white shadow-lg shadow-emerald-900/40 transition group-hover:scale-[1.02]"
                        style="font-family: 'Syne', sans-serif;">CM</span>
                    <div>
                        <span class="block text-sm font-semibold tracking-tight text-white"
                            style="font-family: 'Syne', sans-serif;">CourtMaster</span>
                        <span class="text-xs text-emerald-200/70">{{ __('Public scores') }}</span>
                    </div>
                </a>
                <nav class="flex flex-wrap items-center gap-2">
                    <flux:button variant="ghost" size="sm" :href="route('viewer.tournaments.index')" class="text-emerald-100/90">
                        {{ __('Tournaments') }}
                    </flux:button>
                    @if (Route::has('login'))
                        <flux:button variant="primary" size="sm" :href="route('login')" class="bg-emerald-600 hover:bg-emerald-500">
                            {{ __('Log in') }}
                        </flux:button>
                    @endif
                </nav>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            {{ $slot }}
        </main>

        <footer class="relative z-10 border-t border-white/5 py-8 text-center text-xs text-white/40">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </footer>

        @fluxScripts
    </body>
</html>
