<?php
declare(strict_types=1);

namespace Tests\Unit\Validators;

use App\Validators\FriendValidator;
use App\Validators\ChatValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit testy pro FriendValidator a ChatValidator.
 */
class FriendAndChatValidatorTest extends TestCase
{
    // =========================================================
    // FriendValidator testy
    // =========================================================

    #[Test]
    public function validateTargetIdPassesWithValidId(): void
    {
        $data = (object)['target_id' => 'some-uuid-string'];

        $result = FriendValidator::validateTargetId($data);

        $this->assertNull($result, 'Validní target_id musí projít');
    }

    #[Test]
    public function validateTargetIdFailsWhenMissing(): void
    {
        $data = (object)[];

        $result = FriendValidator::validateTargetId($data);

        $this->assertNotNull($result, 'Chybějící target_id musí vrátit chybu');
    }

    #[Test]
    public function validateTargetIdFailsWithEmptyString(): void
    {
        $data = (object)['target_id' => ''];

        $result = FriendValidator::validateTargetId($data);

        $this->assertNotNull($result, 'Prázdný target_id musí vrátit chybu');
    }

    #[Test]
    public function validateTargetIdFailsWithWhitespaceOnly(): void
    {
        $data = (object)['target_id' => '   '];

        $result = FriendValidator::validateTargetId($data);

        $this->assertNotNull($result);
    }

    #[Test]
    public function validateRequestIdPassesWithValidId(): void
    {
        $data = (object)['request_id' => '42'];

        $result = FriendValidator::validateRequestId($data);

        $this->assertNull($result);
    }

    #[Test]
    public function validateRequestIdFailsWhenNull(): void
    {
        $result = FriendValidator::validateRequestId(null);

        $this->assertNotNull($result);
    }

    #[Test]
    public function sanitizeSearchQueryTrimsWhitespace(): void
    {
        $result = FriendValidator::sanitizeSearchQuery('  bruno  ');

        $this->assertSame('bruno', $result, 'Mezery na okrajích musí být odstraněny');
    }

    #[Test]
    public function sanitizeSearchQueryHandlesNullInput(): void
    {
        $result = FriendValidator::sanitizeSearchQuery(null);

        $this->assertIsString($result, 'Null vstup musí vrátit string');
        $this->assertSame('', $result, 'Null vstup musí vrátit prázdný string');
    }

    #[Test]
    public function sanitizeSearchQueryPreservesValidQuery(): void
    {
        $result = FriendValidator::sanitizeSearchQuery('bruno');

        $this->assertSame('bruno', $result);
    }

    // =========================================================
    // ChatValidator testy
    // =========================================================

    #[Test]
    public function validateMessagePayloadPassesWithValidData(): void
    {
        $data = (object)[
            'room_id' => 5,
            'content' => 'Ahoj světe!',
        ];

        $result = ChatValidator::validateMessagePayload($data);

        $this->assertNull($result);
    }

    #[Test]
    public function validateMessagePayloadFailsWithEmptyContent(): void
    {
        $data = (object)[
            'room_id' => 5,
            'content' => '',
        ];

        $result = ChatValidator::validateMessagePayload($data);

        $this->assertNotNull($result, 'Prázdný obsah zprávy musí selhat');
    }

    #[Test]
    public function validateMessagePayloadFailsWithWhitespaceContent(): void
    {
        $data = (object)[
            'room_id' => 5,
            'content' => '   ',
        ];

        $result = ChatValidator::validateMessagePayload($data);

        $this->assertNotNull($result, 'Obsah zprávy složený jen z mezer musí selhat');
    }

    #[Test]
    public function validateMessagePayloadFailsWhenRoomIdMissing(): void
    {
        $data = (object)['content' => 'Ahoj!'];

        $result = ChatValidator::validateMessagePayload($data);

        $this->assertNotNull($result, 'Chybějící room_id musí selhat');
    }

    #[Test]
    public function validateMessagePayloadFailsWhenNull(): void
    {
        $result = ChatValidator::validateMessagePayload(null);

        $this->assertNotNull($result);
    }

    #[Test]
    public function validateGroupCreationPassesWithValidData(): void
    {
        $data = (object)[
            'name'    => 'Moje skupina',
            'members' => ['uuid-1', 'uuid-2'], // 2 přátelé + zakladatel = 3
        ];

        $result = ChatValidator::validateGroupCreation($data);

        $this->assertNull($result, 'Validní data skupiny musí projít');
    }

    #[Test]
    public function validateGroupCreationFailsWithOnlyOneMember(): void
    {
        $data = (object)[
            'name'    => 'Moje skupina',
            'members' => ['uuid-1'], // jen 1 přítel = celkem 2 lidi, nestačí
        ];

        $result = ChatValidator::validateGroupCreation($data);

        $this->assertNotNull($result, 'Skupina s méně než 2 přáteli musí selhat');
    }

    #[Test]
    public function validateGroupCreationFailsWithEmptyName(): void
    {
        $data = (object)[
            'name'    => '',
            'members' => ['uuid-1', 'uuid-2'],
        ];

        $result = ChatValidator::validateGroupCreation($data);

        $this->assertNotNull($result, 'Prázdný název skupiny musí selhat');
    }

    #[Test]
    public function validateGroupCreationFailsWhenMembersNotArray(): void
    {
        $data = (object)[
            'name'    => 'Skupina',
            'members' => 'not-an-array',
        ];

        $result = ChatValidator::validateGroupCreation($data);

        $this->assertNotNull($result, 'Members musí být pole');
    }
}
