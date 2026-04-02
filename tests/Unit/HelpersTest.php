<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEnvReadsStringLikeSourcesAndFallsBackToDefault(): void
    {
        putenv('SIMS_HELPER_ENV');
        unset($_ENV['SIMS_HELPER_ENV'], $_SERVER['SIMS_HELPER_ENV']);

        self::assertSame('fallback', env('SIMS_HELPER_ENV', 'fallback'));

        $_ENV['SIMS_HELPER_ENV'] = 'from-env';
        self::assertSame('from-env', env('SIMS_HELPER_ENV', 'fallback'));

        unset($_ENV['SIMS_HELPER_ENV']);
        $_SERVER['SIMS_HELPER_ENV'] = 42;
        self::assertSame('42', env('SIMS_HELPER_ENV', 'fallback'));

        $_SERVER['SIMS_HELPER_ENV'] = ['invalid'];
        self::assertSame('fallback', env('SIMS_HELPER_ENV', 'fallback'));

        unset($_SERVER['SIMS_HELPER_ENV']);
    }

    public function testScalarAndNullableHelpersNormalizeValues(): void
    {
        self::assertSame('&lt;unsafe&gt;', e('<unsafe>'));
        self::assertSame('15', string_value(15));
        self::assertSame('fallback', string_value([], 'fallback'));
        self::assertNull(nullable_string_value(null));
        self::assertNull(nullable_string_value(new \stdClass()));
        self::assertSame('1', nullable_string_value(true));
        self::assertSame(12, int_value(12.9));
        self::assertSame(12, int_value('12'));
        self::assertSame(9, int_value([], 9));
        self::assertTrue(bool_value('true'));
        self::assertFalse(bool_value([], false));
        self::assertTrue(bool_value('not-bool', true));
    }

    public function testArrayMappingHelpersNormalizeRowsAndStrings(): void
    {
        $mapped = map_value([1 => 'one', 'two' => 2]);
        $rows = rows_value([
            ['id' => 1, 'name' => 'A'],
            'skip-me',
            ['id' => '2'],
        ]);
        $strings = strings_value([' one ', '', 'two', 'one', 7]);

        self::assertSame(['1' => 'one', 'two' => 2], $mapped);
        self::assertSame([], map_value('not-an-array'));
        self::assertCount(2, $rows);
        self::assertSame([], rows_value('not-an-array'));
        self::assertSame('A', map_string($rows[0], 'name'));
        self::assertSame(2, map_int($rows[1], 'id'));
        self::assertSame(['one', 'two'], $strings);
        self::assertSame([], strings_value('not-an-array'));
    }

    public function testUploadedFileAndFormHelpersHandleEdgeCases(): void
    {
        $file = uploaded_file_value([
            'name' => ' proof.pdf ',
            'tmp_name' => '/tmp/upload',
            'error' => '0',
            'size' => false,
        ]);
        $errors = [
            'email' => ['Please enter a valid email address.'],
            'phone' => ['Primary', 'Secondary'],
        ];

        self::assertSame([
            'name' => ' proof.pdf ',
            'tmp_name' => '/tmp/upload',
            'error' => 0,
            'size' => false,
        ], $file);
        self::assertSame([], uploaded_file_value('not-an-array'));
        self::assertSame('Please enter a valid email address.', first_error($errors, 'email'));
        self::assertNull(first_error($errors, 'missing'));
        self::assertSame('old-value', old_input(['field' => 'old-value'], 'field', 'default'));
        self::assertSame('default', old_input([], 'field', 'default'));
        self::assertSame('selected', selected('A', 'A'));
        self::assertSame('', selected('A', 'B'));
        self::assertSame('checked', checked(true));
        self::assertSame('', checked(false));
    }

    public function testPresentationHelpersFormatStatusesAndNames(): void
    {
        self::assertSame('under-review', status_slug('Under Review'));
        self::assertSame(100, workflow_progress('Completed'));
        self::assertSame(75, workflow_progress('Rejected'));
        self::assertSame(25, workflow_progress('Anything Else'));
        self::assertSame('Status Transition', action_label('status_transition'));
        self::assertSame('JD', user_initials('Jane Doe'));
        self::assertSame('U', user_initials('   '));
    }
}
