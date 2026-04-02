<?php

/** @var array<string, mixed> $old */

$student = map_value($student ?? []);
/** @var ValidationErrors $errors */
$errors = is_array($errors ?? null) ? $errors : [];
$old = map_value($old ?? []);
?>
<div class="form-grid">
    <div class="form-field">
        <label for="first_name">First name</label>
        <input id="first_name" name="first_name" value="<?= e(old_input($old, 'first_name', $student['first_name'] ?? '')) ?>" required>
        <?php if (($error = first_error($errors, 'first_name')) !== null): ?>
            <div class="field-error"><?= e($error) ?></div>
        <?php endif; ?>
    </div>
    <div class="form-field">
        <label for="middle_name">Middle name</label>
        <input id="middle_name" name="middle_name" value="<?= e(old_input($old, 'middle_name', $student['middle_name'] ?? '')) ?>">
    </div>
    <div class="form-field">
        <label for="last_name">Last name</label>
        <input id="last_name" name="last_name" value="<?= e(old_input($old, 'last_name', $student['last_name'] ?? '')) ?>" required>
        <?php if (($error = first_error($errors, 'last_name')) !== null): ?>
            <div class="field-error"><?= e($error) ?></div>
        <?php endif; ?>
    </div>
    <div class="form-field">
        <label for="birthdate">Birthdate</label>
        <input id="birthdate" type="date" name="birthdate" value="<?= e(old_input($old, 'birthdate', $student['birthdate'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="program">Program / Course</label>
        <input id="program" name="program" value="<?= e(old_input($old, 'program', $student['program'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="year_level">Year level</label>
        <input id="year_level" name="year_level" value="<?= e(old_input($old, 'year_level', $student['year_level'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="department">Department</label>
        <input id="department" name="department" value="<?= e(old_input($old, 'department', $student['department'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= e(old_input($old, 'email', $student['email'] ?? '')) ?>" required>
        <?php if (($error = first_error($errors, 'email')) !== null): ?>
            <div class="field-error"><?= e($error) ?></div>
        <?php endif; ?>
    </div>
    <div class="form-field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e(old_input($old, 'phone', $student['phone'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="guardian_name">Guardian name</label>
        <input id="guardian_name" name="guardian_name" value="<?= e(old_input($old, 'guardian_name', $student['guardian_name'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="guardian_contact">Guardian contact</label>
        <input id="guardian_contact" name="guardian_contact" value="<?= e(old_input($old, 'guardian_contact', $student['guardian_contact'] ?? '')) ?>" required>
    </div>
    <div class="form-field">
        <label for="photo">Photo upload</label>
        <input id="photo" type="file" name="photo" accept="image/png,image/jpeg,image/webp">
        <?php if (($error = first_error($errors, 'photo')) !== null): ?>
            <div class="field-error"><?= e($error) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="form-field form-field--full">
    <label for="address">Address</label>
    <textarea id="address" name="address" rows="4" required><?= e(old_input($old, 'address', $student['address'] ?? '')) ?></textarea>
</div>
