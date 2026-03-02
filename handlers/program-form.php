<?php
/**
 * Programmier-Formular für QR-Codes
 */

$base = BASE_PATH;
$codeId = $codeId ?? $routeParams[0] ?? '';
$code = $code ?? getCode($codeId);
$shareUrl = $shareUrl ?? $_GET['url'] ?? '';
$shareTitle = $shareTitle ?? $_GET['title'] ?? '';
$isNew = $isNew ?? false;
$isProgrammed = !empty($code['target_url']);
$error = '';
$success = '';

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $confirm = $_POST['confirm'] ?? false;

    // Validierung
    if (empty($url)) {
        $error = 'Bitte gib eine URL ein.';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        // Versuche mit https:// zu ergänzen
        if (filter_var('https://' . $url, FILTER_VALIDATE_URL)) {
            $url = 'https://' . $url;
        } else {
            $error = 'Bitte gib eine gültige URL ein.';
        }
    }

    // Wenn bereits programmiert, Bestätigung erforderlich
    if (!$error && $isProgrammed && !$confirm) {
        $error = 'confirm_required';
        $shareUrl = $url;
        $shareTitle = $title;
    }

    // Speichern
    if (!$error) {
        $userId = getCurrentUser()['id'] ?? null;
        if (programCode($codeId, $url, $title ?: null, $userId)) {
            $success = 'QR-Code wurde programmiert!';
            $code = getCode($codeId);
            $isProgrammed = true;
        } else {
            $error = 'Fehler beim Speichern.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-Code programmieren</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📱 QR-Code programmieren</h1>

            <div class="code-info">
                <span class="code-id"><?= htmlspecialchars($codeId) ?></span>
                <?php if ($isProgrammed && empty($success)): ?>
                    <span class="badge warning">Bereits programmiert</span>
                <?php elseif ($isNew): ?>
                    <span class="badge info">Neuer Code</span>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert success">
                    ✓ <?= htmlspecialchars($success) ?>
                </div>
                <div class="current-target">
                    <strong>Ziel-URL:</strong><br>
                    <a href="<?= htmlspecialchars($code['target_url']) ?>" target="_blank">
                        <?= htmlspecialchars($code['target_url']) ?>
                    </a>
                </div>
                <div class="actions">
                    <a href="<?= htmlspecialchars($code['target_url']) ?>" class="btn primary">Jetzt öffnen</a>
                    <a href="<?= $base ?>/<?= htmlspecialchars($codeId) ?>?edit=1" class="btn">Erneut programmieren</a>
                </div>
            <?php else: ?>

                <?php if ($isProgrammed): ?>
                    <div class="current-target">
                        <strong>Aktuelle Ziel-URL:</strong><br>
                        <a href="<?= htmlspecialchars($code['target_url']) ?>" target="_blank">
                            <?= htmlspecialchars($code['target_url']) ?>
                        </a>
                        <?php if ($code['title']): ?>
                            <br><small><?= htmlspecialchars($code['title']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error && $error !== 'confirm_required'): ?>
                    <div class="alert error">
                        ⚠ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="program-form">
                    <div class="form-group">
                        <label for="url">Ziel-URL</label>
                        <input
                            type="url"
                            id="url"
                            name="url"
                            placeholder="https://example.com"
                            value="<?= htmlspecialchars($shareUrl ?: ($isProgrammed ? $code['target_url'] : '')) ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="title">Titel (optional)</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            placeholder="Mein Link"
                            value="<?= htmlspecialchars($shareTitle ?: ($code['title'] ?? '')) ?>"
                        >
                    </div>

                    <?php if ($error === 'confirm_required'): ?>
                        <div class="alert warning">
                            ⚠ Dieser Code ist bereits programmiert. Möchtest du ihn überschreiben?
                        </div>
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit" class="btn warning">Überschreiben</button>
                        <a href="<?= htmlspecialchars($code['target_url']) ?>" class="btn">Abbrechen</a>
                    <?php else: ?>
                        <button type="submit" class="btn primary">
                            <?= $isProgrammed ? 'Aktualisieren' : 'Programmieren' ?>
                        </button>
                    <?php endif; ?>
                </form>

            <?php endif; ?>

            <div class="footer-links">
                <a href="<?= $base ?>/admin/">Zur Verwaltung</a>
            </div>
        </div>
    </div>
</body>
</html>
