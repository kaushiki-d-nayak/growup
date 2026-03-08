<?php
// Shared admin sidebar for all admin pages.
if (!isset($base)) {
    require_once __DIR__ . '/../config/app.php';
    $base = BASE_PATH;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/dream_feedback.php';
require_once __DIR__ . '/auth.php';

$db = getDB();
ensureDreamFeedbackSchema($db);
$pendingDreamsSidebar = (int)$db->query("SELECT COUNT(*) FROM dreams WHERE status='Submitted'")->fetchColumn();
$pendingAdoptionsSidebar = (int)$db->query("SELECT COUNT(*) FROM dream_support WHERE status='Pending'")->fetchColumn();
$adminId = (int)($_SESSION['user_id'] ?? 0);
$submittedFeedbackSidebar = getAdminUnreadFeedbackCount($db, $adminId);
$active = $adminSidebarActive ?? '';
?>
<style>
#adSb{width:230px;flex-shrink:0;background:#1A2E25;padding:1.5rem 0 2rem;position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto;display:flex;flex-direction:column;transition:left .25s}
#adSb .adm-sb-title{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.5rem 1.5rem 1rem;font-weight:600}
#adSb .sb-link{display:flex;align-items:center;gap:.65rem;padding:.62rem 1.5rem;color:rgba(255,255,255,.65);text-decoration:none;font-size:.86rem;font-weight:500;transition:all .18s;border-left:3px solid transparent}
#adSb .sb-link:hover{color:#fff;background:rgba(255,255,255,.07);border-left-color:rgba(255,255,255,.15)}
#adSb .sb-link.act{color:#fff;background:rgba(232,168,56,.13);border-left-color:#E8A838}
#adSb .sb-ico{width:18px;text-align:center}
#adSb .sb-num{margin-left:auto;background:#E07058;color:#fff;border-radius:20px;font-size:.64rem;font-weight:700;padding:.08rem .38rem}
#sbOv{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99}
@media(max-width:900px){
  #adSb{position:fixed;left:-250px;top:0;height:100vh;z-index:100;width:250px}
  #adSb.open{left:0}
  #sbOv.show{display:block}
}
</style>
<aside class="adm-sb" id="adSb">
  <div class="adm-sb-title">Before I Grow Up</div>
  <nav>
    <a href="<?= $base ?>/admin/dashboard.php" class="sb-link <?= $active==='dashboard'?'act':'' ?>">
      <span class="sb-ico">&#x1F4CA;</span> Dashboard
    </a>
    <a href="<?= $base ?>/admin/manage_dreams.php" class="sb-link <?= $active==='dreams'?'act':'' ?>">
      <span class="sb-ico">&#x1F31F;</span> Manage Dreams
      <?php if($pendingDreamsSidebar>0):?><span class="sb-num"><?= $pendingDreamsSidebar ?></span><?php endif; ?>
    </a>
    <a href="<?= $base ?>/admin/manage_adoptions.php" class="sb-link <?= $active==='adoptions'?'act':'' ?>">
      <span class="sb-ico">&#x1F91D;</span> Adoptions
      <?php if($pendingAdoptionsSidebar>0):?><span class="sb-num"><?= $pendingAdoptionsSidebar ?></span><?php endif; ?>
    </a>
    <a href="<?= $base ?>/admin/matched_pairs.php" class="sb-link <?= $active==='matched_pairs'?'act':'' ?>">
      <span class="sb-ico">&#x2705;</span> Matched Pairs
    </a>
    <a href="<?= $base ?>/admin/manage_users.php" class="sb-link <?= $active==='users'?'act':'' ?>">
      <span class="sb-ico">&#x1F465;</span> Users
    </a>
    <a href="<?= $base ?>/admin/feedback_reviews.php" class="sb-link <?= $active==='feedback'?'act':'' ?>">
      <span class="sb-ico">&#x1F4DD;</span> Feedback
      <?php if($submittedFeedbackSidebar>0):?><span class="sb-num"><?= $submittedFeedbackSidebar ?></span><?php endif; ?>
    </a>
    <a href="<?= $base ?>/supporter/browse_dreams.php" class="sb-link">
      <span class="sb-ico">&#x1F310;</span> Public View
    </a>
    <a href="<?= $base ?>/logout.php" class="sb-link" style="margin-top:2rem;border-top:1px solid rgba(255,255,255,.1);padding-top:1rem;">
      <span class="sb-ico">&#x1F6AA;</span> Logout
    </a>
  </nav>
</aside>
<div class="sb-overlay" id="sbOv" onclick="closeSb()"></div>
