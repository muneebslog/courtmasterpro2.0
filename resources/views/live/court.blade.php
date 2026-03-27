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
    <script src="https://cdn.jsdelivr.net/npm/twemoji@14.0.2/dist/twemoji.min.js" crossorigin="anonymous"></script>
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

    <style>
        /* --- Hall screen scoreboard (ported from resources/views/Oldscorebord.html) --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000;
            font-family: 'Arial Black', Arial, sans-serif;
            overflow: hidden;
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
            width: 100vw;
            height: 100vh;
            max-width: none;
        }

        .board.is-on {
            display: block;
        }

        .board-inner {
            width: 100%;
            height: 100%;
        }

        .scoreboard-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 2vh 2vw;
            gap: 2vh;
            background: #000;
        }

        .footer {
            background: #fff;
            border: 0.5vh solid #000;
            border-radius: 1.5vh;
            padding: 2vh 3vw;
            height: 12vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-title {
            font-size: 3vh;
            font-weight: 900;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.2vw;
            display: flex;
            align-items: center;
            gap: 2vw;
        }

        .footer-logo {
            height: 60px;
            width: auto;
        }

        .scoreboard {
            flex: 1;
            border: 0.5vh solid #000;
            border-radius: 1.5vh;
            display: flex;
            flex-direction: column;
            gap: 2vh;
            background: #000;
            padding: 2vh;
        }

        .teams-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2vh;
        }

        .team-row {
            flex: 1;
            background: linear-gradient(to right, #2a2a2a 0%, #1a1a1a 100%);
            border: 0.4vh solid #444;
            border-radius: 1.5vh;
            display: flex;
            align-items: center;
            padding: 0 3vw;
            box-shadow: 0 0.8vh 2vh rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .team-logo {
            width: 10vh;
            height: 10vh;
            border-radius: 1vh;
            margin-right: 2vw;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 0.3vh solid rgba(255, 255, 255, 0.1);
            font-size: 5vh;
        }

        .logo-team1 {
            background: linear-gradient(135deg, #2196f3 0%, #1565c0 100%);
        }

        .logo-team2 {
            background: linear-gradient(135deg, #ff3d00 0%, #d50000 100%);
        }

        .team-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5vh;
        }

        .team-name {
            color: #ffffff;
            font-size: 8vh;
            font-weight: 900;
            letter-spacing: 0.2vw;
            text-transform: uppercase;
            text-shadow: 0.3vh 0.3vh 0.5vh rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            gap: 2vw;
        }

        .team-name img.emoji {
            width: 0.95em;
            height: 0.95em;
            margin: 0 0.08em 0 0;
            vertical-align: -0.08em;
        }

        .player-names {
            color: #aaa;
            font-size: 2.5vh;
            font-weight: 600;
        }

        .shuttle-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            animation: bounce 0.6s ease-in-out infinite;
        }

        @keyframes bounce {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-1vh);
            }
        }

        .shuttle-icon {
            font-size: 8vh;
            filter: drop-shadow(0 0 1vh rgba(255, 255, 255, 0.8));
        }

        .cards-container {
            display: flex;
            gap: 1vw;
            align-items: center;
            margin-left: 2vw;
        }

        .card-badge {
            display: flex;
            align-items: center;
            gap: 0.5vw;
            padding: 0.5vh 1vw;
            border-radius: 0.8vh;
            font-size: 3vh;
            font-weight: 900;
            border: 0.3vh solid rgba(0, 0, 0, 0.3);
            box-shadow: 0 0.3vh 0.8vh rgba(0, 0, 0, 0.4);
        }

        .yellow-card-badge {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #000;
        }

        .red-card-badge {
            background: linear-gradient(135deg, #ff1744 0%, #d50000 100%);
            color: #fff;
            animation: pulse-red 1.5s ease-in-out infinite;
        }

        @keyframes pulse-red {
            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }

        .card-count {
            font-size: 3.5vh;
            min-width: 3vw;
            text-align: center;
        }

        .scores {
            display: flex;
            gap: 1.5vw;
            align-items: center;
        }

        .round-score {
            background: #790d0d;
            color: #fff;
            border-radius: 1vh;
            font-size: 18vh;
            font-weight: 900;
            min-width: 7vw;
            text-align: center;
            border: 0.3vh solid #333;
            box-shadow: inset 0 0.5vh 1vh rgba(0, 0, 0, 0.8);
            font-family: 'Impact', 'Arial Black', sans-serif;
        }

        .current-score {
            background: linear-gradient(135deg, #d50000 0%, #8b0000 100%);
            color: #fff;
            border-radius: 1vh;
            font-size: 15vh;
            font-weight: 900;
            min-width: 9vw;
            text-align: center;
            border: 0.4vh solid #ff1744;
            box-shadow: 0 0.5vh 2vh rgba(213, 0, 0, 0.6), inset 0 -0.3vh 1vh rgba(0, 0, 0, 0.3);
            font-family: 'Impact', 'Arial Black', sans-serif;
        }

        .wins-indicator {
            background: linear-gradient(135deg, #ffd700 0%, #ffa000 100%);
            color: #000;
            padding: 1vh 1vw;
            border-radius: 1vh;
            font-size: 6vh;
            font-weight: 900;
            min-width: 6vw;
            text-align: center;
            border: 0.3vh solid #ffeb3b;
            box-shadow: 0 0.5vh 1.5vh rgba(255, 215, 0, 0.4);
        }

        button {
            background: rgba(213, 0, 0, 0.6);
            border: 0.3vh solid #ff1744;
            color: #fff;
            padding: 1.5vh 1vw;
            font-size: 1.8vh;
            border-radius: 0.8vh;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
            font-family: 'Arial Black', Arial, sans-serif;
        }

        button:hover {
            background: rgba(213, 0, 0, 0.8);
            transform: scale(1.05);
        }

        button:active {
            transform: scale(0.95);
        }

        .subtext-container {
            font-size: 0.875rem;
            line-height: 1.25rem;
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
    <div class="court-tag">{{ __('Court') }} {{ $court }}</div>
    <div id="load-err"></div>

    <div id="idle" class="idle">{{ __('No current match') }}</div>

    <div id="board" class="board">
        <div class="board-inner" id="board-inner">
            <div class="scoreboard-container" id="scoreboard-container">
                <div class="footer">
                    <div class="footer-title">
                        <span id="banner-event" class="event-title"></span>
                    </div>
                    <div class="">
                        <img src="https://v1.badmintonscorer.online/img/sponser.jpeg" alt="Logo" class="footer-logo">
                    </div>
                </div>

                <div class="scoreboard">
                    <div class="teams-container">
                        <!-- Team 1 -->
                        <div class="team-row">
                            <div class="team-logo logo-team1">🏸</div>

                            <div class="team-info">
                                <div class="team-name">
                                    <span id="team1Name"></span>
                                </div>

                                <span id="shuttle1" class="shuttle-indicator"></span>
                                <span id="team1Cards" class="cards-container"></span>

                                <div class="subtext-container">
                                    <span id="team1Sub"></span>
                                </div>
                            </div>

                            <div class="scores" id="team1Scores">
                                <div class="wins-indicator" id="team1Wins">0</div>
                            </div>
                        </div>

                        <!-- Team 2 -->
                        <div class="team-row">
                            <div class="team-logo logo-team2">🏸</div>

                            <div class="team-info">
                                <div class="team-name">
                                    <span id="team2Name"></span>
                                </div>

                                <span id="shuttle2" class="shuttle-indicator"></span>
                                <span id="team2Cards" class="cards-container"></span>

                                <div class="subtext-container">
                                    <span id="team2Sub"></span>
                                </div>
                            </div>

                            <div class="scores" id="team2Scores">
                                <div class="wins-indicator" id="team2Wins">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <button id="fullscreenBtn" class="fullscreen-btn">⛶</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var POLL_MS = 500;
            var POLL_URL = {!! json_encode(route('api.live.court.score', ['court' => $court]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!};

            var idleEl = document.getElementById('idle');
            var boardEl = document.getElementById('board');
            var errEl = document.getElementById('load-err');

            var bannerEventEl = document.getElementById('banner-event');

            var team1NameEl = document.getElementById('team1Name');
            var team2NameEl = document.getElementById('team2Name');
            var team1SubEl = document.getElementById('team1Sub');
            var team2SubEl = document.getElementById('team2Sub');

            var team1ScoresEl = document.getElementById('team1Scores');
            var team2ScoresEl = document.getElementById('team2Scores');
            var team1WinsEl = document.getElementById('team1Wins');
            var team2WinsEl = document.getElementById('team2Wins');

            function clamp(n, min, max) {
                if (n < min) return min;
                if (n > max) return max;
                return n;
            }

            function buildSlots(m) {
                var best = m.best_of ? parseInt(m.best_of, 10) : 3;
                best = clamp(best, 1, 5);

                var games = m.games || [];
                var slots = [];

                for (var i = 1; i <= best; i++) {
                    var g = null;

                    for (var j = 0; j < games.length; j++) {
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

            function formatScore(n) {
                n = parseInt(n, 10);
                if (isNaN(n)) n = 0;
                if (n < 10) return '0' + n;
                return String(n);
            }

            function createScoreCell(className, value) {
                var el = document.createElement('div');
                el.className = className;
                // Match the original markup spacing (e.g. "&nbsp;09&nbsp;").
                el.textContent = '\u00A0' + formatScore(value) + '\u00A0';
                return el;
            }

            function clearSlotCells(scoresEl) {
                // Keep the first child (wins indicator) and remove the rest.
                while (scoresEl.children.length > 1) {
                    scoresEl.removeChild(scoresEl.lastElementChild);
                }
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
                team1NameEl.textContent = m.side_a_label || '—';
                team2NameEl.textContent = m.side_b_label || '—';

                var subText = m.stage_name || '';
                team1SubEl.textContent = subText;
                team2SubEl.textContent = subText;

                var slots = buildSlots(m);

                // "Yellow box" = number of games won by each side.
                var winsA = 0;
                var winsB = 0;
                for (var k = 0; k < slots.length; k++) {
                    if (slots[k].winner_side === 'a') winsA++;
                    if (slots[k].winner_side === 'b') winsB++;
                }
                team1WinsEl.textContent = String(winsA);
                team2WinsEl.textContent = String(winsB);

                clearSlotCells(team1ScoresEl);
                clearSlotCells(team2ScoresEl);

                // Active game is the first slot without a winner_side, unless the match is finished.
                var activeIdx = null;
                if (!m.winner_side) {
                    for (var i = 0; i < slots.length; i++) {
                        if (!slots[i].winner_side) {
                            activeIdx = i;
                            break;
                        }
                    }
                }

                for (var idx = 0; idx < slots.length; idx++) {
                    var slot = slots[idx];

                    if (slot.winner_side === 'a' || slot.winner_side === 'b') {
                        team1ScoresEl.appendChild(createScoreCell('round-score', slot.score_a));
                        team2ScoresEl.appendChild(createScoreCell('round-score', slot.score_b));
                        continue;
                    }

                    if (activeIdx !== null && idx === activeIdx) {
                        team1ScoresEl.appendChild(createScoreCell('current-score', slot.score_a));
                        team2ScoresEl.appendChild(createScoreCell('current-score', slot.score_b));
                    }
                }

                if (window.twemoji) {
                    window.twemoji.parse(boardEl, {
                        folder: 'svg',
                        ext: '.svg'
                    });
                }
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

            // Optional: fullscreen support for the live hall screen.
            var fullscreenBtn = document.getElementById('fullscreenBtn');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', function () {
                    var el = document.documentElement;
                    if (el.requestFullscreen) el.requestFullscreen();
                });
            }

            poll();
            setInterval(poll, POLL_MS);
        })();
    </script>
</body>

</html>
