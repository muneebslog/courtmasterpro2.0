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
            grid-template-rows: repeat(3, 1fr);
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
                allowfullscreen
            ></iframe>
        @endforeach
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live — All courts</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            background: #000;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
        }
        #page-header {
            text-align: center;
            font-size: 28px;
            padding: 20px 16px 8px;
            font-weight: bold;
        }
        #live-screen {
            display: none;
            width: 100%;
            min-height: calc(100% - 72px);
            box-sizing: border-box;
        }
        #live-screen.is-visible {
            display: table;
        }
        #empty-state {
            display: none;
            text-align: center;
            font-size: 36px;
            padding: 48px 24px;
            box-sizing: border-box;
            vertical-align: middle;
        }
        #empty-state.is-visible {
            display: table-cell;
        }
        #match-grid {
            display: none;
            grid-template-columns: 1fr;
            gap: 16px;
            padding: 16px;
            box-sizing: border-box;
        }
        #match-grid.is-visible {
            display: grid;
        }
        @media (min-width: 900px) {
            #match-grid.is-visible {
                grid-template-columns: 1fr 1fr;
            }
        }
        .match-card {
            border: 1px solid #333;
            border-radius: 4px;
            padding: 16px;
            box-sizing: border-box;
        }
        .match-court {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .match-event {
            font-size: 18px;
            color: #ccc;
            margin-bottom: 12px;
        }
        .match-sides {
            display: table;
            width: 100%;
            margin-bottom: 12px;
            font-size: 20px;
        }
        .match-sides-row {
            display: table-row;
        }
        .match-sides-cell {
            display: table-cell;
            width: 50%;
            padding: 4px 8px 4px 0;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .match-sides-cell:last-child {
            padding-right: 0;
            padding-left: 8px;
            text-align: right;
        }
        .game-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
            box-sizing: border-box;
        }
        .game-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .game-inner {
            display: table-row;
        }
        .game-label {
            display: table-cell;
            font-size: 18px;
            vertical-align: middle;
            padding-right: 12px;
            white-space: nowrap;
        }
        .game-scores {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 32px;
            font-weight: bold;
        }
        .score-sep {
            opacity: 0.6;
            padding: 0 6px;
        }
    </style>
</head>
<body>
    <div id="page-header">All courts — live</div>

    <div id="live-screen" class="is-visible">
        <div id="empty-state" class="is-visible">No Matches In Progress</div>
    </div>

    <div id="match-grid"></div>

    <script>
        var POLL_MS = 5000;
        var POLL_URL = '/api/live/all';

        function byId(id) {
            return document.getElementById(id);
        }

        function courtLabel(court) {
            if (court === null || court === undefined || court === '') {
                return 'Court —';
            }
            return 'Court ' + String(court);
        }

        function setEmptyVisible(visible) {
            var empty = byId('empty-state');
            var grid = byId('match-grid');
            var wrap = byId('live-screen');
            if (visible) {
                wrap.className = 'is-visible';
                empty.className = 'is-visible';
                grid.className = '';
            } else {
                wrap.className = '';
                empty.className = '';
                grid.className = 'is-visible';
            }
        }

        function clearEl(el) {
            while (el.firstChild) {
                el.removeChild(el.firstChild);
            }
        }

        function appendText(el, text) {
            el.appendChild(document.createTextNode(text));
        }

        function formatSideName(match, side) {
            var players = side === 'a' ? match.players_a : match.players_b;
            var label = side === 'a' ? match.side_a_label : match.side_b_label;
            if (players && players.length > 0) {
                var parts = [];
                var j;
                for (j = 0; j < players.length; j++) {
                    parts.push(String(players[j]));
                }
                return parts.join(' / ');
            }
            return label ? String(label) : '';
        }

        function renderCard(match) {
            var card = document.createElement('div');
            card.className = 'match-card';

            var court = document.createElement('div');
            court.className = 'match-court';
            appendText(court, courtLabel(match.court));

            var ev = document.createElement('div');
            ev.className = 'match-event';
            var eventLine = match.event_name ? String(match.event_name) : '';
            if (match.stage_name) {
                eventLine = eventLine ? eventLine + ' — ' + String(match.stage_name) : String(match.stage_name);
            }
            appendText(ev, eventLine);

            var sidesWrap = document.createElement('div');
            sidesWrap.className = 'match-sides';
            var row = document.createElement('div');
            row.className = 'match-sides-row';
            var cellA = document.createElement('div');
            cellA.className = 'match-sides-cell';
            appendText(cellA, formatSideName(match, 'a'));
            var cellB = document.createElement('div');
            cellB.className = 'match-sides-cell';
            appendText(cellB, formatSideName(match, 'b'));
            row.appendChild(cellA);
            row.appendChild(cellB);
            sidesWrap.appendChild(row);

            card.appendChild(court);
            card.appendChild(ev);
            card.appendChild(sidesWrap);

            var games = match.games || [];
            var gi;
            for (gi = 0; gi < games.length; gi++) {
                var g = games[gi];
                var gRow = document.createElement('div');
                gRow.className = 'game-row';
                var inner = document.createElement('div');
                inner.className = 'game-inner';

                var label = document.createElement('div');
                label.className = 'game-label';
                var gn = g.game_number != null ? g.game_number : g.number;
                appendText(label, 'Game ' + String(gn));

                var scores = document.createElement('div');
                scores.className = 'game-scores';
                appendText(scores, String(g.score_a));
                var sep = document.createElement('span');
                sep.className = 'score-sep';
                appendText(sep, '\u2014');
                scores.appendChild(sep);
                appendText(scores, String(g.score_b));

                inner.appendChild(label);
                inner.appendChild(scores);
                gRow.appendChild(inner);
                card.appendChild(gRow);
            }

            return card;
        }

        function applyPayload(data) {
            var list = Array.isArray(data) ? data : (data && data.matches ? data.matches : []);
            var grid = byId('match-grid');
            clearEl(grid);

            if (!list.length) {
                setEmptyVisible(true);
                return;
            }

            setEmptyVisible(false);
            var i;
            for (i = 0; i < list.length; i++) {
                grid.appendChild(renderCard(list[i]));
            }
        }

        function fetchStatus() {
            fetch(POLL_URL, { credentials: 'same-origin' })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    applyPayload(data);
                })
                .catch(function () {
                    /* keep last good state on transient errors */
                });
        }

        setInterval(fetchStatus, POLL_MS);
        fetchStatus();
    </script>
</body>
</html>
