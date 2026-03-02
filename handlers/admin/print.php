<?php
/**
 * Admin: Druckansicht
 */

requireLogin();

$base = BASE_PATH;
$user = getCurrentUser();
$allCodes = getUserCodes($user['id'] ?? null);

// Codes aus URL-Parameter oder alle
$selectedIds = isset($_GET['codes']) ? explode(',', $_GET['codes']) : [];
$selectedIds = array_filter($selectedIds);

// Filtern
$filter = $_GET['filter'] ?? 'all';
$format = $_GET['format'] ?? 'a6-grid';
$allowedFormats = ['single', 'a6-2', 'a6-3', 'a6-4', 'a6-grid'];
if (!in_array($format, $allowedFormats, true)) {
    $format = 'a6-grid';
}

$codes = [];
if (!empty($selectedIds)) {
    foreach ($allCodes as $code) {
        if (in_array($code['id'], $selectedIds)) {
            $codes[] = $code;
        }
    }
} else {
    switch ($filter) {
        case 'programmed':
            $codes = array_filter($allCodes, fn($c) => !empty($c['target_url']));
            break;
        case 'empty':
            $codes = array_filter($allCodes, fn($c) => empty($c['target_url']));
            break;
        default:
            $codes = $allCodes;
    }
}

$codes = array_values($codes);

// Nur Druckansicht?
$printOnly = isset($_GET['print']);

if ($printOnly):
    $formatConfig = [
        'a6-2' => ['per_page' => 2, 'cols' => 1, 'rows' => 2, 'qr_size' => 210],
        'a6-3' => ['per_page' => 3, 'cols' => 1, 'rows' => 3, 'qr_size' => 170],
        'a6-4' => ['per_page' => 4, 'cols' => 2, 'rows' => 2, 'qr_size' => 170],
        'a6-grid' => ['per_page' => 6, 'cols' => 2, 'rows' => 3, 'qr_size' => 150],
    ];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>QR-Codes drucken</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            background: white;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(2, 105mm);
            grid-auto-rows: 148mm;
            justify-content: center;
            align-content: start;
        }
        .print-page {
            width: 105mm;
            height: 148mm;
            padding: 3mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .print-grid {
            display: grid;
            gap: 4mm;
            height: 100%;
        }
        .print-single {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        .print-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4mm;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        .print-item img {
            max-width: 100%;
            max-height: 19.2mm; /* 2/3 von 28.8mm */
        }
        .print-single img {
            max-width: 37.3mm; /* 2/3 von 56mm */
            max-height: 37.3mm; /* 2/3 von 56mm */
        }
        .print-item .label {
            font-size: 7pt;
            margin-top: 1mm;
            word-break: break-all;
            max-width: 100%;
        }
        .print-single .label {
            font-size: 12pt;
            margin-top: 5mm;
        }
        .print-item .code-id {
            font-family: monospace;
            font-size: 6pt;
            color: #666;
        }
        .print-single .code-id {
            font-size: 10pt;
        }
    </style>
</head>
<body onload="window.print()">
<?php
if ($format === 'single'):
    foreach ($codes as $code):
?>
    <div class="print-page">
        <div class="print-single">
            <img src="<?= $base ?>/api/qr.php?id=<?= $code['id'] ?>&size=300" alt="QR">
            <?php if ($code['title']): ?>
                <div class="label"><?= htmlspecialchars($code['title']) ?></div>
            <?php endif; ?>
            <div class="code-id"><?= $code['id'] ?></div>
        </div>
    </div>
<?php
    endforeach;
else:
    $cfg = $formatConfig[$format] ?? $formatConfig['a6-grid'];
    $chunks = array_chunk($codes, $cfg['per_page']);
    foreach ($chunks as $chunk):
?>
    <div class="print-page">
        <div class="print-grid" style="grid-template-columns: repeat(<?= (int)$cfg['cols'] ?>, 1fr); grid-template-rows: repeat(<?= (int)$cfg['rows'] ?>, 1fr);">
            <?php foreach ($chunk as $code): ?>
            <div class="print-item">
                <img src="<?= $base ?>/api/qr.php?id=<?= $code['id'] ?>&size=<?= (int)$cfg['qr_size'] ?>" alt="QR">
                <?php if ($code['title']): ?>
                    <div class="label"><?= htmlspecialchars($code['title']) ?></div>
                <?php endif; ?>
                <div class="code-id"><?= $code['id'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php
    endforeach;
endif;
?>
</body>
</html>
<?php
exit;
endif;

// Normale Ansicht mit Auswahl
$currentPage = 'print';
$pageTitle = 'Drucken - QR-Code Verwaltung';

ob_start();
?>

<div class="card">
    <h1>QR-Codes drucken</h1>

    <form method="get" class="program-form">
        <div class="form-group">
            <label>Filter</label>
            <select name="filter" onchange="this.form.submit()">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Alle Codes</option>
                <option value="programmed" <?= $filter === 'programmed' ? 'selected' : '' ?>>Nur programmierte</option>
                <option value="empty" <?= $filter === 'empty' ? 'selected' : '' ?>>Nur leere</option>
            </select>
        </div>

        <div class="form-group">
            <label>Format</label>
            <select name="format">
                <option value="single" <?= $format === 'single' ? 'selected' : '' ?>>Einzeln (1 pro A6)</option>
                <option value="a6-2" <?= $format === 'a6-2' ? 'selected' : '' ?>>A6 mit 2 Codes</option>
                <option value="a6-3" <?= $format === 'a6-3' ? 'selected' : '' ?>>A6 mit 3 Codes</option>
                <option value="a6-4" <?= $format === 'a6-4' ? 'selected' : '' ?>>A6 mit 4 Codes</option>
                <option value="a6-grid" <?= $format === 'a6-grid' ? 'selected' : '' ?>>A6 mit 6 Codes</option>
            </select>
        </div>
    </form>

    <label style="display:flex; align-items:center; gap:8px; margin: 10px 0 8px;">
        <input type="checkbox" id="generateNewCodes" value="1">
        Generiere neue Codes
    </label>
    <p style="margin-top:0; color: var(--gray-500); font-size: 0.9rem;">
        Mit aktivierter Option werden vor dem Drucken neue Codes erzeugt (Anzahl = Auswahl, oder 1 Seite wenn nichts ausgewählt ist).
    </p>

    <?php if (empty($codes)): ?>
        <div class="empty-state">
            <p>Keine Codes zum Drucken gefunden.</p>
        </div>
    <?php else: ?>
        <p><?= count($codes) ?> Codes ausgewählt</p>

        <div class="table-container" style="max-height: 400px; overflow-y: auto; margin: 16px 0">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Code</th>
                        <th>Titel</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                    <tr>
                        <td><input type="checkbox" class="code-select" value="<?= $code['id'] ?>" checked></td>
                        <td><code><?= $code['id'] ?></code></td>
                        <td><?= htmlspecialchars($code['title'] ?: '-') ?></td>
                        <td class="url-cell"><?= htmlspecialchars($code['target_url'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="actions">
            <button type="button" class="btn primary" onclick="printSelected()">Drucken</button>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.code-select').forEach(cb => cb.checked = this.checked);
});

document.getElementById('generateNewCodes')?.addEventListener('change', function() {
    if (!this.checked) return;
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = false;
    }
    document.querySelectorAll('.code-select').forEach(cb => {
        cb.checked = false;
    });
});

function printSelected() {
    const selected = Array.from(document.querySelectorAll('.code-select:checked'))
        .map(cb => cb.value);

    const format = document.querySelector('select[name="format"]').value;
    const generateNew = document.getElementById('generateNewCodes')?.checked;

    if (!generateNew && selected.length === 0) {
        alert('Bitte mindestens einen Code auswählen.');
        return;
    }

    if (generateNew) {
        const perPageByFormat = {
            'single': 1,
            'a6-2': 2,
            'a6-3': 3,
            'a6-4': 4,
            'a6-grid': 6
        };

        let needed = selected.length > 0 ? selected.length : (perPageByFormat[format] || 6);
        needed = Math.min(100, Math.max(1, needed));

        fetch('<?= $base ?>/api/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ count: needed })
        })
        .then(res => res.json())
        .then(payload => {
            if (!payload.success || !Array.isArray(payload.codes) || payload.codes.length === 0) {
                throw new Error('generate_failed');
            }

            const generatedIds = payload.codes.map(c => c.id);
            const url = '<?= $base ?>/admin/print?print=1&format=' + format + '&codes=' + generatedIds.join(',');
            window.open(url, '_blank');
        })
        .catch(() => {
            alert('Neue Codes konnten nicht erzeugt werden.');
        });
        return;
    }

    const url = '<?= $base ?>/admin/print?print=1&format=' + format + '&codes=' + selected.join(',');
    window.open(url, '_blank');
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/layout.php';
