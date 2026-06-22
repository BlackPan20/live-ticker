<?php
session_start();

$demoUser = 'admin';
$demoPasswordHash = password_hash('change-me-now', PASSWORD_DEFAULT);
$returnTarget = $_GET['return'] ?? ($_POST['return'] ?? 'manage.php');

if (!in_array($returnTarget, ['manage.php', 'index.html'], true)) {
    $returnTarget = 'manage.php';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte laden Sie die Seite neu.';
    }

    if ($username === '' || $password === '') {
        $errors[] = 'Bitte Benutzername und Passwort eingeben.';
    }

    if (!$errors && $username === $demoUser && password_verify($password, $demoPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $username;
        header('Location: ' . $returnTarget);
        exit;
    } elseif (!$errors) {
        $errors[] = 'Login fehlgeschlagen. Bitte prüfen Sie Ihre Zugangsdaten.';
    }
}

$loggedInUser = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Live Ticker</title>
    <style>
        :root {
            --bg-0: #f4f7fb;
            --bg-1: #e8eef6;
            --surface: rgba(255, 255, 255, 0.92);
            --border: rgba(15, 23, 42, 0.1);
            --text: #182130;
            --muted: #5d6b7e;
            --accent: #2563eb;
            --accent-2: #0ea5e9;
            --success: #16a34a;
            --danger: #b91c1c;
            --shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 28%),
                linear-gradient(180deg, var(--bg-0), var(--bg-1));
        }

        .page {
            width: min(960px, calc(100% - 24px));
            margin: 0 auto;
            padding: 28px 0 36px;
        }

        .card {
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            padding: 24px;
            backdrop-filter: blur(18px);
        }

        .topline {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.08);
            color: var(--accent);
            font-weight: 600;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        h1 {
            margin-top: 8px;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: -0.05em;
        }

        .subtext {
            margin-top: 10px;
            color: var(--muted);
            line-height: 1.65;
            max-width: 60ch;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 16px;
            margin-top: 16px;
        }

        .panel {
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.92);
            border-radius: var(--radius-lg);
            padding: 20px;
        }

        .status {
            display: grid;
            gap: 8px;
        }

        .status strong {
            font-size: 1.05rem;
        }

        .status p {
            color: var(--muted);
            line-height: 1.55;
        }

        form {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        label {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--muted);
        }

        input {
            width: 100%;
            min-height: 48px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: #fff;
            padding: 0 14px;
            font: inherit;
            color: var(--text);
        }

        input:focus {
            outline: none;
            border-color: rgba(37, 99, 235, 0.55);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 6px;
        }

        .button,
        .secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 16px;
            border-radius: 14px;
            border: 1px solid transparent;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .button {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
        }

        .secondary {
            color: var(--text);
            background: rgba(15, 23, 42, 0.04);
            border-color: var(--border);
        }

        .message {
            padding: 12px 14px;
            border-radius: 14px;
            margin-top: 14px;
            line-height: 1.5;
        }

        .message.error {
            background: rgba(185, 28, 28, 0.08);
            color: var(--danger);
            border: 1px solid rgba(185, 28, 28, 0.18);
        }

        .message.success {
            background: rgba(22, 163, 74, 0.08);
            color: var(--success);
            border: 1px solid rgba(22, 163, 74, 0.18);
        }

        .note {
            margin-top: 12px;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .mini-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .mini-item {
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.04);
        }

        .mini-item strong {
            display: block;
            margin-bottom: 4px;
        }

        @media (max-width: 760px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .card,
            .panel {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <div class="topline">
                <div>
                    <div class="badge">Sicherer Login-Bereich</div>
                    <h1>Login für das Live-Ticker System</h1>
                    <p class="subtext">
                        Hier melden sich berechtigte Personen an. Die Seite nutzt PHP, Sessions und eine einfache CSRF-Prüfung als Platzhalter für einen sicheren Backend-Login.
                    </p>
                </div>
                <a class="secondary" href="index.html">Zurück zum Ticker</a>
            </div>

            <div class="grid">
                <div class="panel">
                    <h2>Anmelden</h2>
                    <p class="note">Demo-Zugang für die Entwicklung: Benutzer <strong>admin</strong>, Passwort <strong>change-me-now</strong>.</p>

                    <?php if ($errors): ?>
                        <div class="message error">
                            <?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$loggedInUser): ?>
                        <form method="post" action="login.php" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnTarget, ENT_QUOTES, 'UTF-8'); ?>">

                            <div>
                                <label for="username">Benutzername</label>
                                <input id="username" name="username" type="text" placeholder="Benutzername eingeben" required>
                            </div>

                            <div>
                                <label for="password">Passwort</label>
                                <input id="password" name="password" type="password" placeholder="Passwort eingeben" required>
                            </div>

                            <div class="actions">
                                <button class="button" type="submit">Anmelden</button>
                                <a class="secondary" href="index.html#ergebnisse">Zum Ticker</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="message success">Sie sind als <?php echo htmlspecialchars($loggedInUser, ENT_QUOTES, 'UTF-8'); ?> angemeldet.</div>
                        <div class="actions">
                            <a class="button" href="index.html">Zum Dashboard</a>
                            <a class="secondary" href="manage.php">Zur Verwaltung</a>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="panel">
                    <h2>Backend-Hinweis</h2>
                    <div class="mini-list">
                        <div class="mini-item">
                            <strong>Session-Schutz</strong>
                            Nach erfolgreichem Login wird die Session erneuert, damit der Einstieg etwas sicherer ist.
                        </div>
                        <div class="mini-item">
                            <strong>Passwörter speichern</strong>
                            Ersetzen Sie das Demo-Passwort später durch einen echten Hash aus einer Datenbank.
                        </div>
                        <div class="mini-item">
                            <strong>Bitte im echten Einsatz</strong>
                            Die Zugangsdaten nicht im Code lassen, sondern in einer Datenbank oder in Server-Umgebungswerten speichern.
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</body>
</html>
