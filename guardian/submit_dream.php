<?php
// ============================================================
// guardian/submit_dream.php — Submit a new student dream
// Place this file in: /before-i-grow-up/guardian/submit_dream.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dream_achievement.php';
require_once __DIR__ . '/../includes/mail.php';

requireRole('guardian');

$pageTitle = 'Submit a Dream';
$base = BASE_PATH;
$errors = [];
$old = [];
$db = getDB();
ensureDreamAchievementSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    // Student info
    $ageGroup  = $_POST['age_group']   ?? '';
    $city      = trim($_POST['city']   ?? '');
    $studentEmail = trim($_POST['student_email'] ?? '');

    // Dream info
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = $_POST['category']         ?? '';
    $budget      = $_POST['budget_range']     ?? '';

    // Validate
    if (!in_array($ageGroup, ['6-9','10-12','13-15','16-18'])) $errors[] = 'Please select an age group.';
    if (empty($city) || strlen($city) < 2)   $errors[] = 'Please enter the student\'s city.';
    if ($studentEmail !== '' && !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid student email address.';
    if (empty($title) || strlen($title) < 5) $errors[] = 'Dream title must be at least 5 characters.';
    if (empty($description) || strlen($description) < 30) $errors[] = 'Description must be at least 30 characters.';

    $validCategories = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support',
                        'Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];
    if (!in_array($category, $validCategories)) $errors[] = 'Please select a valid category.';

    $validBudgets = ['No Money Needed','Under Rs500','Rs500-Rs2,000','Rs2,000-Rs10,000','Rs10,000+'];
    if (!in_array($budget, $validBudgets)) $errors[] = 'Please select a budget range.';

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            // Insert student record
            $stmtS = $db->prepare("INSERT INTO students (guardian_id, age_group, city, student_email) VALUES (?, ?, ?, ?)");
            $stmtS->execute([$_SESSION['user_id'], $ageGroup, $city, $studentEmail !== '' ? $studentEmail : null]);
            $studentId = $db->lastInsertId();

            // Insert dream record
            $stmtD = $db->prepare("
                INSERT INTO dreams (student_id, title, description, category, budget_range, status)
                VALUES (?, ?, ?, ?, ?, 'Submitted')
            ");
            $stmtD->execute([$studentId, $title, $description, $category, $budget]);
            $dreamId = (int)$db->lastInsertId();

            $db->commit();

            $guardianEmail = $_SESSION['email'] ?? '';
            $guardianName  = $_SESSION['name'] ?? 'Guardian';
            if ($guardianEmail !== '') {
                $subject = 'Dream submitted successfully';
                $body = '<p>Hi ' . e($guardianName) . ',</p>'
                      . '<p>Your dream submission has been received and is now pending admin review.</p>'
                      . '<p><strong>Dream:</strong> ' . e($title) . '<br>'
                      . '<strong>Category:</strong> ' . e($category) . '<br>'
                      . '<strong>Status:</strong> Submitted</p>'
                      . '<p>We will notify you once it is verified.</p>'
                      . '<p>Reference ID: #' . $dreamId . '</p>'
                      . '<p>With care,<br>' . APP_NAME . ' team</p>';
                if (!sendEmail($guardianEmail, $subject, $body)) {
                    error_log('Dream submission email failed for guardian: ' . $guardianEmail);
                }
            }
            setFlash('success', 'Dream submitted successfully! Our admin will review it shortly. 🌱');
            redirect($base . '/guardian/my_dreams.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Submission failed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Submit a Student Dream</h1>
        <p>Share a learning goal for a student in your care. All submissions are reviewed before publishing.</p>
    </div>
</section>

<div class="section-sm">
    <div class="container">
        <div class="form-card form-wide" style="margin:0 auto;">

            <?php if (!empty($errors)): ?>
                <div class="flash flash-error" style="margin-bottom:1.5rem;flex-direction:column;align-items:flex-start;gap:.3rem;">
                    <?php foreach ($errors as $err): ?>
                        <span>• <?= e($err) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- ── STUDENT SECTION ── -->
                <div class="detail-section">
                    <h3>👦 About the Student</h3>
                    <p style="font-size:.875rem;color:var(--muted);margin-bottom:1.25rem;">
                        No names or identifying details are shared publicly. This information helps us match dreams appropriately.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="age_group">Age Group</label>
                            <select name="age_group" id="age_group" class="form-control" required>
                                <option value="">— Select —</option>
                                <?php foreach (['6-9','10-12','13-15','16-18'] as $ag): ?>
                                    <option value="<?= $ag ?>" <?= ($old['age_group'] ?? '') === $ag ? 'selected' : '' ?>>
                                        <?= $ag ?> years old
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city">City / Region</label>
                            <input type="text" id="city" name="city" class="form-control"
                                placeholder="e.g. Austin, TX"
                                value="<?= e($old['city'] ?? '') ?>" required>
                            <span class="form-hint">City only — no full address needed.</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="student_email">Student Email (optional)</label>
                        <input type="email" id="student_email" name="student_email" class="form-control"
                            placeholder="student@example.com"
                            value="<?= e($old['student_email'] ?? '') ?>">
                        <span class="form-hint">Used only for dream completion confirmation requests.</span>
                    </div>
                </div>

                <!-- ── DREAM SECTION ── -->
                <div class="detail-section">
                    <h3>🌟 The Dream</h3>

                    <div class="form-group">
                        <label for="title">Dream Title</label>
                        <input type="text" id="title" name="title" class="form-control"
                            placeholder="e.g. Learn to Code a Mobile App"
                            value="<?= e($old['title'] ?? '') ?>"
                            maxlength="200" required>
                        <span class="form-hint">Keep it inspiring and specific. Max 200 characters.</span>
                    </div>

                    <div class="form-group">
                        <label for="description">Dream Description</label>
                        <textarea id="description" name="description" class="form-control"
                            placeholder="Describe what the student hopes to learn or achieve, why it matters to them, and what kind of support would help..."
                            rows="6" required><?= e($old['description'] ?? '') ?></textarea>
                        <span class="form-hint">Be specific and heartfelt. Min 30 characters. Do not include any personal identifying information.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" required>
                                <option value="">— Select category —</option>
                                <?php
                                $cats = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support',
                                         'Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];
                                foreach ($cats as $cat):
                                    $sel = ($old['category'] ?? '') === $cat ? 'selected' : '';
                                ?>
                                    <option value="<?= e($cat) ?>" <?= $sel ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="budget_range">Estimated Support Needed</label>
                            <select name="budget_range" id="budget_range" class="form-control" required>
                                <option value="">— Select range —</option>
                                <?php foreach (['No Money Needed','Under Rs500','Rs500-Rs2,000','Rs2,000-Rs10,000','Rs10,000+'] as $b): ?>
                                    <option value="<?= $b ?>" <?= ($old['budget_range'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── AGREEMENT ── -->
                <div class="form-group">
                    <label style="display:flex;gap:.75rem;align-items:flex-start;cursor:pointer;font-weight:400;">
                        <input type="checkbox" name="agree" required style="margin-top:.2rem;flex-shrink:0;">
                        <span style="font-size:.875rem;">
                            I confirm that I am this student's parent, legal guardian, or registered teacher. I understand this dream will be reviewed by our admin team before being published, and I agree to the platform's child safety guidelines.
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    🌱 Submit Dream for Review
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



