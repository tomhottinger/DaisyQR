<?php
/**
 * Datenbank-Verbindung und Setup
 */

require_once __DIR__ . '/config.php';

function getDB(): SQLite3 {
    static $db = null;

    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA journal_mode = WAL');
    }

    return $db;
}

function initDatabase(): void {
    $db = getDB();

    // Users Tabelle
    $db->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            auth_token TEXT UNIQUE NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');

    // QR Codes Tabelle
    $db->exec('
        CREATE TABLE IF NOT EXISTS qr_codes (
            id TEXT PRIMARY KEY,
            user_id INTEGER,
            target_url TEXT,
            title TEXT,
            is_public INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            scan_count INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ');

    // Sessions Tabelle
    $db->exec('
        CREATE TABLE IF NOT EXISTS sessions (
            token TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ');

    // Index für schnellere Abfragen
    $db->exec('CREATE INDEX IF NOT EXISTS idx_qr_codes_user ON qr_codes(user_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at)');

    // Migration: is_admin Spalte hinzufügen falls nicht vorhanden
    $result = $db->query("PRAGMA table_info(users)");
    $hasIsAdmin = false;
    while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'is_admin') $hasIsAdmin = true;
    }
    if (!$hasIsAdmin) {
        $db->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0');
    }

    // Migration: is_public Spalte hinzufügen falls nicht vorhanden
    $result = $db->query("PRAGMA table_info(qr_codes)");
    $hasIsPublic = false;
    while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'is_public') $hasIsPublic = true;
    }
    if (!$hasIsPublic) {
        $db->exec('ALTER TABLE qr_codes ADD COLUMN is_public INTEGER DEFAULT 0');
    }

    // Migration: password_hash Spalte hinzufügen falls nicht vorhanden
    $result = $db->query("PRAGMA table_info(users)");
    $hasPasswordHash = false;
    while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'password_hash') $hasPasswordHash = true;
    }
    if (!$hasPasswordHash) {
        $db->exec('ALTER TABLE users ADD COLUMN password_hash TEXT');
    }

    // Erster Admin erstellen falls keine User existieren
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row['count'] == 0) {
        $authToken = bin2hex(random_bytes(16));
        $stmt = $db->prepare('INSERT INTO users (name, auth_token, is_admin) VALUES (?, ?, 1)');
        $stmt->bindValue(1, 'Admin', SQLITE3_TEXT);
        $stmt->bindValue(2, $authToken, SQLITE3_TEXT);
        $stmt->execute();
        // Token in Datei speichern für erstmaligen Zugriff
        file_put_contents(__DIR__ . '/data/first_admin_token.txt', $authToken);
    }

    // Sicherstellen, dass mindestens ein Admin ein Passwort hat
    $result = $db->query("SELECT COUNT(*) AS count FROM users WHERE is_admin = 1 AND password_hash IS NOT NULL AND password_hash != ''");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ((int)($row['count'] ?? 0) === 0) {
        $result = $db->query("SELECT id FROM users WHERE is_admin = 1 ORDER BY id ASC LIMIT 1");
        $admin = $result->fetchArray(SQLITE3_ASSOC);
        if ($admin) {
            $bootstrapPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($bootstrapPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bindValue(1, $passwordHash, SQLITE3_TEXT);
            $stmt->bindValue(2, (int)$admin['id'], SQLITE3_INTEGER);
            $stmt->execute();
            file_put_contents(__DIR__ . '/data/first_admin_password.txt', $bootstrapPassword);
        }
    }
}

// Datenbank initialisieren beim ersten Laden
initDatabase();

/**
 * Generiert eine zufällige ID für QR-Codes
 */
function generateCodeId(): string {
    $chars = '0123456789abcdef';
    $id = '';
    for ($i = 0; $i < ID_LENGTH; $i++) {
        $id .= $chars[random_int(0, 15)];
    }
    return $id;
}

/**
 * Generiert eine eindeutige Code-ID
 */
function generateUniqueCodeId(): string {
    $db = getDB();
    do {
        $id = generateCodeId();
        $stmt = $db->prepare('SELECT id FROM qr_codes WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $exists = $result->fetchArray() !== false;
    } while ($exists);

    return $id;
}

/**
 * Holt einen QR-Code aus der Datenbank
 */
function getCode(string $id): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM qr_codes WHERE id = ?');
    $stmt->bindValue(1, $id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

/**
 * Erstellt einen neuen QR-Code
 */
function createCode(string $id, ?int $userId = null): bool {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO qr_codes (id, user_id) VALUES (?, ?)');
    $stmt->bindValue(1, $id, SQLITE3_TEXT);
    $stmt->bindValue(2, $userId, $userId ? SQLITE3_INTEGER : SQLITE3_NULL);
    return $stmt->execute() !== false;
}

/**
 * Programmiert einen QR-Code mit einer URL
 */
function programCode(string $id, string $url, ?string $title = null, ?int $userId = null): bool {
    $db = getDB();
    $stmt = $db->prepare('
        UPDATE qr_codes
        SET target_url = ?, title = ?, user_id = COALESCE(?, user_id), updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->bindValue(1, $url, SQLITE3_TEXT);
    $stmt->bindValue(2, $title, $title ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->bindValue(3, $userId, $userId ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(4, $id, SQLITE3_TEXT);
    return $stmt->execute() !== false;
}

/**
 * Setzt den Public-Status eines Codes
 */
function setCodePublic(string $id, bool $isPublic): bool {
    $db = getDB();
    $stmt = $db->prepare('UPDATE qr_codes SET is_public = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bindValue(1, $isPublic ? 1 : 0, SQLITE3_INTEGER);
    $stmt->bindValue(2, $id, SQLITE3_TEXT);
    return $stmt->execute() !== false;
}

/**
 * Erhöht den Scan-Counter
 */
function incrementScanCount(string $id): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ?');
    $stmt->bindValue(1, $id, SQLITE3_TEXT);
    $stmt->execute();
}

/**
 * Löscht die Programmierung eines Codes
 */
function unprogramCode(string $id): bool {
    $db = getDB();
    $stmt = $db->prepare('UPDATE qr_codes SET target_url = NULL, title = NULL, is_public = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bindValue(1, $id, SQLITE3_TEXT);
    return $stmt->execute() !== false;
}

/**
 * Löscht einen Code komplett
 */
function deleteCode(string $id): bool {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM qr_codes WHERE id = ?');
    $stmt->bindValue(1, $id, SQLITE3_TEXT);
    return $stmt->execute() !== false;
}

/**
 * Holt alle Codes eines Benutzers
 */
function getUserCodes(?int $userId = null): array {
    $db = getDB();
    if ($userId) {
        $stmt = $db->prepare('SELECT * FROM qr_codes WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare('SELECT * FROM qr_codes ORDER BY updated_at DESC');
    }
    $result = $stmt->execute();

    $codes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $codes[] = $row;
    }
    return $codes;
}

/**
 * Authentisierung: User per Token finden
 */
function getUserByAuthToken(string $token): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE auth_token = ?');
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

/**
 * Erstellt einen neuen Benutzer
 */
function createUser(string $name, bool $isAdmin = false, ?string $password = null): array {
    $db = getDB();
    $authToken = bin2hex(random_bytes(16));
    $passwordHash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

    $stmt = $db->prepare('INSERT INTO users (name, auth_token, is_admin, password_hash) VALUES (?, ?, ?, ?)');
    $stmt->bindValue(1, $name, SQLITE3_TEXT);
    $stmt->bindValue(2, $authToken, SQLITE3_TEXT);
    $stmt->bindValue(3, $isAdmin ? 1 : 0, SQLITE3_INTEGER);
    $stmt->bindValue(4, $passwordHash, $passwordHash ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->execute();

    return [
        'id' => $db->lastInsertRowID(),
        'name' => $name,
        'auth_token' => $authToken,
        'password_hash' => $passwordHash,
        'is_admin' => $isAdmin ? 1 : 0
    ];
}

/**
 * Prüft ob ein Benutzername bereits existiert (case-insensitive)
 */
function userNameExists(string $name): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE name = ? COLLATE NOCASE LIMIT 1');
    $stmt->bindValue(1, $name, SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC) !== false;
}

/**
 * User per Name + Passwort authentifizieren
 */
function authenticateUserByPassword(string $name, string $password): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE name = ? COLLATE NOCASE');
    $stmt->bindValue(1, $name, SQLITE3_TEXT);
    $result = $stmt->execute();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $hash = $row['password_hash'] ?? '';
        if (!empty($hash) && password_verify($password, $hash)) {
            return $row;
        }
    }

    return null;
}

/**
 * Passwort für Benutzer setzen oder zurücksetzen
 */
function setUserPassword(int $userId, string $password): bool {
    $db = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->bindValue(1, $hash, SQLITE3_TEXT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    return $stmt->execute() !== false;
}

/**
 * Session erstellen
 */
function createSession(int $userId): string {
    $db = getDB();
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    $stmt = $db->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $expiresAt, SQLITE3_TEXT);
    $stmt->execute();

    return $token;
}

/**
 * Session validieren
 */
function validateSession(string $token): ?array {
    $db = getDB();

    // Alte Sessions aufräumen
    $db->exec("DELETE FROM sessions WHERE expires_at < datetime('now')");

    $stmt = $db->prepare('
        SELECT u.* FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > datetime("now")
    ');
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

/**
 * Aktueller eingeloggter Benutzer
 */
function getCurrentUser(): ?array {
    if (isset($_COOKIE['session_token'])) {
        return validateSession($_COOKIE['session_token']);
    }
    return null;
}

/**
 * Prüft ob User Admin ist
 */
function isAdmin(?array $user = null): bool {
    $user = $user ?? getCurrentUser();
    return $user && !empty($user['is_admin']);
}

/**
 * Prüft ob User Zugriff auf einen Code hat
 */
function canAccessCode(array $code, ?array $user = null): bool {
    $user = $user ?? getCurrentUser();

    // Admin kann alles
    if (isAdmin($user)) {
        return true;
    }

    // Eigener Code
    if ($user && $code['user_id'] == $user['id']) {
        return true;
    }

    return false;
}

/**
 * Prüft ob ein Code geöffnet werden kann (Redirect)
 */
function canOpenCode(array $code, ?array $user = null): bool {
    // Code muss programmiert sein
    if (empty($code['target_url'])) {
        return false;
    }

    // Public Code kann jeder öffnen
    if (!empty($code['is_public'])) {
        return true;
    }

    // Sonst nur mit Zugriff
    return canAccessCode($code, $user);
}

/**
 * Einheitliche Entscheidungslogik fuer Code-Zugriff
 *
 * Mögliche Actions:
 * - ERROR_CODE_NOT_FOUND
 * - ERROR_NOT_PROGRAMMED
 * - ERROR_NOT_OWNER
 * - PROMPT_LOGIN
 * - REDIRECT_TO_PROGRAMMING
 * - OPEN_CODE
 */
function resolveCodeAction(string $codeId, ?array $user = null): array {
    $code = getCode($codeId);

    if (!$code) {
        return ['action' => 'ERROR_CODE_NOT_FOUND'];
    }

    $isReleased = !empty($code['is_public']);
    $isProgrammed = !empty($code['target_url']);

    if ($isReleased) {
        if ($isProgrammed) {
            return [
                'action' => 'OPEN_CODE',
                'code' => $code,
            ];
        }

        return ['action' => 'ERROR_NOT_PROGRAMMED', 'code' => $code];
    }

    if (!$user) {
        return ['action' => 'PROMPT_LOGIN', 'code' => $code];
    }

    $ownerId = isset($code['user_id']) ? (int)$code['user_id'] : 0;
    $currentUserId = isset($user['id']) ? (int)$user['id'] : 0;
    if ($currentUserId !== $ownerId) {
        return ['action' => 'ERROR_NOT_OWNER', 'code' => $code];
    }

    if (!$isProgrammed) {
        return ['action' => 'REDIRECT_TO_PROGRAMMING', 'code' => $code];
    }

    return [
        'action' => 'OPEN_CODE',
        'code' => $code,
    ];
}

/**
 * Login erforderlich - Redirect zur Login-Seite
 */
function requireLogin(): void {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: ' . BASE_PATH . '/login');
        exit;
    }
}

/**
 * Admin erforderlich
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo 'Keine Berechtigung';
        exit;
    }
}

/**
 * Alle Benutzer holen (nur für Admin)
 */
function getAllUsers(): array {
    $db = getDB();
    $result = $db->query('SELECT * FROM users ORDER BY created_at DESC');
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    return $users;
}
