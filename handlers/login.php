<?php
/**
 * Login-Seite
 */

$base = BASE_PATH;
$user = getCurrentUser();
$error = '';
$next = $_REQUEST['next'] ?? ($base . '/admin/codes');
if (!is_string($next) || !preg_match('#^(?:/|\./)(?!/)[^\r\n]*$#', $next)) {
    $next = $base . '/admin/codes';
}

// Bereits eingeloggt?
if ($user) {
    header('Location: ' . ($base . '/admin/codes'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $password === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        $loginUser = authenticateUserByPassword($name, $password);
        if ($loginUser) {
            $sessionToken = createSession((int)$loginUser['id']);
            setcookie('session_token', $sessionToken, [
                'expires' => time() + SESSION_LIFETIME,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // Bootstrap-Passwort nach erstem erfolgreichen Admin-Login entfernen
            if (!empty($loginUser['is_admin'])) {
                $bootstrapPasswordFile = __DIR__ . '/../data/first_admin_password.txt';
                if (file_exists($bootstrapPasswordFile)) {
                    unlink($bootstrapPasswordFile);
                }
            }

            header('Location: ' . $next);
            exit;
        }
        $error = 'Login fehlgeschlagen. Benutzername oder Passwort ist falsch.';
    }
}

// Prüfe ob erster Admin-Token existiert (für Ersteinrichtung)
$firstAdminTokenFile = __DIR__ . '/../data/first_admin_token.txt';
$firstAdminToken = null;
if (file_exists($firstAdminTokenFile)) {
    $firstAdminToken = trim(file_get_contents($firstAdminTokenFile));
}

// Prüfe ob initiales Admin-Passwort existiert (Notfallzugriff)
$firstAdminPasswordFile = __DIR__ . '/../data/first_admin_password.txt';
$firstAdminPassword = null;
$firstAdminName = 'Admin';
if (file_exists($firstAdminPasswordFile)) {
    $firstAdminPassword = trim(file_get_contents($firstAdminPasswordFile));
    $result = getDB()->query("SELECT name FROM users WHERE is_admin = 1 ORDER BY id ASC LIMIT 1");
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
    if (!empty($row['name'])) {
        $firstAdminName = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QR-Code Verwaltung</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🔐 Login</h1>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div style="margin-bottom: 24px">
                <h2>Mit Passwort einloggen</h2>
                <form method="post">
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
                    <div class="form-group">
                        <label for="name">Benutzername</label>
                        <input type="text" id="name" name="name" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn primary">Mit Passwort einloggen</button>
                </form>
            </div>

            <div style="margin-bottom: 24px">
                <h2>Mit QR-Code einloggen</h2>
                <p>Scanne deinen persönlichen Auth-QR-Code um dich einzuloggen.</p>
                <p style="color: var(--gray-500); margin-top: 8px">
                    Hast du noch keinen Account? Bitte wende dich an einen Administrator.
                </p>
            </div>

            <?php if ($firstAdminPassword): ?>
                <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--gray-200)">
                <div class="alert warning">
                    <strong>Admin-Notfallzugang:</strong> Benutzername <code><?= htmlspecialchars($firstAdminName) ?></code>, Passwort <code><?= htmlspecialchars($firstAdminPassword) ?></code>
                </div>
                <p style="color: var(--warning); margin-top: 12px; font-size: 0.875rem">
                    ⚠️ Dieses Initialpasswort nach dem ersten Login sofort in der Benutzerverwaltung ändern.
                </p>
            <?php endif; ?>

            <?php if ($firstAdminToken): ?>
                <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--gray-200)">

                <div class="alert warning">
                    <strong>Ersteinrichtung:</strong> Scanne diesen QR-Code um dich als Admin einzuloggen.
                </div>

                <div class="qr-code" style="margin: 20px 0">
                    <img src="<?= $base ?>/api/qr.php?id=auth_<?= $firstAdminToken ?>&size=200" alt="Admin QR Code">
                </div>

                <p style="text-align: center">
                    <a href="<?= $base ?>/auth/<?= htmlspecialchars($firstAdminToken) ?>" class="btn primary">
                        Als Admin einloggen
                    </a>
                </p>

                <p style="color: var(--warning); margin-top: 12px; font-size: 0.875rem">
                    ⚠️ Nach dem ersten Login wird dieser QR-Code nicht mehr angezeigt.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
