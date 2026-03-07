<?php
// reset_password.php — Set a new password from reset link

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Reset Password';
$base      = BASE_PATH;
$error     = '';
$token     = $_GET['token'] ?? ($_POST['token'] ?? '');
$token     = is_string($token) ? trim($token) : '';

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $invalid = true;
} else {
    $db = getDB();
    // Ensure table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX token_idx (token),
            INDEX user_idx (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmt = $db->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email, u.name
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    $invalid = !$reset || $reset['used'] || strtotime($reset['expires_at']) < time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalid) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $db = getDB();
        $db->beginTransaction();
        try {
            $upUser = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upUser->execute([$hash, $reset['user_id']]);

            $upReset = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $upReset->execute([$reset['id']]);

            $db->commit();
            setFlash('success', 'Your password has been updated. You can now log in.');
            redirect($base . '/login.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Unable to reset password right now. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-page">
    <div class="form-card">
        <div class="text-center mb-3">
            <span style="font-size:2.2rem;">🔒</span>
        </div>

        <?php if ($invalid): ?>
            <h1 class="text-center">Reset link not valid</h1>
            <p class="form-sub text-center">
                This password reset link is invalid or has expired.
            </p>
            <p class="text-center" style="margin-top:1rem;font-size:.9rem;">
                You can request a new link from the
                <a href="<?= $base ?>/forgot_password.php" style="font-weight:600;">Forgot Password</a> page.
            </p>
        <?php else: ?>
            <h1 class="text-center">Set a new password</h1>
            <p class="form-sub text-center">
                Choose a strong password you don&rsquo;t use anywhere else.
            </p>

            <?php if ($error): ?>
                <div class="flash flash-error" style="margin-bottom:1.25rem;">
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        minlength="8"
                        required
                    >
                    <span class="form-hint">Minimum 8 characters.</span>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        class="form-control"
                        minlength="8"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100" style="margin-top:.5rem;">
                    Update Password →
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

