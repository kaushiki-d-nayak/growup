<?php
// ============================================================
// register.php — User Registration (guardian or supporter)
// Place this file in: /before-i-grow-up/register.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mail.php';

$pageTitle = 'Register';
$base = BASE_PATH;
$errors = [];
$old = [];

if (isLoggedIn()) {
    redirect($base . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $name     = trim($_POST['name']     ?? '');
    $email    = normalizeEmail($_POST['email'] ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $role     = $_POST['role']          ?? '';

    // Supporter extra fields
    $profession   = trim($_POST['profession']   ?? '');
    $interestArea = trim($_POST['interest_area'] ?? '');

    // Validation
    if (empty($name))     $errors[] = 'Full name is required.';
    if (strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Enter a valid Gmail address ending with @gmail.com.';
    }
    if (empty($password)) $errors[] = 'Password is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['guardian', 'supporter'])) $errors[] = 'Please select a valid role.';

    if (empty($errors)) {
        $db = getDB();

        // Check email uniqueness
        $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->beginTransaction();
            try {
                $ins = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $ins->execute([$name, $email, $hashed, $role]);
                $userId = $db->lastInsertId();

                // If supporter, create supporter profile
                if ($role === 'supporter') {
                    $sp = $db->prepare("INSERT INTO supporters (user_id, profession, interest_area) VALUES (?, ?, ?)");
                    $sp->execute([$userId, $profession, $interestArea]);
                }

                $db->commit();

                // Send welcome email (non-blocking)
                $roleLabel = $role === 'guardian' ? 'Guardian' : 'Supporter';
                $subject = 'Welcome to ' . APP_NAME;
                $body = '<p>Hi ' . e($name) . ',</p>'
                      . '<p>Your <strong>' . e($roleLabel) . '</strong> account has been created successfully.</p>'
                      . '<p>We are glad to have you with us on <strong>' . APP_NAME . '</strong>.</p>';
                if ($role === 'guardian') {
                    $body .= '<p>You can now submit a student dream from your dashboard.</p>';
                } else {
                    $body .= '<p>You can now browse open dreams and apply to support them.</p>';
                }
                $body .= '<p>With care,<br>' . APP_NAME . ' team</p>';
                if (!sendEmail($email, $subject, $body)) {
                    error_log('Welcome email failed for user: ' . $email);
                }

                // Auto-login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['name']    = $name;
                $_SESSION['role']    = $role;
                $_SESSION['email']   = $email;

                setFlash('success', 'Account created! Welcome to Before I Grow Up, ' . $name . ' 🎉');

                if ($role === 'guardian')  redirect($base . '/guardian/submit_dream.php');
                if ($role === 'supporter') redirect($base . '/supporter/browse_dreams.php');

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-page" style="align-items:flex-start; padding-top:4rem;">
    <div class="form-card form-wide" style="max-width:600px;">
        <div class="text-center mb-3">
            <span style="font-size:2.5rem;">🌱</span>
        </div>
        <h1 class="text-center">Create Account</h1>
        <p class="form-sub text-center">Join our community of dreamers and supporters</p>

        <?php if (!empty($errors)): ?>
            <div class="flash flash-error" style="margin-bottom:1.25rem;flex-direction:column;align-items:flex-start;gap:.3rem;">
                <?php foreach ($errors as $err): ?>
                    <span>• <?= e($err) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="regForm">
            <div class="form-group">
                <label for="role">I am a…</label>
                <select name="role" id="role" class="form-control" required onchange="toggleSupporterFields(this.value)">
                    <option value="">— Select your role —</option>
                    <option value="guardian"  <?= ($old['role'] ?? '') === 'guardian'  ? 'selected' : '' ?>>👨‍👩‍👧 Guardian / Teacher</option>
                    <option value="supporter" <?= ($old['role'] ?? '') === 'supporter' ? 'selected' : '' ?>>💛 Supporter (Mentor / Sponsor)</option>
                </select>
                <span class="form-hint">Admin accounts are created internally only.</span>
            </div>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control"
                    placeholder="Your full name"
                    value="<?= e($old['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                    placeholder="you@gmail.com"
                    value="<?= e($old['email'] ?? '') ?>"
                    maxlength="254"
                    inputmode="email"
                    autocomplete="email"
                    pattern="^[A-Za-z0-9._%+-]+@gmail\\.com$"
                    title="Enter a Gmail address ending with @gmail.com"
                    required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Min. 8 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" class="form-control"
                        placeholder="Repeat password" required>
                </div>
            </div>

            <!-- Supporter extra fields -->
            <div id="supporterFields" style="display:<?= ($old['role'] ?? '') === 'supporter' ? 'block' : 'none' ?>;">
                <div class="form-group">
                    <label for="profession">Your Profession</label>
                    <input type="text" id="profession" name="profession" class="form-control"
                        placeholder="e.g. Software Engineer, Teacher, Artist"
                        value="<?= e($old['profession'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="interest_area">Area of Interest to Support</label>
                    <select id="interest_area" name="interest_area" class="form-control">
                        <option value="">— Choose a category —</option>
                        <?php
                        $cats = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support',
                                 'Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];
                        $cats[] = 'Provide Money / Sponsorship';
                        foreach ($cats as $cat):
                            $sel = ($old['interest_area'] ?? '') === $cat ? 'selected' : '';
                        ?>
                            <option value="<?= e($cat) ?>" <?= $sel ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:.5rem;">
                Create Account →
            </button>
        </form>

        <div class="form-divider">— or —</div>
        <p class="text-center" style="font-size:.9rem;">
            Already have an account? <a href="<?= $base ?>/login.php" style="font-weight:600;">Sign in</a>
        </p>
    </div>
</div>

<script>
// Plain JS toggle — no framework
function toggleSupporterFields(role) {
    document.getElementById('supporterFields').style.display = role === 'supporter' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
