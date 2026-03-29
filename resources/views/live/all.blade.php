@php
    $title = __('All courts — Live') . ' — ' . config('app.name');
    $canonicalUrl = url()->current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta name="description" content="{{ __('Live match scores for all courts.') }}">
    <meta name="robots" content="index,follow">

    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ __('Live match scores for all courts.') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('imgs/hero.png') }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ __('Live match scores for all courts.') }}">
    <meta name="twitter:image" content="{{ asset('imgs/hero.png') }}">

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #000;
        }

        .courts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            width: 100vw;
            height: 100vh;
            gap: 4px;
            padding: 4px;
            box-sizing: border-box;
            background: #000;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: 0;
            background: #000;
        }
    </style>
</head>
<body>
    <div class="courts-grid" role="application" aria-label="All courts live scoreboard">
        @foreach ($courts as $court)
            <iframe
                title="{{ __('Court') }} {{ $court }}"
                src="{{ route('live.court', ['court' => $court]) }}"
                loading="eager"
                referrerpolicy="no-referrer"
            ></iframe>
        @endforeach
    </div>
</body>
</html>
