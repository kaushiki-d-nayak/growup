<?php
// forgot_password.php — Request password reset link

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mail.php';

$pageTitle = 'Forgot Password';
$base      = BASE_PATH;
$error     = '';

// If already logged in, send back home
if (isLoggedIn()) {
    redirect($base . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } else {
        $db = getDB();

        // Create password_resets table if it doesn't exist
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

        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token    = bin2hex(random_bytes(32));
            $expires  = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $ins      = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$user['id'], $token, $expires]);

            $resetUrl = appUrl('reset_password.php?token=' . urlencode($token));
            $subject  = 'Reset your password';
            $body     = '<p>Hi ' . e($user['name']) . ',</p>'
                      . '<p>We received a request to reset the password for your account on <strong>' . APP_NAME . '</strong>.</p>'
                      . '<p>You can set a new password by clicking this link:</p>'
                      . '<p><a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p>'
                      . '<p>This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>'
                      . '<p>With care,<br>' . APP_NAME . ' team</p>';

            sendEmail($email, $subject, $body);
        }

        // Always show generic message for security
        setFlash('success', 'If that email address is registered, we\'ve sent a password reset link.');
        redirect($base . '/login.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-page">
    <div class="form-card">
        <div class="text-center mb-3">
            <span style="font-size:2.2rem;">🔑</span>
        </div>
        <h1 class="text-center">Forgot your password?</h1>
        <p class="form-sub text-center">Enter your email and we&rsquo;ll send you a reset link.</p>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom:1.25rem;">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@example.com"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:.5rem;">
                Send Reset Link →
            </button>
        </form>

        <p class="text-center" style="margin-top:1.25rem;font-size:.9rem;">
            Remembered your password? <a href="<?= $base ?>/login.php" style="font-weight:600;">Back to login</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

