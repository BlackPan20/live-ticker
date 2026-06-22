<?php
session_start();

$dataFile = __DIR__ . '/data/live-ticker.json';

if (!isset($_SESSION['user'])) {
    header('Location: login.php?return=manage.php');
    exit;
}

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0775, true);
}

$defaults = [
    'currentGameId' => 'game-1',
    'teams' => [],
    'standings' => [],
    'games' => [],
    'lastGames' => [],
];

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$data = json_decode(file_get_contents($dataFile), true) ?: $defaults;
$errors = [];
$notice = null;

function normalize_id(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
    return trim($slug, '-') ?: 'team-' . bin2hex(random_bytes(3));
}

function lower_text(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function team_label(array $teams, string $teamId): string
{
    foreach ($teams as $team) {
        if (($team['id'] ?? '') === $teamId) {
            return $team['name'] ?? $teamId;
        }
    }

    return $teamId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_team') {
        $teamName = trim($_POST['team_name'] ?? '');
        $logoUrl = trim($_POST['logo_url'] ?? '');

        if ($teamName === '') {
            $errors[] = 'Bitte einen Teamnamen eingeben.';
        } else {
            $teamId = normalize_id($teamName);
            foreach ($data['teams'] as $team) {
                if (($team['id'] ?? '') === $teamId || lower_text((string) ($team['name'] ?? '')) === lower_text($teamName)) {
                    $errors[] = 'Dieses Team existiert bereits.';
                    break;
                }
            }

            if (!$errors) {
                $data['teams'][] = [
                    'id' => $teamId,
                    'name' => $teamName,
                    'logoUrl' => $logoUrl,
                ];
                $data['standings'][] = [
                    'teamId' => $teamId,
                    'points' => 0,
                    'played' => 0,
                    'goalDiff' => 0,
                ];
                $notice = 'Team wurde gespeichert.';
            }
        }
    }

    if ($action === 'add_game') {
        $homeTeamId = $_POST['home_team_id'] ?? '';
        $awayTeamId = $_POST['away_team_id'] ?? '';
        $minute = (int) ($_POST['minute'] ?? 0);
        $status = trim($_POST['status'] ?? 'live');
        $publicScore = trim($_POST['public_score'] ?? '0 : 0');
        $actualScore = trim($_POST['actual_score'] ?? '0 : 0');

        if ($homeTeamId === '' || $awayTeamId === '' || $homeTeamId === $awayTeamId) {
            $errors[] = 'Bitte zwei unterschiedliche Teams wählen.';
        }

        if (!$errors) {
            $homeName = team_label($data['teams'], $homeTeamId);
            $awayName = team_label($data['teams'], $awayTeamId);
            $gameId = normalize_id($homeName . '-' . $awayName . '-' . random_int(100, 999));

            $data['games'][] = [
                'id' => $gameId,
                'homeTeamId' => $homeTeamId,
                'awayTeamId' => $awayTeamId,
                'status' => $status,
                'minute' => $minute,
                'publicScore' => $publicScore,
                'actualScore' => $actualScore,
                'title' => $homeName . ' gegen ' . $awayName,
            ];
            $notice = 'Spiel wurde angelegt.';
        }
    }

    if ($action === 'set_current_game') {
        $currentGameId = $_POST['current_game_id'] ?? '';
        if ($currentGameId !== '') {
            $data['currentGameId'] = $currentGameId;
            $notice = 'Live-Spiel wurde gesetzt.';
        }
    }

    if ($action === 'update_game') {
        $gameId = $_POST['game_id'] ?? '';
        foreach ($data['games'] as &$game) {
            if (($game['id'] ?? '') === $gameId) {
                $game['publicScore'] = trim($_POST['public_score'] ?? ($game['publicScore'] ?? '0 : 0'));
                $game['actualScore'] = trim($_POST['actual_score'] ?? ($game['actualScore'] ?? '0 : 0'));
                $game['minute'] = (int) ($_POST['minute'] ?? ($game['minute'] ?? 0));
                $game['status'] = trim($_POST['status'] ?? ($game['status'] ?? 'live'));
                $notice = 'Spielstand wurde aktualisiert.';
                break;
            }
        }
        unset($game);
    }

    if ($action === 'save_all') {
        $standingsInput = $_POST['standings'] ?? [];
        foreach ($standingsInput as $entryIndex => $entry) {
            if (!isset($data['standings'][$entryIndex])) {
                continue;
            }
            $data['standings'][$entryIndex]['points'] = (int) ($entry['points'] ?? 0);
            $data['standings'][$entryIndex]['played'] = (int) ($entry['played'] ?? 0);
            $data['standings'][$entryIndex]['goalDiff'] = (int) ($entry['goalDiff'] ?? 0);
        }
        $notice = 'Tabelle wurde gespeichert.';
    }

    if (!$errors) {
        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

$teamsById = [];
foreach ($data['teams'] as $team) {
    $teamsById[$team['id'] ?? ''] = $team;
}

$games = $data['games'];
$currentGame = null;
foreach ($games as $game) {
    if (($game['id'] ?? '') === ($data['currentGameId'] ?? '')) {
        $currentGame = $game;
        break;
    }
}

if ($currentGame === null && $games) {
    $currentGame = $games[0];
}

function safe_text($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verwaltung | Live Ticker</title>
    <style>
        :root {
            --bg-0: #f5f7fb;
            --bg-1: #e7edf5;
            --surface: rgba(255, 255, 255, 0.92);
            --surface-soft: rgba(15, 23, 42, 0.05);
            --border: rgba(15, 23, 42, 0.1);
            --text: #16202f;
            --muted: #607085;
            --accent: #2563eb;
            --accent-2: #0ea5e9;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #b91c1c;
            --shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 28%),
                linear-gradient(180deg, var(--bg-0), var(--bg-1));
        }

        .page {
            width: min(1280px, calc(100% - 24px));
            margin: 0 auto;
            padding: 24px 0 36px;
        }

        .topbar,
        .card {
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            margin-bottom: 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mark {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
        }

        .title h1,
        .title p,
        h2,
        h3,
        p { margin: 0; }

        .title p {
            color: var(--muted);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav a,
        .button,
        .secondary,
        .ghost,
        select,
        input,
        textarea,
        button {
            font: inherit;
        }

        .nav a,
        .button,
        .secondary,
        .ghost,
        .mini {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 14px;
            text-decoration: none;
            border: 1px solid var(--border);
            background: var(--surface-soft);
            color: var(--text);
        }

        .button {
            border: 0;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            cursor: pointer;
        }

        .secondary {
            background: #fff;
            cursor: pointer;
        }

        .grid {
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            gap: 16px;
        }

        .stack {
            display: grid;
            gap: 16px;
        }

        .card {
            padding: 20px;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.08);
            color: var(--accent);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .text-muted { color: var(--muted); }
        .success { color: var(--success); }
        .warning { color: var(--warning); }
        .danger { color: var(--danger); }

        .hero-copy {
            max-width: 60ch;
            color: var(--muted);
            line-height: 1.65;
            margin-top: 10px;
        }

        .live-card {
            margin-top: 16px;
            padding: 18px;
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.05), rgba(255, 255, 255, 0.02));
            border: 1px solid rgba(37, 99, 235, 0.08);
        }

        .match {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding-top: 14px;
        }

        .team-head {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            flex: 0 0 auto;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .score {
            min-width: 110px;
            padding: 14px 16px;
            border-radius: 18px;
            background: var(--surface-soft);
            text-align: center;
            font-weight: 900;
            font-size: clamp(1.8rem, 4vw, 2.9rem);
            line-height: 1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-height: 46px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
        }

        .field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: rgba(37, 99, 235, 0.55);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .notice,
        .alert {
            padding: 12px 14px;
            border-radius: 14px;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .notice { background: rgba(22, 163, 74, 0.08); color: var(--success); }
        .alert { background: rgba(185, 28, 28, 0.08); color: var(--danger); }

        .list {
            display: grid;
            gap: 10px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            background: var(--surface-soft);
        }

        .item strong { display: block; }
        .item small { color: var(--muted); }

        .table {
            display: grid;
            gap: 8px;
        }

        .row {
            display: grid;
            grid-template-columns: 1.5fr repeat(3, minmax(60px, 0.35fr));
            gap: 10px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--surface-soft);
        }

        .row.head {
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.8rem;
        }

        .mini-stack {
            display: grid;
            gap: 10px;
        }

        .mini-card {
            padding: 14px;
            border-radius: 16px;
            background: var(--surface-soft);
        }

        .mini-card strong {
            display: block;
            margin-bottom: 4px;
        }

        @media (max-width: 960px) {
            .grid,
            .form-grid {
                grid-template-columns: 1fr;
            }

            .row {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 720px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .match,
            .item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">
                <div class="mark">LT</div>
                <div class="title">
                    <h1>Verwaltung</h1>
                    <p>Teams, Spiele, Live-Auswahl und JSON-Speicherung</p>
                </div>
            </div>
            <nav class="nav">
                <a href="index.html">Zur Startseite</a>
                <a href="login.php">Login</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <?php if ($notice): ?>
            <div class="notice"><?php echo safe_text($notice); ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert"><?php echo safe_text(implode(' ', $errors)); ?></div>
        <?php endif; ?>

        <section class="grid">
            <article class="card">
                <div class="section-head">
                    <div>
                        <div class="badge">Aktuelles Live-Spiel</div>
                        <h2 style="margin-top: 8px;">Welches Spiel läuft im Ticker?</h2>
                    </div>
                    <div class="text-muted">Gespeichert in JSON</div>
                </div>

                <div class="live-card">
                    <?php if ($currentGame): ?>
                        <div class="match">
                            <div>
                                <div class="team-head">
                                    <div class="logo">
                                        <?php $homeTeam = $teamsById[$currentGame['homeTeamId']] ?? []; ?>
                                        <?php if (!empty($homeTeam['logoUrl'])): ?>
                                            <img src="<?php echo safe_text($homeTeam['logoUrl']); ?>" alt="Logo <?php echo safe_text($homeTeam['name'] ?? ''); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo safe_text($teamsById[$currentGame['homeTeamId']]['name'] ?? 'Unbekannt'); ?></strong>
                                        <div class="text-muted">Heimteam</div>
                                    </div>
                                </div>
                            </div>
                            <div class="score"><?php echo safe_text($currentGame['publicScore'] ?? '0 : 0'); ?></div>
                            <div>
                                <div class="team-head">
                                    <div class="logo">
                                        <?php $awayTeam = $teamsById[$currentGame['awayTeamId']] ?? []; ?>
                                        <?php if (!empty($awayTeam['logoUrl'])): ?>
                                            <img src="<?php echo safe_text($awayTeam['logoUrl']); ?>" alt="Logo <?php echo safe_text($awayTeam['name'] ?? ''); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo safe_text($teamsById[$currentGame['awayTeamId']]['name'] ?? 'Unbekannt'); ?></strong>
                                        <div class="text-muted"><?php echo safe_text(($currentGame['status'] ?? 'live') === 'live' ? 'läuft' : 'beendet'); ?> · Minute <?php echo safe_text($currentGame['minute'] ?? 0); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Noch kein Live-Spiel angelegt.</p>
                    <?php endif; ?>
                </div>

                <form method="post" action="manage.php" style="margin-top: 18px;">
                    <input type="hidden" name="action" value="set_current_game">
                    <div class="field">
                        <label for="current_game_id">Live-Spiel auswählen</label>
                        <select id="current_game_id" name="current_game_id">
                            <?php foreach ($data['games'] as $game): ?>
                                <option value="<?php echo safe_text($game['id'] ?? ''); ?>" <?php echo (($data['currentGameId'] ?? '') === ($game['id'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo safe_text($game['title'] ?? ($game['id'] ?? 'Spiel')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="button" type="submit">Als Live-Spiel speichern</button>
                    </div>
                </form>
            </article>

            <aside class="stack">
                <article class="card">
                    <div class="section-head">
                        <div>
                            <div class="badge">Team anlegen</div>
                            <h3 style="margin-top: 8px;">Neues Team mit Logo</h3>
                        </div>
                    </div>

                    <form method="post" action="manage.php">
                        <input type="hidden" name="action" value="add_team">
                        <div class="form-grid">
                            <div class="field">
                                <label for="team_name">Teamname</label>
                                <input id="team_name" name="team_name" type="text" placeholder="z. B. Team Aurora" required>
                            </div>
                            <div class="field">
                                <label for="logo_url">Logo-URL</label>
                                <input id="logo_url" name="logo_url" type="text" placeholder="https://...">
                            </div>
                        </div>
                        <div class="actions">
                            <button class="button" type="submit">Team speichern</button>
                        </div>
                    </form>
                </article>

                <article class="card">
                    <div class="section-head">
                        <div>
                            <div class="badge">Spiel anlegen</div>
                            <h3 style="margin-top: 8px;">Laufendes Spiel erstellen</h3>
                        </div>
                    </div>

                    <form method="post" action="manage.php">
                        <input type="hidden" name="action" value="add_game">
                        <div class="form-grid">
                            <div class="field">
                                <label for="home_team_id">Heimteam</label>
                                <select id="home_team_id" name="home_team_id" required>
                                    <option value="">Bitte wählen</option>
                                    <?php foreach ($data['teams'] as $team): ?>
                                        <option value="<?php echo safe_text($team['id'] ?? ''); ?>"><?php echo safe_text($team['name'] ?? ''); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="away_team_id">Auswärtsteam</label>
                                <select id="away_team_id" name="away_team_id" required>
                                    <option value="">Bitte wählen</option>
                                    <?php foreach ($data['teams'] as $team): ?>
                                        <option value="<?php echo safe_text($team['id'] ?? ''); ?>"><?php echo safe_text($team['name'] ?? ''); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="public_score">Angezeigter Stand</label>
                                <input id="public_score" name="public_score" type="text" value="0 : 0">
                            </div>
                            <div class="field">
                                <label for="actual_score">Echte Punkte</label>
                                <input id="actual_score" name="actual_score" type="text" value="0 : 0">
                            </div>
                            <div class="field">
                                <label for="minute">Minute</label>
                                <input id="minute" name="minute" type="number" min="0" max="130" value="0">
                            </div>
                            <div class="field">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="live">live</option>
                                    <option value="fertig">fertig</option>
                                    <option value="pause">pause</option>
                                </select>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="button" type="submit">Spiel speichern</button>
                        </div>
                    </form>
                </article>
            </aside>
        </section>

        <section class="manage-shell">
            <div class="grid">
                <article class="card">
                    <div class="section-head">
                        <div>
                            <div class="badge">Tabelle pflegen</div>
                            <h3 style="margin-top: 8px;">Punkte und Spiele direkt ändern</h3>
                        </div>
                    </div>

                    <form method="post" action="manage.php">
                        <input type="hidden" name="action" value="save_all">
                        <div class="table">
                            <div class="row head">
                                <div>Mannschaft</div>
                                <div>Punkte</div>
                                <div>Spiele</div>
                                <div>Diff.</div>
                            </div>
                            <?php foreach ($data['standings'] as $index => $entry): ?>
                                <?php $teamName = team_label($data['teams'], $entry['teamId'] ?? ''); ?>
                                <div class="row">
                                    <div><strong><?php echo safe_text($teamName); ?></strong></div>
                                    <div><input type="number" name="standings[<?php echo $index; ?>][points]" value="<?php echo safe_text($entry['points'] ?? 0); ?>"></div>
                                    <div><input type="number" name="standings[<?php echo $index; ?>][played]" value="<?php echo safe_text($entry['played'] ?? 0); ?>"></div>
                                    <div><input type="number" name="standings[<?php echo $index; ?>][goalDiff]" value="<?php echo safe_text($entry['goalDiff'] ?? 0); ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="actions">
                            <button class="button" type="submit">Tabelle speichern</button>
                        </div>
                    </form>
                    <p class="hint">Diese Werte erscheinen danach sofort auf der Startseite aus der JSON-Datei.</p>
                </article>

                <article class="card">
                    <div class="section-head">
                        <div>
                            <div class="badge">Aktuelle Spiele</div>
                            <h3 style="margin-top: 8px;">Stand eines laufenden Spiels ändern</h3>
                        </div>
                    </div>

                    <div class="list">
                        <?php foreach ($data['games'] as $game): ?>
                            <div class="item">
                                <div>
                                    <strong><?php echo safe_text($game['title'] ?? 'Spiel'); ?></strong>
                                    <small><?php echo safe_text(($game['status'] ?? 'live')); ?> · Minute <?php echo safe_text($game['minute'] ?? 0); ?></small>
                                </div>
                                <div>
                                    <span class="game-score"><?php echo safe_text($game['publicScore'] ?? '0 : 0'); ?></span>
                                </div>
                            </div>
                            <form method="post" action="manage.php" style="margin-bottom: 12px;">
                                <input type="hidden" name="action" value="update_game">
                                <input type="hidden" name="game_id" value="<?php echo safe_text($game['id'] ?? ''); ?>">
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Anzeige</label>
                                        <input name="public_score" type="text" value="<?php echo safe_text($game['publicScore'] ?? '0 : 0'); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Echte Punkte</label>
                                        <input name="actual_score" type="text" value="<?php echo safe_text($game['actualScore'] ?? '0 : 0'); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Minute</label>
                                        <input name="minute" type="number" min="0" max="130" value="<?php echo safe_text($game['minute'] ?? 0); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Status</label>
                                        <select name="status">
                                            <option value="live" <?php echo (($game['status'] ?? '') === 'live') ? 'selected' : ''; ?>>live</option>
                                            <option value="fertig" <?php echo (($game['status'] ?? '') === 'fertig') ? 'selected' : ''; ?>>fertig</option>
                                            <option value="pause" <?php echo (($game['status'] ?? '') === 'pause') ? 'selected' : ''; ?>>pause</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="actions">
                                    <button class="secondary" type="submit">Spielstand speichern</button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>

            <div class="grid">
                <article class="card">
                    <div class="section-head">
                        <div>
                            <div class="badge">Teams</div>
                            <h3 style="margin-top: 8px;">Alle Teams mit Logo</h3>
                        </div>
                    </div>
                    <div class="mini-stack">
                        <?php foreach ($data['teams'] as $team): ?>
                            <div class="mini-card">
                                <strong><?php echo safe_text($team['name'] ?? ''); ?></strong>
                                <div class="text-muted small"><?php echo safe_text($team['logoUrl'] ?: 'Kein Logo gesetzt'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="card">
                    <div class="section-head">
                        <div>
                            <div class="badge">Letzte Spiele</div>
                            <h3 style="margin-top: 8px;">Historie für die Startseite</h3>
                        </div>
                    </div>
                    <div class="mini-stack">
                        <?php foreach ($data['lastGames'] as $game): ?>
                            <div class="mini-card">
                                <strong><?php echo safe_text($game['title'] ?? ''); ?></strong>
                                <div class="text-muted small"><?php echo safe_text($game['date'] ?? ''); ?> · <?php echo safe_text($game['score'] ?? ''); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>
    </div>
</body>
</html>
