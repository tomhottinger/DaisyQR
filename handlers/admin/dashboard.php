<?php
/**
 * Admin Dashboard
 */

requireLogin();

$base = BASE_PATH;
$user = getCurrentUser();
$codes = getUserCodes($user['id'] ?? null);

$totalCodes = count($codes);
$programmedCodes = count(array_filter($codes, fn($c) => !empty($c['target_url'])));
$totalScans = array_sum(array_column($codes, 'scan_count'));

$currentPage = 'dashboard';
$pageTitle = 'Dashboard - QR-Code Verwaltung';

ob_start();
?>

<div class="card">
    <h1>Dashboard</h1>

    <div class="stats">
        <div class="stat">
            <div class="stat-value"><?= $totalCodes ?></div>
            <div class="stat-label">Codes gesamt</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= $programmedCodes ?></div>
            <div class="stat-label">Programmiert</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= $totalScans ?></div>
            <div class="stat-label">Scans gesamt</div>
        </div>
    </div>

    <div class="actions">
        <a href="<?= $base ?>/admin/generate" class="btn primary">Neue Codes erstellen</a>
        <a href="<?= $base ?>/admin/codes" class="btn">Alle Codes anzeigen</a>
    </div>
</div>

<?php if (!empty($codes)): ?>
<div class="card">
    <h2>Letzte Aktivität</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Titel / URL</th>
                    <th>Scans</th>
                    <th>Aktualisiert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($codes, 0, 10) as $code): ?>
                <tr>
                    <td>
                        <a href="<?= $base ?>/<?= htmlspecialchars($code['id']) ?>" target="_blank">
                            <code><?= htmlspecialchars($code['id']) ?></code>
                        </a>
                    </td>
                    <td class="url-cell">
                        <?php if ($code['target_url']): ?>
                            <?= htmlspecialchars($code['title'] ?: $code['target_url']) ?>
                        <?php else: ?>
                            <em style="color: var(--gray-500)">Nicht programmiert</em>
                        <?php endif; ?>
                    </td>
                    <td><?= $code['scan_count'] ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($code['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/layout.php';
