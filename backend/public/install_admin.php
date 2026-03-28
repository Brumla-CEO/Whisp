<?php
declare(strict_types=1);

/**
 * public/install_admin.php
 *
 * Spuštění:
 *   docker exec -it whisp_backend php public/install_admin.php
 *   http://localhost:8000/install_admin.php?token=install_admin
 */

const WEB_TOKEN = 'install_admin';

require_once __DIR__ . '/../vendor/autoload.php';

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function respond(int $code, array $payload): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

// Ochrana při spuštění přes web.
if (PHP_SAPI !== 'cli') {
    $token = (string) ($_GET['token'] ?? '');

    if (WEB_TOKEN === '' || !hash_equals(WEB_TOKEN, $token)) {
        respond(403, ['ok' => false, 'error' => 'Forbidden (bad token).']);
    }
}

// ===== DB (Postgres) =====
$dbHost = 'db';
$dbPort = '5432';
$dbName = 'whisp_db';
$dbUser = 'whisp_user';
$dbPass = 'whisp_password';

// ===== Admin účet =====
$adminEmail = env('ADMIN_EMAIL', 'admin@a.a');
$adminUsername = env('ADMIN_USERNAME', 'admin');
$adminPassword = env('ADMIN_PASSWORD', 'admin123');

// Chování při existujícím uživateli.
$resetPasswordIfExists = true;

try {
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    respond(500, [
        'ok' => false,
        'error' => 'DB connect failed',
        'details' => $e->getMessage(),
    ]);
}

try {
    $pdo->beginTransaction();

    // 1) Role "admin"
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => 'admin']);
    $role = $stmt->fetch();

    if (!$role) {
        $stmt = $pdo->prepare(
            'INSERT INTO roles (name, description)
             VALUES (:name, :description)
             RETURNING id'
        );
        $stmt->execute([
            ':name' => 'admin',
            ':description' => 'Administrator role (seeded)',
        ]);
        $adminRoleId = (string) $stmt->fetchColumn();
    } else {
        $adminRoleId = (string) $role['id'];
    }

    // 2) Uživatel podle emailu
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $adminEmail]);
    $user = $stmt->fetch();

    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);

    if (!$user) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role_id)
             VALUES (:username, :email, :password_hash, :role_id)
             RETURNING id'
        );
        $stmt->execute([
            ':username' => $adminUsername,
            ':email' => $adminEmail,
            ':password_hash' => $hash,
            ':role_id' => $adminRoleId,
        ]);

        $newId = (string) $stmt->fetchColumn();

        $pdo->commit();
        respond(200, [
            'ok' => true,
            'action' => 'inserted',
            'user_id' => $newId,
            'email' => $adminEmail,
            'role_id' => $adminRoleId,
        ]);
    }

    if ($resetPasswordIfExists) {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET role_id = :role_id,
                 password_hash = :password_hash
             WHERE id = :id'
        );
        $stmt->execute([
            ':role_id' => $adminRoleId,
            ':password_hash' => $hash,
            ':id' => (string) $user['id'],
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET role_id = :role_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':role_id' => $adminRoleId,
            ':id' => (string) $user['id'],
        ]);
    }

    $pdo->commit();
    respond(200, [
        'ok' => true,
        'action' => 'updated',
        'user_id' => (string) $user['id'],
        'email' => $adminEmail,
        'role_id' => $adminRoleId,
        'password_reset' => $resetPasswordIfExists,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond(500, [
        'ok' => false,
        'error' => 'Seed failed',
        'details' => $e->getMessage(),
    ]);
}


