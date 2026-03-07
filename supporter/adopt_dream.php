<?php
// ============================================================
// supporter/adopt_dream.php — Adopt a dream or view adopted dreams
// Place this file in: /before-i-grow-up/supporter/adopt_dream.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

requireRole('supporter');

$pageTitle = 'Adopt a Dream';
$base = BASE_PATH;
$db   = getDB();

$userId = $_SESSION['user_id'];

// ── Handle dream adoption POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dream_id'])) {
    $dreamId     = (int)$_POST['dream_id'];
    $supportType = $_POST['support_type'] ?? 'Mentorship';

    if (!in_array($supportType, ['Mentorship','Sponsorship','Both'])) {
        $supportType = 'Mentorship';
    }

    // Check dream exists and is adoptable
    $check = $db->prepare("SELECT id, status FROM dreams WHERE id = ? AND status IN ('Verified','Matched')");
    $check->execute([$dreamId]);
    $dream = $check->fetch();

    if (!$dream) {
        setFlash('error', 'This dream is not available for adoption.');
        redirect($base . '/supporter/adopt_dream.php');
    }

    // Check if already adopted by this supporter
    $dup = $db->prepare("SELECT id FROM dream_support WHERE dream_id = ? AND supporter_id = ?");
    $dup->execute([$dreamId, $userId]);
    if ($dup->fetch()) {
        setFlash('info', 'You have already expressed interest in this dream.');
        redirect($base . '/supporter/adopt_dream.php');
    }

    // Insert support record
    $ins = $db->prepare("
        INSERT INTO dream_support (dream_id, supporter_id, support_type, status)
        VALUES (?, ?, ?, 'Pending')
    ");
    $ins->execute([$dreamId, $userId, $supportType]);

    // NOTE: Dream status is NOT changed here.
    // It stays 'Verified' until the admin explicitly approves this adoption request.

    setFlash('success', 'Your request has been submitted! 💛 The admin will review and approve your adoption request shortly.');
    redirect($base . '/supporter/adopt_dream.php');
}

// ── Show adoption form if dream_id in GET ──────────────────
$adoptingDream = null;
if (isset($_GET['dream_id'])) {
    $dreamId = (int)$_GET['dream_id'];
    $dStmt   = $db->prepare("
        SELECT d.*, s.age_group, s.city
        FROM dreams d
        JOIN students s ON d.student_id = s.id
        WHERE d.id = ? AND d.status IN ('Verified','Matched')
    ");
    $dStmt->execute([$dreamId]);
    $adoptingDream = $dStmt->fetch();
}

// ── Fetch this supporter's adopted dreams ──────────────────
$myAdoptions = $db->prepare("
    SELECT ds.*, d.title, d.category, d.status AS dream_status, d.description, s.city, s.age_group,
           ds.rejection_reason
    FROM dream_support ds
    JOIN dreams d ON ds.dream_id = d.id
    JOIN students s ON d.student_id = s.id
    WHERE ds.supporter_id = ?
    ORDER BY ds.created_at DESC
");
$myAdoptions->execute([$userId]);
$adoptions = $myAdoptions->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>My Dream Support</h1>
        <p>Track the dreams you've chosen to help fulfill — and adopt new ones.</p>
    </div>
</section>

<div class="section-sm">
    <div class="container">

        <!-- ── Adoption Form ── -->
        <?php if ($adoptingDream): ?>
        <div class="card" style="margin-bottom:2rem;max-width:640px;margin-left:auto;margin-right:auto;">
            <h3 style="margin-bottom:.25rem;">Adopt This Dream</h3>
            <p style="margin-bottom:1.25rem;font-size:.875rem;color:var(--muted);">Review the dream below and choose how you'd like to help.</p>

            <div style="background:var(--cream);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem;">
                <span class="dream-category" style="margin-bottom:.5rem;display:inline-block;"><?= e($adoptingDream['category']) ?></span>
                <h4><?= e($adoptingDream['title']) ?></h4>
                <p style="font-size:.875rem;margin-top:.5rem;"><?= e($adoptingDream['description']) ?></p>
                <div class="dream-meta" style="margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--border);">
                    <span>📍 <?= e($adoptingDream['city']) ?></span>
                    <span>🎂 Age <?= e($adoptingDream['age_group']) ?></span>
                    <span>💰 <?= e($adoptingDream['budget_range']) ?></span>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="dream_id" value="<?= (int)$adoptingDream['id'] ?>">
                <div class="form-group">
                    <label for="support_type">How would you like to support?</label>
                    <select name="support_type" id="support_type" class="form-control">
                        <option value="Mentorship">🧠 Mentorship — I can guide and teach</option>
                        <option value="Sponsorship">💰 Sponsorship — I can fund resources</option>
                        <option value="Both">✨ Both — Mentorship + Sponsorship</option>
                    </select>
                </div>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-amber">💛 Confirm Adoption</button>
                    <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-outline">← Back to Browse</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── My Adoptions List ── -->
        <h2 style="margin-bottom:1.25rem;">Dreams I'm Supporting</h2>

        <?php if (empty($adoptions)): ?>
            <div class="empty-state">
                <div class="empty-icon">💛</div>
                <h3>No adoptions yet</h3>
                <p>Browse our open dreams and find one that inspires you to help.</p>
                <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-primary" style="margin-top:1.25rem;">Browse Dreams</a>
            </div>
        <?php else: ?>
            <div class="grid-2">
            <?php foreach ($adoptions as $a): ?>
                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;">
                        <span class="dream-category"><?= e($a['category']) ?></span>
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                            <span class="status-badge status-<?= str_replace(' ', '-', e($a['dream_status'])) ?>"><?= e($a['dream_status']) ?></span>
                            <span class="status-badge" style="background:#F0FDF4;color:#166534;"><?= e($a['support_type']) ?></span>
                        </div>
                    </div>
                    <h4><?= e($a['title']) ?></h4>
                    <p style="font-size:.875rem;margin-top:.4rem;"><?= e(mb_substr($a['description'], 0, 120)) ?>...</p>
                    <div class="dream-meta">
                        <span>📍 <?= e($a['city']) ?></span>
                        <span>🎂 Age <?= e($a['age_group']) ?></span>
                        <span>📅 Adopted <?= date('M j, Y', strtotime($a['created_at'])) ?></span>
                    </div>
                    <?php if($a['status'] === 'Rejected'): ?>
                    <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:.85rem 1rem;margin-top:.75rem;">
                      <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.3rem;">
                        <span>❌</span>
                        <strong style="color:#991B1B;font-size:.85rem;">Adoption Request Rejected</strong>
                      </div>
                      <p style="color:#B91C1C;font-size:.8rem;margin:0 0 .4rem;line-height:1.5;">
                        The admin has reviewed your request and it was not approved at this time.
                      </p>
                      <?php if($a['rejection_reason']): ?>
                      <div style="background:rgba(255,255,255,.7);border-radius:7px;padding:.55rem .75rem;border:1px solid #FECACA;">
                        <strong style="font-size:.75rem;color:#7F1D1D;display:block;margin-bottom:.15rem;">📋 Reason from admin:</strong>
                        <p style="color:#991B1B;font-size:.8rem;margin:0;line-height:1.5;"><?= e($a['rejection_reason']) ?></p>
                      </div>
                      <?php endif; ?>
                    </div>
                    <?php elseif($a['status'] === 'Approved'): ?>
                    <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:.7rem 1rem;margin-top:.75rem;font-size:.8rem;color:#065F46;font-weight:600;">
                      ✅ Adoption approved — you're matched with this dream!
                    </div>
                    <?php else: ?>
                    <div style="margin-top:.75rem;font-size:.8rem;color:var(--muted);">
                      Support status: <strong style="color:var(--ink);"><?= e($a['status']) ?></strong>
                      <span style="color:#6B7280;"> · Awaiting admin review</span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:2rem;">
            <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-outline">Browse More Dreams</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>