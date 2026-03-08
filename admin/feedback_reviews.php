<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/dream_feedback.php';

requireRole('admin');
$pageTitle = 'Feedback Reviews';
$base = BASE_PATH;
$db = getDB();
$adminSidebarActive = 'feedback';
ensureDreamFeedbackSchema($db);
markAdminFeedbackReviewed($db, (int)$_SESSION['user_id']);

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($status === 'submitted') {
    $where .= " AND fr.submitted_at IS NOT NULL";
} elseif ($status === 'pending') {
    $where .= " AND fr.submitted_at IS NULL";
}
if (in_array($roleFilter, ['guardian','supporter'], true)) {
    $where .= " AND fr.recipient_role = ?";
    $params[] = $roleFilter;
}
if ($search !== '') {
    $where .= " AND (d.title LIKE ? OR fr.recipient_name LIKE ? OR fr.recipient_email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $db->prepare("
    SELECT fr.*, d.title AS dream_title, d.category
    FROM dream_feedback_requests fr
    JOIN dreams d ON fr.dream_id = d.id
    $where
    ORDER BY COALESCE(fr.submitted_at, fr.sent_at) DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalCount = (int)$db->query("SELECT COUNT(*) FROM dream_feedback_requests")->fetchColumn();
$submittedCount = (int)$db->query("SELECT COUNT(*) FROM dream_feedback_requests WHERE submitted_at IS NOT NULL")->fetchColumn();
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM dream_feedback_requests WHERE submitted_at IS NULL")->fetchColumn();
$donationInterestCount = (int)$db->query("SELECT COUNT(*) FROM dream_feedback_requests WHERE submitted_at IS NOT NULL AND donation_interest = 1")->fetchColumn();
$avgRating = (float)$db->query("SELECT COALESCE(AVG(rating), 0) FROM dream_feedback_requests WHERE submitted_at IS NOT NULL")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.ap{display:flex;min-height:calc(100vh - 72px)}
.ap-main{flex:1;padding:2rem 2.2rem;background:#F5F0E8;min-width:0}
.ap-hdr h1{font-family:'Fraunces',serif;font-size:1.65rem;color:#1A2E25;margin:0 0 .2rem}
.ap-hdr p{color:#7A7060;font-size:.86rem;margin:0 0 1.2rem}
.stat-row{display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:1.2rem}
.stat-pill{background:#fff;border:1px solid #E8E0D4;border-radius:12px;padding:.8rem 1rem;min-width:140px;flex:1}
.stat-pill .n{font-family:'Fraunces',serif;font-size:1.55rem;color:#1A2E25;line-height:1}
.stat-pill .l{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#7A7060;font-weight:600}
.fbar{display:flex;gap:.55rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
.fbar input,.fbar select{padding:.45rem .7rem;border:1px solid #D6CFC4;border-radius:8px;background:#fff;color:#1A2E25;font-size:.84rem}
.tb-wrap{background:#fff;border:1px solid #E8E0D4;border-radius:14px;overflow-x:auto}
.tb{width:100%;border-collapse:collapse;font-size:.83rem;min-width:840px}
.tb th{padding:.6rem .8rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#7A7060;border-bottom:2px solid #F0EBE2}
.tb td{padding:.62rem .8rem;border-bottom:1px solid #F5F0E8;vertical-align:top}
.tb tr:last-child td{border-bottom:none}
.chip{display:inline-block;border-radius:100px;padding:.14rem .5rem;font-size:.68rem;font-weight:600}
.c-guardian{background:#EDE9FE;color:#5B21B6}
.c-supporter{background:#ECFDF5;color:#065F46}
.c-sub{background:#D1FAE5;color:#065F46}
.c-pend{background:#FEF3C7;color:#92400E}
.c-donate{background:#DBEAFE;color:#1E40AF}
.review{color:#5C5447;max-width:260px;line-height:1.45}
.ap-tog{display:none;position:fixed;bottom:1.5rem;right:1.5rem;width:48px;height:48px;background:#5C8C6A;color:#fff;border-radius:50%;border:none;font-size:1.1rem;cursor:pointer;z-index:200;box-shadow:0 4px 14px rgba(0,0,0,.2);align-items:center;justify-content:center}
@media(max-width:900px){
  .ap-main{padding:1.2rem}
  .ap-tog{display:flex}
}
</style>

<div class="ap">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="ap-main">
    <div class="ap-hdr">
      <h1>Feedback Reviews</h1>
      <p>Track feedback submitted by guardians and supporters after dream completion.</p>
    </div>

    <div class="stat-row">
      <div class="stat-pill"><div class="n"><?= $totalCount ?></div><div class="l">Invites</div></div>
      <div class="stat-pill"><div class="n"><?= $submittedCount ?></div><div class="l">Submitted</div></div>
      <div class="stat-pill"><div class="n"><?= $pendingCount ?></div><div class="l">Pending</div></div>
      <div class="stat-pill"><div class="n"><?= number_format($avgRating, 1) ?></div><div class="l">Avg Rating</div></div>
      <div class="stat-pill"><div class="n"><?= $donationInterestCount ?></div><div class="l">Donation Interest</div></div>
    </div>

    <form method="GET" class="fbar">
      <input type="text" name="search" placeholder="Search by dream, name, or email..." value="<?= e($search) ?>" style="min-width:220px;flex:1;max-width:360px">
      <select name="status">
        <option value="">All Status</option>
        <option value="submitted" <?= $status==='submitted'?'selected':'' ?>>Submitted</option>
        <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
      </select>
      <select name="role">
        <option value="">All Roles</option>
        <option value="guardian" <?= $roleFilter==='guardian'?'selected':'' ?>>Guardian</option>
        <option value="supporter" <?= $roleFilter==='supporter'?'selected':'' ?>>Supporter</option>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <a class="btn btn-outline btn-sm" href="<?= $base ?>/admin/feedback_reviews.php">Clear</a>
    </form>

    <div class="tb-wrap">
      <table class="tb">
        <thead>
          <tr>
            <th>Dream</th>
            <th>Recipient</th>
            <th>Status</th>
            <th>Rating</th>
            <th>Review</th>
            <th>Donation</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" style="color:#7A7060;">No feedback records found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?= e($r['dream_title']) ?></strong><br>
              <small style="color:#7A7060;"><?= e($r['category']) ?></small>
            </td>
            <td>
              <?= e($r['recipient_name']) ?><br>
              <small style="color:#7A7060;"><?= e($r['recipient_email']) ?></small><br>
              <span class="chip <?= $r['recipient_role']==='guardian'?'c-guardian':'c-supporter' ?>"><?= e(ucfirst($r['recipient_role'])) ?></span>
            </td>
            <td>
              <?php if (!empty($r['submitted_at'])): ?>
                <span class="chip c-sub">Submitted</span>
              <?php else: ?>
                <span class="chip c-pend">Pending</span>
              <?php endif; ?>
            </td>
            <td><?= $r['rating'] ? (int)$r['rating'] . '/5' : '—' ?></td>
            <td class="review"><?= $r['review_text'] ? e(mb_substr($r['review_text'], 0, 180)) . (mb_strlen($r['review_text']) > 180 ? '...' : '') : '—' ?></td>
            <td>
              <?php if ((int)$r['donation_interest'] === 1): ?>
                <span class="chip c-donate">Interested</span>
                <?php if (!is_null($r['donation_amount'])): ?><br><small>₹<?= number_format((float)$r['donation_amount'], 2) ?></small><?php endif; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td><?= !empty($r['submitted_at']) ? date('M j, Y g:ia', strtotime($r['submitted_at'])) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<button class="ap-tog" onclick="toggleSb()">&#9776;</button>
<script>
function toggleSb(){document.getElementById('adSb').classList.toggle('open');document.getElementById('sbOv').classList.toggle('show')}
function closeSb(){document.getElementById('adSb').classList.remove('open');document.getElementById('sbOv').classList.remove('show')}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
