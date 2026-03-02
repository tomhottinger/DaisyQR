<?php
/**
 * Admin: QR-Code Scannen
 */

requireLogin();

$base = BASE_PATH;
$currentPage = 'scan';
$pageTitle = 'QR-Code scannen - QR-Code Verwaltung';

ob_start();
?>

<div class="card">
    <h1>QR-Code scannen</h1>
    <p style="color: var(--gray-500); margin-bottom: 16px;">
        Der Code wird geprueft. Bei Berechtigung wird die Ziel-URL sofort geoeffnet.
    </p>

    <div id="scanMessage" class="alert" style="display: none;"></div>

    <div class="scanner-shell">
        <video id="scannerVideo" class="scanner-video" autoplay playsinline muted></video>
        <div class="scanner-overlay"></div>
    </div>

    <div class="actions">
        <button id="startScanBtn" class="btn primary" type="button">Scanner starten</button>
        <button id="stopScanBtn" class="btn" type="button" disabled>Scanner stoppen</button>
    </div>

    <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--gray-200)">

    <form id="manualScanForm">
        <div class="form-group">
            <label for="manualScanInput">Code oder QR-Inhalt manuell eingeben</label>
            <input id="manualScanInput" type="text" placeholder="z. B. a1b2c3d4e5f6 oder komplette URL" required>
        </div>
        <button class="btn" type="submit">Pruefen und oeffnen</button>
    </form>
</div>

<script>
(function() {
    const video = document.getElementById('scannerVideo');
    const messageBox = document.getElementById('scanMessage');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const manualForm = document.getElementById('manualScanForm');
    const manualInput = document.getElementById('manualScanInput');

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

    async function resolveAndOpen(rawValue) {
        const codeId = normalizeCodeId(rawValue);
        if (!codeId) {
            showMessage('Kein gueltiger Code erkannt (erwartet 12-stellige Hex-ID).', 'error');
            return;
        }

        try {
            const response = await fetch('<?= $base ?>/api/resolve/' + codeId);
            const payload = await response.json();

            if (payload.action === 'OPEN_CODE' && payload.code_url) {
                showMessage('Code gueltig. Ziel wird geoeffnet ...', 'success');
                window.location.href = payload.code_url;
                return;
            }

            if (payload.action === 'REDIRECT_TO_PROGRAMMING' && payload.programming_url) {
                showMessage('Code ist nicht programmiert. Weiterleitung zur Programmierung ...', 'warning');
                window.location.href = payload.programming_url;
                return;
            }

            if (payload.action === 'PROMPT_LOGIN' && payload.login_url) {
                showMessage('Bitte zuerst einloggen.', 'warning');
                window.location.href = payload.login_url;
                return;
            }

            showMessage(payload.message || 'Code konnte nicht geoeffnet werden.', 'error');
        } catch (err) {
            showMessage('Technischer Fehler beim Pruefen des Codes.', 'error');
        }
    }

    async function startScanner() {
        if (running) return;

        if (!('BarcodeDetector' in window)) {
            showMessage('BarcodeDetector wird in diesem Browser nicht unterstuetzt. Nutze bitte die manuelle Eingabe.', 'warning');
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
                await resolveAndOpen(rawValue);
                return;
            }
        } catch (err) {
            // Kamera kann kurzzeitig Frames nicht lesen; still weiterlaufen lassen.
        }

        scanLoopId = requestAnimationFrame(scanLoop);
    }

    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);

    manualForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        await resolveAndOpen(manualInput.value);
    });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/layout.php';
