/**
 * QR-Code Webapp Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Service Worker fuer PWA registrieren
    if ('serviceWorker' in navigator && window.isSecureContext) {
        const base = (window.APP_BASE || '.').replace(/\/$/, '');
        navigator.serviceWorker.register(base + '/sw.js').catch(() => {
            // Registrierung stillschweigend ignorieren
        });
    }

    // Alle Checkboxen auswählen
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.code-select').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    // URL automatisch mit https:// ergänzen
    const urlInputs = document.querySelectorAll('input[type="url"]');
    urlInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value && !value.match(/^https?:\/\//i)) {
                this.value = 'https://' + value;
            }
        });
    });

    // Bestätigungsdialoge für gefährliche Aktionen
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Copy to clipboard
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.dataset.copy;
            navigator.clipboard.writeText(text).then(() => {
                const original = this.textContent;
                this.textContent = 'Kopiert!';
                setTimeout(() => this.textContent = original, 1500);
            });
        });
    });
});

/**
 * API-Helfer
 */
const API = {
    async get(endpoint) {
        const response = await fetch(endpoint);
        return response.json();
    },

    async post(endpoint, data) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    },

    async delete(endpoint) {
        const response = await fetch(endpoint, { method: 'DELETE' });
        return response.json();
    }
};

/**
 * QR-Code programmieren via API
 */
async function programCode(codeId, url, title = '', force = false) {
    return API.post('/api/program', {
        code_id: codeId,
        target_url: url,
        title: title,
        force: force
    });
}

/**
 * Neue Codes generieren
 */
async function generateCodes(count = 1) {
    return API.post('/api/generate', { count: count });
}
