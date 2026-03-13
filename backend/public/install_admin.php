<?php
declare(strict_types=1);

/**
 * public/install_admin.php
 *
 *  spuštění:
 *   docker exec -it whisp_backend php public/install_admin.php
 *
 */

const WEB_TOKEN = 'install_admin';

require_once __DIR__ . '/../vendor/autoload.php';

function env(string $key, ?string $default = null): ?string {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') return $default;
    return (string)$v;
}

function respond(int $code, array $payload): void {
    if (PHP_SAPI !== 'cli') {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

// ochrana při spuštění přes web
if (PHP_SAPI !== 'cli') {
    $token = $_GET['token'] ?? '';
    if (WEB_TOKEN === '' || !hash_equals(WEB_TOKEN, (string)$token)) {
        respond(403, ['ok' => false, 'error' => 'Forbidden (bad token).']);
    }
}

// ===== DB (Postgres) =====
$DB_HOST = 'db';
$DB_PORT = '5432';
$DB_NAME = 'whisp_db';
$DB_USER = 'whisp_user';
$DB_PASS = 'whisp_password';

// ===== Admin účet =====
$ADMIN_EMAIL    = env('ADMIN_EMAIL', 'a@a.a');
$ADMIN_USERNAME = env('ADMIN_USERNAME', 'admin');
$ADMIN_PASSWORD = env('ADMIN_PASSWORD', 'a');

// chování při existujícím uživateli:
$RESET_PASSWORD_IF_EXISTS = true;

try {
    $dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => 'DB connect failed', 'details' => $e->getMessage()]);
}

try {
    $pdo->beginTransaction();

    // 1) role "admin"
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => 'admin']);
    $role = $stmt->fetch();

    if (!$role) {
        $stmt = $pdo->prepare("
            INSERT INTO roles (name, description)
            VALUES (:name, :description)
            RETURNING id
        ");
        $stmt->execute([
            ':name' => 'admin',
            ':description' => 'Administrator role (seeded)',
        ]);
        $adminRoleId = (string)$stmt->fetchColumn();
    } else {
        $adminRoleId = (string)$role['id'];
    }

    // 2) uživatel podle emailu
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $ADMIN_EMAIL]);
    $user = $stmt->fetch();

    $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);

    if (!$user) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role_id)
            VALUES (:username, :email, :password_hash, :role_id)
            RETURNING id
        ");
        $stmt->execute([
            ':username'      => $ADMIN_USERNAME,
            ':email'         => $ADMIN_EMAIL,
            ':password_hash' => $hash,
            ':role_id'       => $adminRoleId,
        ]);

        $newId = (string)$stmt->fetchColumn();

        $pdo->commit();
        respond(200, [
            'ok' => true,
            'action' => 'inserted',
            'user_id' => $newId,
            'email' => $ADMIN_EMAIL,
            'role_id' => $adminRoleId,
        ]);
    }

    // UPDATE existujícího
    if ($RESET_PASSWORD_IF_EXISTS) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET role_id = :role_id,
                password_hash = :password_hash
            WHERE id = :id
        ");
        $stmt->execute([
            ':role_id' => $adminRoleId,
            ':password_hash' => $hash,
            ':id' => (string)$user['id'],
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET role_id = :role_id
            WHERE id = :id
        ");
        $stmt->execute([
            ':role_id' => $adminRoleId,
            ':id' => (string)$user['id'],
        ]);
    }

    $pdo->commit();
    respond(200, [
        'ok' => true,
        'action' => 'updated',
        'user_id' => (string)$user['id'],
        'email' => $ADMIN_EMAIL,
        'role_id' => $adminRoleId,
        'password_reset' => $RESET_PASSWORD_IF_EXISTS,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(500, ['ok' => false, 'error' => 'Seed failed', 'details' => $e->getMessage()]);
}
