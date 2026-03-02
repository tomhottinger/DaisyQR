<?php
/**
 * Admin: Neue Codes generieren
 */

requireLogin();

$base = BASE_PATH;
$user = getCurrentUser();
$generatedCodes = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = min(100, max(1, (int) ($_POST['count'] ?? 1)));

    for ($i = 0; $i < $count; $i++) {
        $id = generateUniqueCodeId();
        createCode($id, $user['id'] ?? null);
        $generatedCodes[] = $id;
    }
}

$currentPage = 'generate';
$pageTitle = 'Codes generieren - QR-Code Verwaltung';

ob_start();
?>

<div class="card">
    <h1>Neue Codes generieren</h1>

    <?php if (!empty($generatedCodes)): ?>
        <div class="alert success">
            ✓ <?= count($generatedCodes) ?> neue Codes erstellt!
        </div>

        <div class="generated-codes">
            <h3>Generierte Codes:</h3>
            <div class="code-grid">
                <?php foreach ($generatedCodes as $id): ?>
                <div class="code-card">
                    <div class="qr-code">
                        <img src="<?= $base ?>/api/qr.php?id=<?= $id ?>&size=150" alt="QR Code">
                    </div>
                    <div class="code-card-id"><?= $id ?></div>
                    <div style="margin-top: 8px">
                        <a href="<?= $base ?>/<?= $id ?>" class="btn small" target="_blank">Öffnen</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="actions" style="margin-top: 20px">
                <a href="<?= $base ?>/admin/print?codes=<?= implode(',', $generatedCodes) ?>" class="btn primary">Druckansicht</a>
                <a href="<?= $base ?>/admin/generate" class="btn">Weitere erstellen</a>
            </div>
        </div>
    <?php else: ?>
        <form method="post" class="program-form">
            <div class="form-group">
                <label for="count">Anzahl Codes</label>
                <input type="number" id="count" name="count" value="1" min="1" max="100">
                <small style="color: var(--gray-500)">Maximal 100 Codes auf einmal</small>
            </div>

            <button type="submit" class="btn primary">Codes generieren</button>
        </form>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/layout.php';
