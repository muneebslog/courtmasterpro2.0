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

        .fullscreen-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: #ff1744;
            border: 3px solid #fff;
            color: #fff;
            font-size: 28px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 99999;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.4);
            transition: transform 0.2s ease, background 0.2s ease;
            padding: 0;
        }

        .fullscreen-btn:hover {
            transform: scale(1.15);
            background: #d50000;
        }

        .fullscreen-btn:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body>
    <button type="button" id="fullscreenBtn" class="fullscreen-btn" aria-label="{{ __('Full screen') }}">⛶</button>

    <div class="courts-grid" role="application" aria-label="All courts live scoreboard">
        @foreach ($courts as $court)
            <iframe
                title="{{ __('Court') }} {{ $court }}"
                src="{{ route('live.court', ['court' => $court, 'embed' => '1']) }}"
                loading="eager"
                referrerpolicy="no-referrer"
            ></iframe>
        @endforeach
    </div>

    <script>
        (function () {
            var fsBtn = document.getElementById('fullscreenBtn');
            if (!fsBtn) {
                return;
            }
            fsBtn.addEventListener('click', function () {
                var doc = document;
                var el = doc.documentElement;
                if (!doc.fullscreenElement && !doc.webkitFullscreenElement && !doc.msFullscreenElement) {
                    var req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
                    if (req) {
                        req.call(el);
                    }
                } else {
                    var exit = doc.exitFullscreen || doc.webkitExitFullscreen || doc.msExitFullscreen;
                    if (exit) {
                        exit.call(doc);
                    }
                }
            });
        })();
    </script>
</body>
</html>
