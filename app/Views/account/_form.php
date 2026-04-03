<?php

/** @var array<string, mixed> $userAccount */
/** @var ValidationErrors $errors */
/** @var array<string, mixed> $old */
/** @var string $submitLabel */

$userAccount = map_value($userAccount ?? []);
$errors = is_array($errors ?? null) ? $errors : [];
$old = map_value($old ?? []);
$submitLabel = string_value($submitLabel ?? 'Save account details', 'Save account details');
$avatarData = private_upload_data_uri(nullable_string_value($userAccount['photo_path'] ?? null));
$accountName = string_value($userAccount['name'] ?? 'User', 'User');
?>
<div class="account-form-layout">
    <aside class="account-profile-card">
        <div class="section-pill">Profile</div>
        <div class="account-profile-card__avatar">
            <?php if ($avatarData !== ''): ?>
                <img src="<?= e($avatarData) ?>" alt="<?= e($accountName . ' avatar') ?>">
            <?php else: ?>
                <?= e(user_initials($accountName)) ?>
            <?php endif; ?>
        </div>
        <h3><?= e($accountName) ?></h3>
        <p><?= e($userAccount['email'] ?? '') ?></p>
        <div class="small text-muted">Upload JPG, PNG, or WEBP up to 5MB.</div>
    </aside>

    <div class="account-form-fields">
        <div class="form-grid">
            <div class="form-field">
                <label for="name">Full name</label>
                <input id="name" name="name" value="<?= e(old_input($old, 'name', $userAccount['name'] ?? '')) ?>" required>
                <?php if (($error = first_error($errors, 'name')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= e(old_input($old, 'email', $userAccount['email'] ?? '')) ?>" required>
                <?php if (($error = first_error($errors, 'email')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="mobile_phone">Contact number</label>
                <input id="mobile_phone" name="mobile_phone" value="<?= e(old_input($old, 'mobile_phone', $userAccount['mobile_phone'] ?? '')) ?>" placeholder="0917XXXXXXX">
                <?php if (($error = first_error($errors, 'mobile_phone')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="department">Department</label>
                <input id="department" name="department" value="<?= e(old_input($old, 'department', $userAccount['department'] ?? '')) ?>" placeholder="ICT">
                <?php if (($error = first_error($errors, 'department')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-field form-field--full">
                <label for="photo">Profile image</label>
                <input id="photo" type="file" name="photo" accept="image/png,image/jpeg,image/webp">
                <?php if (($error = first_error($errors, 'photo')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions mt-4">
            <button class="btn btn-primary">
                <i class="fas fa-user-pen"></i>
                <span><?= e($submitLabel) ?></span>
            </button>
        </div>
    </div>
</div>
