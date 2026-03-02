<?php
/**
 * Admin: Benutzerverwaltung (nur für Admins)
 */

requireAdmin();

$base = BASE_PATH;
$currentUser = getCurrentUser();
$message = '';
$error = '';
$newUserData = null;
$minPasswordLength = 8;

// Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $makeAdmin = !empty($_POST['is_admin']);
        $password = (string)($_POST['password'] ?? '');
        if (empty($name)) {
            $error = 'Bitte einen Benutzernamen angeben.';
        } elseif (userNameExists($name)) {
            $error = "Benutzername '{$name}' existiert bereits.";
        } elseif (strlen($password) < $minPasswordLength) {
            $error = "Das Passwort muss mindestens {$minPasswordLength} Zeichen lang sein.";
        } else {
            $newUserData = createUser($name, $makeAdmin, $password);
            $message = "Benutzer '{$name}' erstellt.";
        }
    } elseif ($action === 'set_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');

        if ($userId <= 0) {
            $error = 'Ungültiger Benutzer.';
        } elseif (strlen($password) < $minPasswordLength) {
            $error = "Das Passwort muss mindestens {$minPasswordLength} Zeichen lang sein.";
        } elseif (setUserPassword($userId, $password)) {
            $message = 'Passwort wurde aktualisiert.';
        } else {
            $error = 'Passwort konnte nicht aktualisiert werden.';
        }
    }
}

// Alle Benutzer laden
$users = getAllUsers();

$currentPage = 'users';
$pageTitle = 'Benutzer - QR-Code Verwaltung';

ob_start();
?>

<div class="card">
    <h1>Benutzer verwalten</h1>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($newUserData): ?>
        <div class="card" style="background: var(--gray-50); margin-bottom: 20px">
            <h3>Neuer Benutzer: <?= htmlspecialchars($newUserData['name']) ?></h3>
            <div class="qr-code" style="margin: 16px 0">
                <img src="<?= $base ?>/api/qr.php?id=auth_<?= $newUserData['auth_token'] ?>&size=200" alt="Auth QR">
            </div>
            <p style="text-align: center">
                <a href="<?= $base ?>/auth/<?= htmlspecialchars($newUserData['auth_token']) ?>" class="btn primary" target="_blank">
                    Login-Link
                </a>
            </p>
            <p style="color: var(--warning); margin-top: 12px; font-size: 0.875rem; text-align: center">
                ⚠️ Diesen QR-Code dem Benutzer geben - er wird nur einmal angezeigt!
            </p>
        </div>
    <?php endif; ?>

    <form method="post" style="margin-bottom: 24px">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label for="name">Neuer Benutzer</label>
            <div style="display: flex; gap: 8px; align-items: center">
                <input type="text" id="name" name="name" placeholder="Name" required style="flex: 1">
                <input type="password" name="password" placeholder="Passwort (mind. <?= $minPasswordLength ?> Zeichen)" required style="flex: 1">
                <label style="display: flex; align-items: center; gap: 4px; white-space: nowrap">
                    <input type="checkbox" name="is_admin" value="1"> Admin
                </label>
                <button type="submit" class="btn primary">Erstellen</button>
            </div>
        </div>
    </form>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <p>Noch keine Benutzer vorhanden.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Rolle</th>
                        <th>Passwort</th>
                        <th>Auth-QR</th>
                        <th>Erstellt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td>
                            <?php if ($user['is_admin']): ?>
                                <span class="badge success">Admin</span>
                            <?php else: ?>
                                <span class="badge">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['password_hash'])): ?>
                                <span class="badge success">gesetzt</span>
                            <?php else: ?>
                                <span class="badge warning">fehlt</span>
                            <?php endif; ?>
                            <form method="post" style="margin-top: 8px; display: flex; gap: 6px; align-items: center;">
                                <input type="hidden" name="action" value="set_password">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="password" name="password" placeholder="Neues Passwort" required style="min-width: 160px">
                                <button type="submit" class="btn small">Setzen</button>
                            </form>
                        </td>
                        <td>
                            <a href="<?= $base ?>/auth/<?= $user['auth_token'] ?>" target="_blank" class="btn small">
                                Login-Link
                            </a>
                            <button type="button" class="btn small" onclick="showAuthQR('<?= $user['auth_token'] ?>')">
                                QR zeigen
                            </button>
                        </td>
                        <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal für QR-Code -->
<div id="qrModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="max-width: 300px; text-align: center;">
        <h3>Auth QR-Code</h3>
        <img id="qrImage" src="" alt="QR Code" style="max-width: 100%">
        <p style="margin-top: 12px">Scanne diesen Code zum Einloggen</p>
        <button class="btn" onclick="document.getElementById('qrModal').style.display='none'">Schliessen</button>
    </div>
</div>

<script>
function showAuthQR(token) {
    document.getElementById('qrImage').src = '<?= $base ?>/api/qr.php?id=auth_' + token + '&size=250';
    document.getElementById('qrModal').style.display = 'flex';
}

document.getElementById('qrModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/layout.php';
