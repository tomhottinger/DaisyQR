<?php
/**
 * Root-Seite: QR-Scanner
 */

$base = BASE_PATH;
$pendingShare = $_SESSION['pending_share'] ?? null;
$pendingShareUrl = '';
$pendingShareTitle = '';
$shareMode = false;

if (is_array($pendingShare) && !empty($pendingShare['url']) && filter_var($pendingShare['url'], FILTER_VALIDATE_URL)) {
    $pendingShareUrl = (string)$pendingShare['url'];
    $pendingShareTitle = trim((string)($pendingShare['title'] ?? ''));
    $shareMode = true;
} else {
    unset($_SESSION['pending_share']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title>QR-Code Scanner</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
    <link rel="manifest" href="<?= $base ?>/manifest.webmanifest">
    <link rel="icon" href="<?= $base ?>/assets/icon.svg" type="image/svg+xml">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>QR-Code scannen</h1>

            <?php if ($shareMode): ?>
                <div class="alert warning" style="margin-bottom: 16px;">
                    <strong>Share-Modus aktiv:</strong> Diese URL wird auf deinen Code geschrieben:<br>
                    <code><?= htmlspecialchars($pendingShareUrl) ?></code>
                    <?php if ($pendingShareTitle): ?>
                        <br><small>Titel: <?= htmlspecialchars($pendingShareTitle) ?></small>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= $base ?>/share" style="margin-bottom: 16px;">
                    <input type="hidden" name="clear_share" value="1">
                    <button class="btn" type="submit">Share-Modus beenden</button>
                </form>
            <?php else: ?>
                <p style="color: var(--gray-500); margin-bottom: 16px;">
                    Scanne einen QR-Code oder gib ihn manuell ein.
                </p>
            <?php endif; ?>

            <div id="scanMessage" class="alert" style="display: none;"></div>

            <div class="scanner-shell">
                <video id="scannerVideo" class="scanner-video" autoplay playsinline muted></video>
                <div class="scanner-overlay"></div>
            </div>

            <div class="actions">
                <button id="startScanBtn" class="btn primary" type="button">Scanner starten</button>
                <button id="stopScanBtn" class="btn" type="button" disabled>Scanner stoppen</button>
                <a href="<?= $base ?>/login" class="btn">Login</a>
            </div>

            <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--gray-200)">

            <form id="manualScanForm">
                <div class="form-group">
                    <label for="manualScanInput">Code oder QR-Inhalt manuell eingeben</label>
                    <input id="manualScanInput" type="text" placeholder="z. B. a1b2c3d4e5f6 oder komplette URL" required>
                </div>
                <button class="btn" type="submit">Pruefen</button>
            </form>
        </div>
    </div>

<script>
(function() {
    const video = document.getElementById('scannerVideo');
    const messageBox = document.getElementById('scanMessage');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const manualForm = document.getElementById('manualScanForm');
    const manualInput = document.getElementById('manualScanInput');
    const isShareMode = <?= $shareMode ? 'true' : 'false' ?>;

    let detector = null;
    let stream = null;
    let running = false;
    let scanLoopId = null;

    function showMessage(text, level) {
        messageBox.textContent = text;
        messageBox.className = 'alert ' + level;
        messageBox.style.display = 'block';
    }

    function normalizeCodeId(raw) {
        if (!raw) return null;
        const value = raw.trim();

        const exact = value.match(/^([a-f0-9]{12})$/i);
        if (exact) return exact[1].toLowerCase();

        const urlLike = value.match(/(?:\/|=)([a-f0-9]{12})(?:\/?$|[?#&])/i);
        if (urlLike) return urlLike[1].toLowerCase();

        return null;
    }

    async function postJson(url) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: '{}'
        });
        const payload = await response.json();
        return { response, payload };
    }

    async function resolveAction(rawValue) {
        const codeId = normalizeCodeId(rawValue);
        if (!codeId) {
            showMessage('Kein gueltiger Code erkannt (12-stellige Hex-ID erwartet).', 'error');
            return;
        }

        try {
            if (isShareMode) {
                const { payload } = await postJson('<?= $base ?>/api/share/scan/' + codeId);

                if (payload.action === 'SHARE_PROGRAMMED') {
                    showMessage('URL wurde erfolgreich auf den Code programmiert.', 'success');
                    setTimeout(function() { window.location.href = '<?= $base ?>/' + codeId; }, 400);
                    return;
                }

                if (payload.action === 'SHARE_CONFIRM_OVERWRITE') {
                    const ok = confirm(
                        'Der Code ist bereits programmiert.\n\nAktuell: ' + payload.current_url + '\nNeu: ' + payload.new_url + '\n\nUeberschreiben?'
                    );

                    if (!ok) {
                        showMessage('Ueberschreiben abgebrochen. Scanne einen anderen Code oder beende den Share-Modus.', 'warning');
                        return;
                    }

                    const overwrite = await postJson('<?= $base ?>/api/share/overwrite/' + codeId);
                    if (overwrite.payload.action === 'SHARE_OVERWRITTEN') {
                        showMessage('Code wurde erfolgreich ueberschrieben.', 'success');
                        setTimeout(function() { window.location.href = '<?= $base ?>/' + codeId; }, 400);
                        return;
                    }

                    showMessage(overwrite.payload.message || 'Ueberschreiben fehlgeschlagen.', 'error');
                    return;
                }

                if (payload.action === 'PROMPT_LOGIN' && payload.login_url) {
                    showMessage('Bitte zuerst einloggen.', 'warning');
                    setTimeout(function() { window.location.href = payload.login_url; }, 600);
                    return;
                }

                if (payload.action === 'ERROR_CODE_NOT_FOUND') {
                    showMessage('Code nicht gefunden.', 'error');
                    return;
                }

                if (payload.action === 'ERROR_NOT_OWNER') {
                    showMessage('Dieser Code gehoert dir nicht.', 'error');
                    return;
                }

                showMessage(payload.message || 'Fehler im Share-Modus.', 'error');
                return;
            }

            const response = await fetch('<?= $base ?>/api/resolve/' + codeId);
            const payload = await response.json();
            const action = payload.action;

            if (action === 'OPEN_CODE' && payload.code_url) {
                window.location.href = payload.code_url;
                return;
            }

            if (action === 'REDIRECT_TO_PROGRAMMING' && payload.programming_url) {
                window.location.href = payload.programming_url;
                return;
            }

            if (action === 'PROMPT_LOGIN' && payload.login_url) {
                showMessage('Bitte zuerst einloggen.', 'warning');
                setTimeout(function() {
                    window.location.href = payload.login_url;
                }, 600);
                return;
            }

            if (action === 'ERROR_CODE_NOT_FOUND') {
                showMessage('Code nicht gefunden.', 'error');
                return;
            }

            if (action === 'ERROR_NOT_PROGRAMMED') {
                showMessage('Code ist nicht programmiert.', 'error');
                return;
            }

            if (action === 'ERROR_NOT_OWNER') {
                showMessage('Keine Berechtigung fuer diesen Code.', 'error');
                return;
            }

            showMessage(payload.message || 'Unbekannte Antwort vom Server.', 'error');
        } catch (err) {
            showMessage('Technischer Fehler beim Pruefen des Codes.', 'error');
        }
    }

    async function startScanner() {
        if (running) return;

        if (!('BarcodeDetector' in window)) {
            showMessage('BarcodeDetector wird in diesem Browser nicht unterstuetzt. Nutze die manuelle Eingabe.', 'warning');
            return;
        }

        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false
            });
            video.srcObject = stream;
            running = true;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            showMessage('Scanner aktiv. QR-Code vor die Kamera halten.', 'success');
            scanLoop();
        } catch (err) {
            showMessage('Kamera konnte nicht gestartet werden. Bitte Berechtigung pruefen.', 'error');
        }
    }

    function stopScanner() {
        running = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;

        if (scanLoopId) {
            cancelAnimationFrame(scanLoopId);
            scanLoopId = null;
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.srcObject = null;
    }

    async function scanLoop() {
        if (!running || !detector) return;

        try {
            const barcodes = await detector.detect(video);
            if (barcodes && barcodes.length > 0) {
                const rawValue = barcodes[0].rawValue || '';
                stopScanner();
                await resolveAction(rawValue);
                return;
            }
        } catch (err) {
            // Ignorieren und weiter scannen.
        }

        scanLoopId = requestAnimationFrame(scanLoop);
    }

    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);

    manualForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        await resolveAction(manualInput.value);
    });

    // Root-Scanner direkt beim Laden starten
    startScanner();
})();
</script>
    <script>window.APP_BASE = '<?= $base ?>';</script>
    <script src="<?= $base ?>/assets/app.js"></script>
    <div class="site-signature">artScape cybernetics</div>
</body>
</html>
