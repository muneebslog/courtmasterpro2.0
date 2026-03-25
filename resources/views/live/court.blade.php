<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Court') }} {{ $court }} — {{ __('Live') }}</title>
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="description" content="Live badminton match scores for Court {{ $court }} on CourtMaster.">
    <meta name="robots" content="index,follow">

    <meta property="og:title" content="{{ __('Court') }} {{ $court }} — {{ __('Live') }}">
    <meta property="og:description"
        content="Live badminton match scores for Court {{ $court }} on CourtMaster.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('imgs/hero.png') }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ __('Court') }} {{ $court }} — {{ __('Live') }}">
    <meta name="twitter:description"
        content="Live badminton match scores for Court {{ $court }} on CourtMaster.">
    <meta name="twitter:image" content="{{ asset('imgs/hero.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #111;
            font-family: 'Roboto Condensed', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: clamp(8px, 2vw, 20px);
            -webkit-text-size-adjust: 100%;
        }

        .court-tag {
            position: fixed;
            top: 6px;
            left: 8px;
            font-size: 9px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #444;
            z-index: 2;
        }

        #load-err {
            display: none;
            width: 100%;
            max-width: 900px;
            text-align: center;
            font-size: 14px;
            color: #e8192f;
            padding: 8px;
            margin-bottom: 8px;
        }

        .idle {
            width: 100%;
            max-width: 900px;
            text-align: center;
            font-size: 18px;
            padding: 40px 16px;
            color: #888;
            background: #1a1a1a;
            border-radius: 4px;
            border-left: 5px solid #e8192f;
        }

        .board {
            display: none;
            width: 100%;
            max-width: 900px;
        }

        .board.is-on {
            display: block;
        }

        .board-inner {
            --set-cols: 3;
        }

        /* HEADER */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #1a1a1a;
            border-left: 5px solid #e8192f;
            padding: clamp(8px, 2vw, 14px) clamp(12px, 3vw, 24px);
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .header-left {
            min-width: 0;
            flex: 1;
        }

        .event-title {
            font-family: 'Oswald', sans-serif;
            font-size: clamp(14px, 3.5vw, 18px);
            letter-spacing: 0.12em;
            color: #aaa;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .header-meta {
            margin-top: 2px;
            font-size: 10px;
            letter-spacing: 0.1em;
            color: #555;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sponsor {
            flex-shrink: 0;
            font-family: 'Oswald', sans-serif;
            font-size: clamp(12px, 2.8vw, 16px);
            color: #555;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: right;
            max-width: 45%;
        }

        .sponsor img {
            display: block;
            max-height: clamp(22px, 6vw, 36px);
            max-width: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            margin-left: auto;
        }

        /* SET HEADERS */
        .col-headers {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 52px repeat(var(--set-cols), minmax(52px, 1fr));
            gap: 6px;
            padding: 0 6px 6px;
            text-align: center;
        }

        .col-headers span {
            font-size: 11px;
            letter-spacing: 0.18em;
            color: #444;
            text-transform: uppercase;
            font-weight: 700;
        }

        /* PLAYER ROW */
        .row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 52px repeat(var(--set-cols), minmax(52px, 1fr));
            gap: 6px;
            align-items: center;
            background: #1a1a1a;
            border-radius: 6px;
            padding: clamp(10px, 2.5vw, 18px) clamp(10px, 2vw, 16px);
            margin-bottom: 6px;
            border: 1px solid #222;
        }

        .row:last-child {
            margin-bottom: 0;
        }

        .row.winner {
            border-color: #e8192f;
            background: #1c1012;
        }

        .name {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .player-name {
            font-family: 'Oswald', sans-serif;
            font-size: clamp(18px, 5vw, 32px);
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .player-team {
            font-size: clamp(10px, 2.2vw, 12px);
            letter-spacing: 0.14em;
            color: #555;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .seed {
            width: 40px;
            height: 40px;
            max-width: 100%;
            background: #f5c842;
            color: #111;
            font-family: 'Oswald', sans-serif;
            font-size: 20px;
            font-weight: 700;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            justify-self: center;
        }

        @media (max-width: 400px) {
            .seed {
                width: 36px;
                height: 36px;
                font-size: 17px;
            }

            .col-headers,
            .row {
                grid-template-columns: minmax(0, 1fr) 40px repeat(var(--set-cols), minmax(44px, 1fr));
            }
        }

        /* SCORE TILE */
        .score {
            min-height: clamp(56px, 14vh, 80px);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Oswald', sans-serif;
            font-size: clamp(26px, 9vw, 52px);
            font-weight: 700;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
        }

        .score.won {
            background: #e8192f;
            color: #fff;
        }

        .score.lost {
            background: #1f1f1f;
            color: #3a3a3a;
        }

        .score.current {
            background: #252525;
            color: #fff;
            border: 2px solid #e8192f;
        }
    </style>
</head>

<body>
    <div class="court-tag">{{ __('Court') }} {{ $court }}</div>
    <div id="load-err"></div>

    <div id="idle" class="idle">{{ __('No current match') }}</div>

    <div id="board" class="board">
        <div class="board-inner" id="board-inner">
            <div class="header">
                <div class="header-left">
                    <div id="banner-event" class="event-title"></div>
                    <div id="banner-meta" class="header-meta"></div>
                </div>
                <div class="sponsor">
                    <img src="{{ asset('imgs/scoreboard-sponsor.svg') }}" alt="{{ __('Sponsor') }}">
                </div>
            </div>

            <div id="col-headers" class="col-headers"></div>
            <div id="player-rows"></div>
        </div>
    </div>

    <script>
        (function () {
            var POLL_MS = 500;
            var POLL_URL = {!! json_encode(route('api.live.court.score', ['court' => $court]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!};

            var idleEl = document.getElementById('idle');
            var boardEl = document.getElementById('board');
            var boardInnerEl = document.getElementById('board-inner');
            var errEl = document.getElementById('load-err');
            var bannerEventEl = document.getElementById('banner-event');
            var bannerMetaEl = document.getElementById('banner-meta');
            var colHeadersEl = document.getElementById('col-headers');
            var playerRowsEl = document.getElementById('player-rows');

            function splitPlayerLabel(label) {
                if (!label) {
                    return { name: '—', sub: '' };
                }
                var parts = label.split(/\s*\|\s*|\n\r?|\r/);
                if (parts.length > 1) {
                    return { name: parts[0].trim(), sub: parts.slice(1).join(' · ').trim() };
                }
                return { name: label.trim(), sub: '' };
            }

            function buildSlots(m) {
                var best = m.best_of ? parseInt(m.best_of, 10) : 3;
                if (best < 1) {
                    best = 1;
                }
                if (best > 5) {
                    best = 5;
                }
                var games = m.games || [];
                var slots = [];
                var i;
                var j;
                for (i = 1; i <= best; i++) {
                    var g = null;
                    for (j = 0; j < games.length; j++) {
                        if (parseInt(games[j].game_number, 10) === i) {
                            g = games[j];
                            break;
                        }
                    }
                    if (g) {
                        slots.push(g);
                    } else {
                        slots.push({ game_number: i, score_a: 0, score_b: 0, winner_side: null });
                    }
                }
                return slots;
            }

            function activeSlotIndex(slots) {
                var i;
                for (i = 0; i < slots.length; i++) {
                    if (!slots[i].winner_side) {
                        return i;
                    }
                }
                return slots.length ? slots.length - 1 : 0;
            }

            function formatSetScore(n) {
                n = parseInt(n, 10);
                if (isNaN(n)) {
                    n = 0;
                }
                if (n < 10) {
                    return '0' + n;
                }
                return String(n);
            }

            function scoreClassForCell(side, slot, setIndex, activeIdx) {
                var ws = slot.winner_side;
                if (ws === 'a' || ws === 'b') {
                    if (side === ws) {
                        return 'score won';
                    }
                    return 'score lost';
                }
                if (setIndex === activeIdx) {
                    return 'score current';
                }
                return 'score lost';
            }

            function renderColHeaders(nSets) {
                colHeadersEl.innerHTML = '';
                colHeadersEl.appendChild(document.createElement('span'));
                colHeadersEl.appendChild(document.createElement('span'));
                var i;
                for (i = 0; i < nSets; i++) {
                    var sp = document.createElement('span');
                    sp.textContent = '{{ __('Set') }} ' + (i + 1);
                    colHeadersEl.appendChild(sp);
                }
            }

            function renderPlayerRow(side, num, label, slots, activeIdx, matchWinnerSide) {
                var parts = splitPlayerLabel(label);
                var row = document.createElement('div');
                row.className = 'row';
                if (matchWinnerSide && side === matchWinnerSide) {
                    row.className += ' winner';
                }

                var nameWrap = document.createElement('div');
                nameWrap.className = 'name';
                var nameEl = document.createElement('div');
                nameEl.className = 'player-name';
                nameEl.textContent = parts.name;
                nameEl.title = label || '';
                nameWrap.appendChild(nameEl);
                if (parts.sub) {
                    var teamEl = document.createElement('div');
                    teamEl.className = 'player-team';
                    teamEl.textContent = parts.sub;
                    teamEl.title = parts.sub;
                    nameWrap.appendChild(teamEl);
                }

                var seed = document.createElement('div');
                seed.className = 'seed';
                seed.textContent = String(num);

                row.appendChild(nameWrap);
                row.appendChild(seed);

                var i;
                for (i = 0; i < slots.length; i++) {
                    var slot = slots[i];
                    var cell = document.createElement('div');
                    cell.className = scoreClassForCell(side, slot, i, activeIdx);
                    var score = side === 'a' ? slot.score_a : slot.score_b;
                    cell.textContent = formatSetScore(score);
                    row.appendChild(cell);
                }

                return row;
            }

            function applyPayload(data) {
                errEl.style.display = 'none';
                errEl.textContent = '';

                if (!data || !data.match) {
                    idleEl.style.display = 'block';
                    boardEl.className = 'board';
                    return;
                }

                var m = data.match;
                idleEl.style.display = 'none';
                boardEl.className = 'board is-on';

                bannerEventEl.textContent = m.event_name || '{{ __('Match') }}';

                var metaBits = [];
                if (m.stage_name) {
                    metaBits.push(m.stage_name);
                }
                metaBits.push('{{ __('Court') }} {{ $court }}');
                if (m.best_of) {
                    metaBits.push('{{ __('Best of') }} ' + m.best_of);
                }
                bannerMetaEl.textContent = metaBits.join(' · ');

                var slots = buildSlots(m);
                var n = slots.length;
                boardInnerEl.style.setProperty('--set-cols', String(n));

                var activeIdx = activeSlotIndex(slots);
                var matchWinner = m.winner_side || null;

                renderColHeaders(n);

                playerRowsEl.innerHTML = '';
                playerRowsEl.appendChild(renderPlayerRow('a', 1, m.side_a_label, slots, activeIdx, matchWinner));
                playerRowsEl.appendChild(renderPlayerRow('b', 2, m.side_b_label, slots, activeIdx, matchWinner));
            }

            function poll() {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', POLL_URL, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) {
                        return;
                    }
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            applyPayload(JSON.parse(xhr.responseText));
                        } catch (e) {
                            errEl.style.display = 'block';
                            errEl.textContent = '{{ __('Unable to load') }}';
                        }
                    } else {
                        errEl.style.display = 'block';
                        errEl.textContent = '{{ __('Unable to load') }}';
                    }
                };
                xhr.send(null);
            }

            poll();
            setInterval(poll, POLL_MS);
        })();
    </script>
</body>

</html>
