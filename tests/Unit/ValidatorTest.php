<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidationReturnsErrorsForMissingRequiredFieldsAndInvalidEmail(): void
    {
        $validator = new Validator();

        [$errors] = $validator->validate([
            'first_name' => '',
            'email' => 'not-an-email',
        ], [
            'first_name' => 'required',
            'email' => ['required', 'email'],
        ]);

        self::assertArrayHasKey('first_name', $errors);
        self::assertArrayHasKey('email', $errors);
    }
}
