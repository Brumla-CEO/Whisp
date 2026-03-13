<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Validators\AuthValidator;
use App\Validators\ChatValidator;
use App\Validators\FriendValidator;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertTrue(AuthValidator::validateLogin((object) ['email' => 'valid@example.com', 'password' => 'Secret123']) === null, 'Login validator should accept valid payload.');
assertTrue(AuthValidator::validateRegister((object) ['username' => 'tester', 'email' => 't@t.t', 'password' => 'Secret123']) === null, 'Register validator should accept valid payload.');
assertTrue(FriendValidator::validateFriendId((object) ['friend_id' => 'uuid']) === null, 'Friend validator should accept friend_id.');
assertTrue(ChatValidator::validateMessagePayload((object) ['room_id' => 1, 'content' => 'Hello']) === null, 'Chat validator should accept valid payload.');

fwrite(STDOUT, "Validator smoke test passed.\n");
