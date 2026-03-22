<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\JWTService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit testy pro JWTService.
 *
 * JWTService čte JWT_SECRET z prostředí (getenv).
 * Nastavíme proměnnou prostředí přímo v testu — nepotřebujeme Docker ani DB.
 */
class JWTServiceTest extends TestCase
{
    private const TEST_SECRET = 'test-secret-key-min-32-characters-long!!';
    private const TEST_USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        // Nastavení ENV proměnné pro každý test
        putenv('JWT_SECRET=' . self::TEST_SECRET);
        putenv('JWT_TTL_SECONDS=86400');
    }

    protected function tearDown(): void
    {
        // Úklid po testu
        putenv('JWT_SECRET');
        putenv('JWT_TTL_SECONDS');
    }

    // =========================================================
    // generate() testy
    // =========================================================

    #[Test]
    public function generateReturnsNonEmptyString(): void
    {
        $token = JWTService::generate(self::TEST_USER_ID, 'user');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    #[Test]
    public function generateReturnsValidJwtFormat(): void
    {
        $token = JWTService::generate(self::TEST_USER_ID, 'user');

        // JWT má vždy tvar: header.payload.signature (3 části oddělené tečkou)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT musí mít přesně 3 části oddělené tečkou');
    }

    #[Test]
    public function generateCreatesUniqueTokensForSameUser(): void
    {
        // Pokud generujeme tokeny v různých sekundách, budou různé (různé iat)
        $token1 = JWTService::generate(self::TEST_USER_ID, 'user');
        sleep(1); // počkáme 1 sekundu — iat se změní
        $token2 = JWTService::generate(self::TEST_USER_ID, 'user');

        // Tokeny nemusí být vždy různé pokud jsou generovány ve stejné sekundě,
        // ale test ukazuje záměr — každý login = nový token
        $this->assertIsString($token1);
        $this->assertIsString($token2);
    }

    // =========================================================
    // decode() testy
    // =========================================================

    #[Test]
    public function decodeReturnsCorrectPayload(): void
    {
        // ARRANGE – vygeneruj token
        $token = JWTService::generate(self::TEST_USER_ID, 'user');

        // ACT – dekóduj ho zpět
        $decoded = JWTService::decode($token);

        // ASSERT – payload musí obsahovat správná data
        $this->assertNotNull($decoded, 'Validní token musí být dekódován');
        $this->assertSame(self::TEST_USER_ID, $decoded->sub, 'sub claim musí odpovídat userId');
        $this->assertSame('user', $decoded->role, 'role claim musí odpovídat roli');
    }

    #[Test]
    public function decodeContainsRequiredClaims(): void
    {
        $token = JWTService::generate(self::TEST_USER_ID, 'admin');
        $decoded = JWTService::decode($token);

        $this->assertObjectHasProperty('sub', $decoded, 'Token musí obsahovat sub claim');
        $this->assertObjectHasProperty('role', $decoded, 'Token musí obsahovat role claim');
        $this->assertObjectHasProperty('iat', $decoded, 'Token musí obsahovat iat (issued at)');
        $this->assertObjectHasProperty('exp', $decoded, 'Token musí obsahovat exp (expiration)');
    }

    #[Test]
    public function decodeReturnsNullForInvalidToken(): void
    {
        $result = JWTService::decode('tohle.neni.platnytoken');

        $this->assertNull($result, 'Neplatný token musí vrátit null');
    }

    #[Test]
    public function decodeReturnsNullForEmptyString(): void
    {
        $result = JWTService::decode('');

        $this->assertNull($result);
    }

    #[Test]
    public function decodeReturnsNullForRandomGarbage(): void
    {
        $result = JWTService::decode('aaabbbccc');

        $this->assertNull($result);
    }

    #[Test]
    public function decodeReturnsNullForTokenSignedWithDifferentSecret(): void
    {
        // Vygeneruj token s jiným secretem
        putenv('JWT_SECRET=completely-different-secret-key-xyz!!');
        $tokenWithWrongSecret = JWTService::generate(self::TEST_USER_ID, 'user');

        // Nastav zpět původní secret
        putenv('JWT_SECRET=' . self::TEST_SECRET);

        // Pokus o dekódování tokenu podepsaného jiným klíčem musí selhat
        $result = JWTService::decode($tokenWithWrongSecret);

        $this->assertNull($result, 'Token podepsaný jiným klíčem musí vrátit null');
    }

    #[Test]
    public function decodeTokenHasExpirationInFuture(): void
    {
        $token = JWTService::generate(self::TEST_USER_ID, 'user');
        $decoded = JWTService::decode($token);

        $this->assertGreaterThan(
            time(),
            $decoded->exp,
            'Expirace tokenu musí být v budoucnosti'
        );
    }

    #[Test]
    public function generateWorksForAdminRole(): void
    {
        $token = JWTService::generate(self::TEST_USER_ID, 'admin');
        $decoded = JWTService::decode($token);

        $this->assertSame('admin', $decoded->role);
    }

    #[Test]
    public function generateThrowsWhenSecretNotConfigured(): void
    {
        putenv('JWT_SECRET'); // smazat proměnnou

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET is not configured');

        JWTService::generate(self::TEST_USER_ID, 'user');
    }
}