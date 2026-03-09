<?php
// ============================================================
// login.php — User Login (all roles)
// Place this file in: /before-i-grow-up/login.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Login';
$base = BASE_PATH;
$error = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect($base . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = normalizeEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Use your Gmail address ending with @gmail.com.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session for security
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['email']   = $user['email'];

            setFlash('success', 'Welcome back, ' . $user['name'] . '! 👋');

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':     redirect($base . '/admin/dashboard.php'); break;
                case 'guardian':  redirect($base . '/guardian/my_dreams.php'); break;
                case 'supporter': redirect($base . '/supporter/browse_dreams.php'); break;
                default:          redirect($base . '/index.php');
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-page">
    <div class="form-card">
        <div class="text-center mb-3">
            <span style="font-size:2.5rem;">🌱</span>
        </div>
        <h1 class="text-center">Welcome Back</h1>
        <p class="form-sub text-center">Sign in to your account to continue</p>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom:1.25rem;">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'login_required'): ?>
            <div class="flash flash-info" style="margin-bottom:1.25rem;">
                <span>Please log in to access that page.</span>
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
                    placeholder="you@gmail.com"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    pattern="^[A-Za-z0-9._%+-]+@gmail\\.com$"
                    title="Enter your Gmail address ending with @gmail.com"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Your password"
                    required
                >
            </div>

            <div style="text-align:right;margin-top:.15rem;margin-bottom:.4rem;">
                <a href="<?= $base ?>/forgot_password.php" style="font-size:.85rem;">Forgot your password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:.5rem;">
                Sign In →
            </button>
        </form>

        <div class="form-divider">— or —</div>

        <p class="text-center" style="font-size:.9rem;">
            Don't have an account? <a href="<?= $base ?>/register.php" style="font-weight:600;">Register here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
