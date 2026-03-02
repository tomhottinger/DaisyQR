<?php
/**
 * Basis-Layout Template
 */

$user = getCurrentUser();
$currentPage = $currentPage ?? '';
$base = BASE_PATH;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title><?= htmlspecialchars($pageTitle ?? 'QR-Code Verwaltung') ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
    <link rel="manifest" href="<?= $base ?>/manifest.webmanifest">
    <link rel="icon" href="<?= $base ?>/assets/icon.svg" type="image/svg+xml">
</head>
<body>
    <div class="container">
        <nav class="nav no-print">
            <div class="nav-inner">
                <a href="<?= $base ?>/" class="nav-brand">QR-Codes</a>
                <ul class="nav-links">
                    <li><a href="<?= $base ?>/admin/codes" class="<?= $currentPage === 'codes' ? 'active' : '' ?>">Codes</a></li>
                    <li><a href="<?= $base ?>/admin/generate" class="<?= $currentPage === 'generate' ? 'active' : '' ?>">Generieren</a></li>
                    <li><a href="<?= $base ?>/admin/print" class="<?= $currentPage === 'print' ? 'active' : '' ?>">Drucken</a></li>
                    <?php if (isAdmin($user)): ?>
                    <li><a href="<?= $base ?>/admin/users" class="<?= $currentPage === 'users' ? 'active' : '' ?>">Benutzer</a></li>
                    <?php endif; ?>
                </ul>
                <div class="nav-user">
                    <?php if ($user): ?>
                        <?= htmlspecialchars($user['name']) ?>
                        <a href="<?= $base ?>/logout" class="btn small">Logout</a>
                    <?php else: ?>
                        <a href="<?= $base ?>/login" class="btn small primary">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <?= $content ?? '' ?>
        <div class="site-signature">artScape cybernetics</div>
    </div>

    <script>window.APP_BASE = '<?= $base ?>';</script>
    <script src="<?= $base ?>/assets/app.js"></script>
</body>
</html>
