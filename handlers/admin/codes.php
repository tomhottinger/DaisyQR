<?php
/**
 * Admin: Code-Verwaltung
 */

requireLogin();

$base = BASE_PATH;
$user = getCurrentUser();
$message = '';

$filterParams = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => (string)($_GET['status'] ?? 'all'),
    'sort' => (string)($_GET['sort'] ?? 'updated_desc'),
    'per_page' => (int)($_GET['per_page'] ?? 20),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
];

$allowedStatus = ['all', 'programmed', 'unprogrammed', 'public', 'private'];
if (!in_array($filterParams['status'], $allowedStatus, true)) {
    $filterParams['status'] = 'all';
}

$allowedSort = [
    'updated_desc',
    'updated_asc',
    'scans_desc',
    'scans_asc',
    'id_asc',
    'id_desc',
    'title_asc',
    'title_desc',
];
if (!in_array($filterParams['sort'], $allowedSort, true)) {
    $filterParams['sort'] = 'updated_desc';
}

$allowedPerPage = [10, 20, 50, 100];
if (!in_array($filterParams['per_page'], $allowedPerPage, true)) {
    $filterParams['per_page'] = 20;
}

$querySuffix = '';
if (!empty($_GET)) {
    $querySuffix = '?' . http_build_query($_GET);
}

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $codeId = $_POST['code_id'] ?? '';

    $code = getCode($codeId);
    if ($code && canAccessCode($code, $user)) {
        switch ($action) {
            case 'delete':
                if (unprogramCode($codeId)) {
                    $message = 'Programmierung geloescht.';
                }
                break;
            case 'delete_complete':
                if (deleteCode($codeId)) {
                    $message = 'Code komplett geloescht.';
                }
                break;
            case 'toggle_public':
                $newStatus = empty($code['is_public']);
                if (setCodePublic($codeId, $newStatus)) {
                    $message = $newStatus ? 'Code ist jetzt oeffentlich.' : 'Code ist jetzt privat.';
                }
                break;
        }
    }
}

$allCodes = getUserCodes($user['id']);

$filteredCodes = array_values(array_filter($allCodes, function (array $code) use ($filterParams): bool {
    if ($filterParams['q'] !== '') {
        $needle = strtolower($filterParams['q']);
        $haystack = strtolower(
            (string)$code['id'] . ' ' .
            (string)($code['title'] ?? '') . ' ' .
            (string)($code['target_url'] ?? '')
        );
        if (strpos($haystack, $needle) === false) {
            return false;
        }
    }

    $isProgrammed = !empty($code['target_url']);
    $isPublic = !empty($code['is_public']);

    switch ($filterParams['status']) {
        case 'programmed':
            return $isProgrammed;
        case 'unprogrammed':
            return !$isProgrammed;
        case 'public':
            return $isProgrammed && $isPublic;
        case 'private':
            return $isProgrammed && !$isPublic;
        default:
            return true;
    }
}));

usort($filteredCodes, function (array $a, array $b) use ($filterParams): int {
    switch ($filterParams['sort']) {
        case 'updated_asc':
            return strcmp((string)$a['updated_at'], (string)$b['updated_at']);
        case 'updated_desc':
            return strcmp((string)$b['updated_at'], (string)$a['updated_at']);
        case 'scans_asc':
            return ((int)$a['scan_count']) <=> ((int)$b['scan_count']);
        case 'scans_desc':
            return ((int)$b['scan_count']) <=> ((int)$a['scan_count']);
        case 'id_asc':
            return strcmp((string)$a['id'], (string)$b['id']);
        case 'id_desc':
            return strcmp((string)$b['id'], (string)$a['id']);
        case 'title_asc':
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        case 'title_desc':
            return strcmp((string)($b['title'] ?? ''), (string)($a['title'] ?? ''));
        default:
            return 0;
    }
});

$totalFiltered = count($filteredCodes);
$perPage = $filterParams['per_page'];
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($filterParams['page'], $totalPages);
$offset = ($page - 1) * $perPage;
$codes = array_slice($filteredCodes, $offset, $perPage);

$queryForLinks = $_GET;
unset($queryForLinks['page']);
$baseQuery = http_build_query($queryForLinks);
$baseQueryPrefix = $baseQuery !== '' ? $baseQuery . '&' : '';

$statusChips = [
    'all' => 'Alle',
    'programmed' => 'Programmiert',
    'unprogrammed' => 'Nicht programmiert',
    'public' => 'Oeffentlich',
    'private' => 'Privat',
];

$currentPage = 'codes';
$pageTitle = 'Codes verwalten - QR-Code Verwaltung';

ob_start();
?>

<div class="card">
    <h1>Codes verwalten</h1>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="get" class="codes-filter-form">
        <div class="codes-filter-grid">
            <div class="form-group">
                <label for="q">Suche</label>
                <input type="text" id="q" name="q" value="<?= htmlspecialchars($filterParams['q']) ?>" placeholder="Code, Titel oder URL">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="all" <?= $filterParams['status'] === 'all' ? 'selected' : '' ?>>Alle</option>
                    <option value="programmed" <?= $filterParams['status'] === 'programmed' ? 'selected' : '' ?>>Programmiert</option>
                    <option value="unprogrammed" <?= $filterParams['status'] === 'unprogrammed' ? 'selected' : '' ?>>Nicht programmiert</option>
                    <option value="public" <?= $filterParams['status'] === 'public' ? 'selected' : '' ?>>Oeffentlich</option>
                    <option value="private" <?= $filterParams['status'] === 'private' ? 'selected' : '' ?>>Privat</option>
                </select>
            </div>
            <div class="form-group">
                <label for="sort">Sortierung</label>
                <select id="sort" name="sort">
                    <option value="updated_desc" <?= $filterParams['sort'] === 'updated_desc' ? 'selected' : '' ?>>Zuletzt aktualisiert (neu)</option>
                    <option value="updated_asc" <?= $filterParams['sort'] === 'updated_asc' ? 'selected' : '' ?>>Zuletzt aktualisiert (alt)</option>
                    <option value="scans_desc" <?= $filterParams['sort'] === 'scans_desc' ? 'selected' : '' ?>>Scans (meiste zuerst)</option>
                    <option value="scans_asc" <?= $filterParams['sort'] === 'scans_asc' ? 'selected' : '' ?>>Scans (wenigste zuerst)</option>
                    <option value="id_asc" <?= $filterParams['sort'] === 'id_asc' ? 'selected' : '' ?>>Code A-Z</option>
                    <option value="id_desc" <?= $filterParams['sort'] === 'id_desc' ? 'selected' : '' ?>>Code Z-A</option>
                    <option value="title_asc" <?= $filterParams['sort'] === 'title_asc' ? 'selected' : '' ?>>Titel A-Z</option>
                    <option value="title_desc" <?= $filterParams['sort'] === 'title_desc' ? 'selected' : '' ?>>Titel Z-A</option>
                </select>
            </div>
            <div class="form-group">
                <label for="per_page">Pro Seite</label>
                <select id="per_page" name="per_page">
                    <?php foreach ($allowedPerPage as $value): ?>
                        <option value="<?= $value ?>" <?= $filterParams['per_page'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="actions" style="margin-top: 8px;">
            <button type="submit" class="btn primary">Anwenden</button>
            <a href="<?= $base ?>/admin/codes" class="btn">Zuruecksetzen</a>
        </div>
    </form>

    <p class="codes-meta">
        <?= $totalFiltered ?> Treffer, Seite <?= $page ?> von <?= $totalPages ?>
    </p>

    <div class="filter-chips" aria-label="Status Schnellfilter">
        <?php foreach ($statusChips as $statusKey => $statusLabel): ?>
            <?php
            $chipQuery = $_GET;
            $chipQuery['status'] = $statusKey;
            $chipQuery['page'] = 1;
            $chipHref = $base . '/admin/codes?' . http_build_query($chipQuery);
            ?>
            <a href="<?= htmlspecialchars($chipHref) ?>" class="chip <?= $filterParams['status'] === $statusKey ? 'active' : '' ?>">
                <?= htmlspecialchars($statusLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($codes)): ?>
        <div class="empty-state">
            <p>Keine Codes fuer diese Filter gefunden.</p>
            <a href="<?= $base ?>/admin/generate" class="btn primary">Codes erstellen</a>
        </div>
    <?php else: ?>
        <div class="table-container codes-desktop">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Titel</th>
                        <th>Ziel-URL</th>
                        <th>Status</th>
                        <th>Scans</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                    <tr>
                        <td>
                            <a href="<?= $base ?>/<?= htmlspecialchars($code['id']) ?>" target="_blank">
                                <code><?= htmlspecialchars($code['id']) ?></code>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($code['title'] ?: '-') ?></td>
                        <td class="url-cell">
                            <?php if ($code['target_url']): ?>
                                <a href="<?= htmlspecialchars($code['target_url']) ?>" target="_blank">
                                    <?= htmlspecialchars($code['target_url']) ?>
                                </a>
                            <?php else: ?>
                                <em style="color: var(--gray-500)">-</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($code['target_url']): ?>
                                <?php if ($code['is_public']): ?>
                                    <span class="badge success">Oeffentlich</span>
                                <?php else: ?>
                                    <span class="badge">Privat</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <em style="color: var(--gray-500)">-</em>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$code['scan_count'] ?></td>
                        <td class="code-actions-cell">
                            <div class="code-actions">
                                <a href="<?= $base ?>/<?= htmlspecialchars($code['id']) ?>?edit=1" class="btn small">Bearbeiten</a>
                                <form method="post" action="<?= $base ?>/admin/codes<?= $querySuffix ?>" onsubmit="return confirm('Code komplett loeschen? Diese Aktion kann nicht rueckgaengig gemacht werden.')">
                                    <input type="hidden" name="action" value="delete_complete">
                                    <input type="hidden" name="code_id" value="<?= htmlspecialchars($code['id']) ?>">
                                    <button type="submit" class="btn small danger">Loeschen</button>
                                </form>
                                <?php if ($code['target_url']): ?>
                                    <form method="post" action="<?= $base ?>/admin/codes<?= $querySuffix ?>">
                                        <input type="hidden" name="action" value="toggle_public">
                                        <input type="hidden" name="code_id" value="<?= htmlspecialchars($code['id']) ?>">
                                        <button type="submit" class="btn small <?= $code['is_public'] ? '' : 'success' ?>">
                                            <?= $code['is_public'] ? 'Privat setzen' : 'Freigeben' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= $base ?>/admin/codes<?= $querySuffix ?>" onsubmit="return confirm('Programmierung loeschen?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="code_id" value="<?= htmlspecialchars($code['id']) ?>">
                                        <button type="submit" class="btn small danger">Reset</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="codes-mobile">
            <?php foreach ($codes as $code): ?>
                <div class="mobile-code-card">
                    <div class="mobile-code-head">
                        <a href="<?= $base ?>/<?= htmlspecialchars($code['id']) ?>" target="_blank"><code><?= htmlspecialchars($code['id']) ?></code></a>
                        <span class="badge <?= !empty($code['is_public']) && !empty($code['target_url']) ? 'success' : '' ?>">
                            <?php if (!empty($code['target_url'])): ?>
                                <?= !empty($code['is_public']) ? 'Oeffentlich' : 'Privat' ?>
                            <?php else: ?>
                                Nicht programmiert
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="mobile-code-row"><strong>Titel:</strong> <?= htmlspecialchars($code['title'] ?: '-') ?></div>
                    <div class="mobile-code-row"><strong>URL:</strong>
                        <?php if (!empty($code['target_url'])): ?>
                            <a href="<?= htmlspecialchars($code['target_url']) ?>" target="_blank"><?= htmlspecialchars($code['target_url']) ?></a>
                        <?php else: ?>
                            <em style="color: var(--gray-500)">-</em>
                        <?php endif; ?>
                    </div>
                    <div class="mobile-code-row"><strong>Scans:</strong> <?= (int)$code['scan_count'] ?></div>
                    <div class="code-actions" style="margin-top: 10px;">
                        <a href="<?= $base ?>/<?= htmlspecialchars($code['id']) ?>?edit=1" class="btn small">Bearbeiten</a>
                        <form method="post" action="<?= $base ?>/admin/codes<?= $querySuffix ?>" onsubmit="return confirm('Code komplett loeschen? Diese Aktion kann nicht rueckgaengig gemacht werden.')">
                            <input type="hidden" name="action" value="delete_complete">
                            <input type="hidden" name="code_id" value="<?= htmlspecialchars($code['id']) ?>">
                            <button type="submit" class="btn small danger">Loeschen</button>
                        </form>
                        <?php if ($code['target_url']): ?>
                            <form method="post" action="<?= $base ?>/admin/codes<?= $querySuffix ?>">
                                <input type="hidden" name="action" value="toggle_public">
                                <input type="hidden" name="code_id" value="<?= htmlspecialchars($code['id']) ?>">
                                <button type="submit" class="btn small <?= $code['is_public'] ? '' : 'success' ?>">
                                    <?= $code['is_public'] ? 'Privat setzen' : 'Freigeben' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= $base ?>/admin/codes<?= $querySuffix ?>" onsubmit="return confirm('Programmierung loeschen?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="code_id" value="<?= htmlspecialchars($code['id']) ?>">
                                <button type="submit" class="btn small danger">Reset</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination" aria-label="Seitennavigation">
                <?php if ($page > 1): ?>
                    <a class="btn small" href="<?= $base ?>/admin/codes?<?= $baseQueryPrefix ?>page=<?= $page - 1 ?>">Zurueck</a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a class="btn small <?= $p === $page ? 'primary' : '' ?>" href="<?= $base ?>/admin/codes?<?= $baseQueryPrefix ?>page=<?= $p ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="btn small" href="<?= $base ?>/admin/codes?<?= $baseQueryPrefix ?>page=<?= $page + 1 ?>">Weiter</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/layout.php';
