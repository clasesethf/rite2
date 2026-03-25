<?php
/**
 * config.php — RITE v2.0
 * Configuración, conexión BD y helpers
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');
mb_internal_encoding('UTF-8');

define('APP_NAME', 'RITE');
define('APP_VERSION', '2.0');
define('SCHOOL_NAME', 'Escuela Técnica Henry Ford');
define('DB_FILE', __DIR__ . '/database/rite.db');

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 7200);
    session_set_cookie_params(7200);
    session_start();
}

// --- Conexión BD (Singleton) ---
class DB {
    private static ?PDO $conn = null;

    public static function get(): PDO {
        if (!self::$conn) {
            self::$conn = new PDO('sqlite:' . DB_FILE);
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$conn->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;');
        }
        return self::$conn;
    }

    /** Ejecutar query con params, retorna PDOStatement */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch one row */
    public static function row(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    /** Fetch all rows */
    public static function rows(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    /** Insert and return last ID */
    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::get()->lastInsertId();
    }
}

// --- Auth helpers ---
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    $active = activeRole();
    if (!in_array($active, $roles)) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'No tenés permiso para acceder a esa sección.'];
        header('Location: index.php');
        exit;
    }
}

function activeRole(): string {
    return $_SESSION['role_active'] ?? $_SESSION['user_type'] ?? 'estudiante';
}

function isAdmin(): bool {
    return in_array(activeRole(), ['admin', 'directivo']);
}

function userId(): int {
    return $_SESSION['user_id'] ?? 0;
}

function userName(): string {
    return $_SESSION['user_name'] ?? '';
}

function userInitials(): string {
    $parts = explode(' ', userName());
    return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}

// --- Flash messages ---
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// --- Ciclo lectivo activo ---
function cicloActivo(): ?array {
    return DB::row("SELECT * FROM ciclos_lectivos WHERE activo = 1");
}

function cicloId(): int {
    return cicloActivo()['id'] ?? 0;
}
