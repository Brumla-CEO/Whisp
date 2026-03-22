<?php
declare(strict_types=1);

namespace Tests\Unit\Validators;

use App\Validators\AuthValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit testy pro AuthValidator.
 **/
class AuthValidatorTest extends TestCase
{
    // =========================================================
    // validateLogin() testy
    // =========================================================

    #[Test]
    public function loginValidatesSuccessfullyWithValidData(): void
    {
        // ARRANGE
        $data = (object)[
            'email' => 'test@example.com',
            'password' => 'heslo123',
        ];

        // ACT
        $result = AuthValidator::validateLogin($data);

        // ASSERT
        $this->assertNull($result, 'Validní data by měla projít — výsledek null = OK');
    }

    #[Test]
    public function loginFailsWhenDataIsNull(): void
    {
        $result = AuthValidator::validateLogin(null);

        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    #[Test]
    public function loginFailsWithEmptyEmail(): void
    {
        $data = (object)['email' => '', 'password' => 'heslo123'];

        $result = AuthValidator::validateLogin($data);

        $this->assertNotNull($result, 'Prázdný email musí vrátit chybovou zprávu');
    }

    #[Test]
    public function loginFailsWithEmptyPassword(): void
    {
        $data = (object)['email' => 'test@example.com', 'password' => ''];

        $result = AuthValidator::validateLogin($data);

        $this->assertNotNull($result, 'Prázdné heslo musí vrátit chybovou zprávu');
    }

    #[Test]
    public function loginFailsWhenEmailFieldMissing(): void
    {
        $data = (object)['password' => 'heslo123'];

        $result = AuthValidator::validateLogin($data);

        $this->assertNotNull($result, 'Chybějící email field musí vrátit chybu');
    }

    #[Test]
    public function loginFailsWhenPasswordFieldMissing(): void
    {
        $data = (object)['email' => 'test@example.com'];

        $result = AuthValidator::validateLogin($data);

        $this->assertNotNull($result, 'Chybějící password field musí vrátit chybu');
    }

    #[Test]
    public function loginFailsWithWhitespaceOnlyEmail(): void
    {
        $data = (object)['email' => '   ', 'password' => 'heslo123'];

        $result = AuthValidator::validateLogin($data);

        $this->assertNotNull($result, 'Email složený jen z mezer musí selhat');
    }

    // =========================================================
    // validateRegister() testy
    // =========================================================

    #[Test]
    public function registerValidatesSuccessfullyWithValidData(): void
    {
        $data = (object)[
            'username' => 'brunovaskr',
            'email'    => 'bruno@example.com',
            'password' => 'bezpecne123',
        ];

        $result = AuthValidator::validateRegister($data);

        $this->assertNull($result, 'Validní registrační data by měla projít');
    }

    #[Test]
    public function registerFailsWithInvalidEmailFormat(): void
    {
        $data = (object)[
            'username' => 'brunovaskr',
            'email'    => 'tohle-neni-email',
            'password' => 'heslo123',
        ];

        $result = AuthValidator::validateRegister($data);

        $this->assertNotNull($result, 'Neplatný email formát musí vrátit chybu');
    }

    #[Test]
    public function registerFailsWhenPasswordTooShort(): void
    {
        $data = (object)[
            'username' => 'brunovaskr',
            'email'    => 'bruno@example.com',
            'password' => '12345', // méně než 6 znaků
        ];

        $result = AuthValidator::validateRegister($data);

        $this->assertNotNull($result, 'Heslo kratší než 6 znaků musí selhat');
    }

    #[Test]
    public function registerAcceptsPasswordWithExactlySixChars(): void
    {
        $data = (object)[
            'username' => 'brunovaskr',
            'email'    => 'bruno@example.com',
            'password' => '123456', // přesně 6 znaků — hraniční případ
        ];

        $result = AuthValidator::validateRegister($data);

        $this->assertNull($result, 'Heslo s přesně 6 znaky musí projít (hraniční případ)');
    }

    #[Test]
    public function registerFailsWithEmptyUsername(): void
    {
        $data = (object)[
            'username' => '',
            'email'    => 'bruno@example.com',
            'password' => 'heslo123',
        ];

        $result = AuthValidator::validateRegister($data);

        $this->assertNotNull($result, 'Prázdné uživatelské jméno musí selhat');
    }

    #[Test]
    public function registerFailsWhenDataIsNull(): void
    {
        $result = AuthValidator::validateRegister(null);

        $this->assertNotNull($result);
    }


    #[DataProvider('invalidEmailsProvider')]
    #[Test]
    public function registerFailsWithVariousInvalidEmails(string $invalidEmail): void
    {
        $data = (object)[
            'username' => 'user',
            'email'    => $invalidEmail,
            'password' => 'heslo123',
        ];

        $result = AuthValidator::validateRegister($data);

        $this->assertNotNull($result, "Email '{$invalidEmail}' měl být odmítnut");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmailsProvider(): array
    {
        return [
            'bez zavinace'       => ['neni-email'],
            'bez domeny'         => ['user@'],
            'bez lokalniho'      => ['@example.com'],
            'double @'           => ['user@@example.com'],
            'mezera v emailu'    => ['us er@example.com'],
            'prazdny string'     => [''],
        ];
    }
}
