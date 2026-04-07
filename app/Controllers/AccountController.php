<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AccountService;
use RuntimeException;

final class AccountController
{
    public function __construct(
        private readonly Response $response,
        private readonly AccountService $accounts,
        private readonly Validator $validator,
        private readonly Auth $auth
    ) {
    }

    public function show(): void
    {
        $user = $this->currentUser();

        $this->response->view('account/show', [
            'userAccount' => $user,
        ]);
    }

    public function update(): void
    {
        $user = $this->currentUser();
        [$errors, $data] = $this->validator->validate(map_value($_POST), $this->rules());
        $files = uploaded_file_value($_FILES['photo'] ?? null);

        if ($errors !== []) {
            $this->renderForm($this->mergeUserFormData($user), $errors, 422);
        }

        $payload = [
            'name' => string_value($data['name'] ?? ''),
            'email' => string_value($data['email'] ?? ''),
            'mobile_phone' => nullable_string_value($_POST['mobile_phone'] ?? null),
            'department' => nullable_string_value($_POST['department'] ?? null),
        ];

        try {
            $this->accounts->update((int) $user['id'], $payload, $files !== [] ? ['photo' => $files] : []);
        } catch (RuntimeException $exception) {
            $this->renderForm($this->mergeUserFormData($user), $this->errorsForException($exception), 422);
        }

        $this->response->redirect('/account', 'Account details updated successfully.');
    }

    /**
     * @return ValidationRules
     */
    private function rules(): array
    {
        return [
            'name' => 'required',
            'email' => ['required', 'email'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param ValidationErrors $errors
     */
    private function renderForm(array $user, array $errors, int $status = 422): never
    {
        $_SESSION['_old'] = $_POST;
        $this->response->view('account/show', [
            'userAccount' => $user,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @return ValidationErrors
     */
    private function errorsForException(RuntimeException $exception): array
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'email address')) {
            return ['email' => [$message]];
        }

        if (str_contains($message, 'Photo') || str_contains($message, 'JPG') || str_contains($message, 'PNG') || str_contains($message, 'WEBP')) {
            return ['photo' => [$message]];
        }

        return ['name' => [$message]];
    }

    /**
     * @param UserRow $user
     * @return array<string, mixed>
     */
    private function mergeUserFormData(array $user): array
    {
        $merged = $user;

        foreach (map_value($_POST) as $key => $value) {
            if (is_string($key)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @return UserRow
     */
    private function currentUser(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->response->redirect('/login', 'Please sign in first.', 'error');
        }

        return $user;
    }
}
