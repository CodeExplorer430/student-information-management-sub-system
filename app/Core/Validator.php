<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, string|list<string>> $rules
     * @return array{0: array<string, list<string>>, 1: array<string, mixed>}
     */
    public function validate(array $input, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $input[$field] ?? null;
            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = 'This field is required.';
                }

                if ($rule === 'email' && $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$field][] = 'Please enter a valid email address.';
                }

                if (str_starts_with((string) $rule, 'in:') && $value !== null && $value !== '') {
                    $options = explode(',', substr(string_value($rule), 3));
                    if (!in_array(string_value($value), $options, true)) {
                        $errors[$field][] = 'Selected value is invalid.';
                    }
                }
            }

            $validated[$field] = is_string($value) ? trim($value) : $value;
        }

        return [$errors, $validated];
    }
}
