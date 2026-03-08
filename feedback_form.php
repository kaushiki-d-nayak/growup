<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/dream_feedback.php';

$pageTitle = 'Feedback';
$base = BASE_PATH;
$db = getDB();
ensureDreamFeedbackSchema($db);

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$request = null;
$errors = [];
$saved = false;
$upiId = 'nayakkaushiki19@okaxis';
$accountName = 'Kaushiki D Nayak';
$upiQrData = 'upi://pay?pa=' . urlencode($upiId) . '&pn=' . urlencode($accountName) . '&cu=INR';
$upiQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . $upiQrData;

if ($token !== '') {
    $request = getDreamFeedbackRequestByToken($db, $token);
}

if (!$request) {
    $errors[] = 'This feedback link is invalid or has expired.';
} else {
    if (empty($request['opened_at'])) {
        $db->prepare("UPDATE dream_feedback_requests SET opened_at = NOW() WHERE id = ?")->execute([(int)$request['id']]);
        $request['opened_at'] = date('Y-m-d H:i:s');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($request['submitted_at'])) {
        $rating = (int)($_POST['rating'] ?? 0);
        $reviewText = trim((string)($_POST['review_text'] ?? ''));
        $donationInterest = isset($_POST['donation_interest']) ? 1 : 0;
        $donationAmountRaw = trim((string)($_POST['donation_amount'] ?? ''));
        $donationAmount = null;

        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Please select a rating from 1 to 5.';
        }
        if (mb_strlen($reviewText) > 2000) {
            $errors[] = 'Review is too long (max 2000 characters).';
        }
        if ($donationAmountRaw !== '') {
            if (!is_numeric($donationAmountRaw) || (float)$donationAmountRaw < 0) {
                $errors[] = 'Donation amount must be a valid positive number.';
            } else {
                $donationAmount = round((float)$donationAmountRaw, 2);
            }
        }

        if (empty($errors)) {
            $stmt = $db->prepare("
                UPDATE dream_feedback_requests
                SET rating = ?, review_text = ?, donation_interest = ?, donation_amount = ?, submitted_at = NOW()
                WHERE id = ? AND submitted_at IS NULL
            ");
            $stmt->execute([
                $rating,
                $reviewText !== '' ? $reviewText : null,
                $donationInterest,
                $donationAmount,
                (int)$request['id']
            ]);
            $saved = true;
            $request = getDreamFeedbackRequestByToken($db, $token);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:760px;">
        <div class="card" style="padding:2rem;">
            <h1 style="font-size:2rem;margin-bottom:.6rem;">Share Your Feedback</h1>
            <?php if ($request): ?>
                <p style="margin-bottom:1rem;">
                    Dream: <strong><?= e($request['dream_title']) ?></strong><br>
                    You are receiving this as a <strong><?= e($request['recipient_role']) ?></strong>.
                </p>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="flash flash-error" style="margin-bottom:1rem;">
                    <?php foreach ($errors as $err): ?>
                        <div><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($request && !empty($request['submitted_at'])): ?>
                <div class="flash flash-success" style="margin-bottom:0;">
                    Thank you! Your feedback has been submitted.
                </div>
            <?php elseif ($request): ?>
                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <label for="rating" style="display:block;font-weight:600;margin-bottom:.35rem;">Rating (1 to 5)</label>
                    <select id="rating" name="rating" required style="width:100%;max-width:180px;margin-bottom:1rem;">
                        <option value="">Select</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= (string)($_POST['rating'] ?? '') === (string)$i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>

                    <label for="review_text" style="display:block;font-weight:600;margin-bottom:.35rem;">Review (optional)</label>
                    <textarea id="review_text" name="review_text" rows="5" placeholder="Tell us what worked well and what we can improve." style="width:100%;margin-bottom:1rem;"><?= e((string)($_POST['review_text'] ?? '')) ?></textarea>

                    <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.7rem;">
                        <input id="donation_interest" type="checkbox" name="donation_interest" value="1" <?= isset($_POST['donation_interest']) ? 'checked' : '' ?>>
                        I am open to donating to support the platform.
                    </label>

                    <div id="donationBox" style="display:<?= isset($_POST['donation_interest']) ? 'block' : 'none' ?>;background:#FFFAF4;border:1px solid #E8D9C4;border-radius:12px;padding:1rem;margin:0 0 1rem;">
                        <p style="margin:0 0 .55rem;font-weight:700;color:#2C2416;">Donate via UPI</p>
                        <p style="margin:0;font-size:.92rem;">UPI ID: <?= e($upiId) ?></p>
                        <p style="margin:0 0 .75rem;font-size:.92rem;">Account Name: <?= e($accountName) ?></p>
                        <p style="margin:0 0 .65rem;font-size:.92rem;">Scan the QR below</p>
                        <img src="<?= e($upiQrUrl) ?>" alt="UPI QR code for donation" style="width:220px;max-width:100%;border-radius:10px;border:1px solid #E8D9C4;background:#fff;padding:.4rem;">
                    </div>

                    <label for="donation_amount" style="display:block;font-weight:600;margin-bottom:.35rem;">Donation amount (optional)</label>
                    <input id="donation_amount" type="number" min="0" step="0.01" name="donation_amount" placeholder="e.g. 10.00" value="<?= e((string)($_POST['donation_amount'] ?? '')) ?>" style="width:100%;max-width:220px;margin-bottom:1.4rem;">

                    <button type="submit" class="btn btn-primary">Submit Feedback</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
(function(){
    var check = document.getElementById('donation_interest');
    var box = document.getElementById('donationBox');
    if (!check || !box) return;
    function sync(){ box.style.display = check.checked ? 'block' : 'none'; }
    check.addEventListener('change', sync);
    sync();
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
