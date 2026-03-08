<?php
// admin/manage_dreams.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/dream_achievement.php';
requireRole('admin');
$pageTitle = 'Manage Dreams';
$base = BASE_PATH;
$db   = getDB();
$adminSidebarActive = 'dreams';
ensureDreamAchievementSchema($db);

// ── Handle REJECT ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_dream') {
    $dreamId = (int)$_POST['dream_id'];
    $reason  = trim($_POST['rejection_reason'] ?? '');
    if ($reason === '') $reason = 'No specific reason provided.';
    $db->prepare("UPDATE dreams SET status='Rejected', rejection_reason=? WHERE id=?")->execute([$reason, $dreamId]);
    $db->prepare("INSERT INTO admin_logs (admin_id,action) VALUES (?,?)")->execute([$_SESSION['user_id'], "Rejected dream #$dreamId: $reason"]);
    setFlash('success', 'Dream rejected. The guardian will see the reason on their dashboard.');
    redirect($base . '/admin/manage_dreams.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_dream') {
    $dreamId = (int)($_POST['dream_id'] ?? 0);
    if ($dreamId > 0) {
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM dream_achievement_confirmations WHERE dream_id=?")->execute([$dreamId]);
            $db->prepare("DELETE FROM dream_support WHERE dream_id=?")->execute([$dreamId]);
            $db->prepare("DELETE FROM dreams WHERE id=?")->execute([$dreamId]);
            $db->prepare("INSERT INTO admin_logs (admin_id,action) VALUES (?,?)")->execute([$_SESSION['user_id'], "Deleted dream #$dreamId"]);
            $db->commit();
            setFlash('success', 'Dream deleted successfully.');
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            setFlash('error', 'Unable to delete dream right now. Please try again.');
        }
    } else {
        setFlash('error', 'Invalid dream id.');
    }
    redirect($base . '/admin/manage_dreams.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_achievement_confirmation') {
    $dreamId = (int)$_POST['dream_id'];
    $infoStmt = $db->prepare("
        SELECT d.id, d.title, d.status, s.student_email, u.email AS guardian_email, u.name AS guardian_name
        FROM dreams d
        JOIN students s ON d.student_id = s.id
        JOIN users u ON s.guardian_id = u.id
        WHERE d.id = ?
        LIMIT 1
    ");
    $infoStmt->execute([$dreamId]);
    $dreamInfo = $infoStmt->fetch();

    if (!$dreamInfo) {
        setFlash('error', 'Dream not found.');
    } elseif (in_array($dreamInfo['status'], ['Submitted', 'Rejected'], true)) {
        setFlash('error', 'This dream is not eligible for completion confirmation yet.');
    } else {
        $recipientEmail = !empty($dreamInfo['student_email']) ? $dreamInfo['student_email'] : $dreamInfo['guardian_email'];
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (14 * 24 * 60 * 60));
        saveDreamAchievementRequest($db, $dreamId, (int)$_SESSION['user_id'], $recipientEmail, $token, $expiresAt);

        $confirmUrl = appUrl('confirm_dream_achievement.php?token=' . urlencode($token));
        $subject = 'Please confirm dream completion';
        $body = '<p>Hi ' . e($dreamInfo['guardian_name']) . ',</p>'
              . '<p>Our team received a completion update for this dream:</p>'
              . '<p><strong>' . e($dreamInfo['title']) . '</strong></p>'
              . '<p>Please confirm with the student and click the link below only if this dream has been completed.</p>'
              . '<p><a href="' . e($confirmUrl) . '">' . e($confirmUrl) . '</a></p>'
              . '<p>This link expires in 14 days.</p>'
              . '<p>With care,<br>' . APP_NAME . ' team</p>';

        if (sendEmail($recipientEmail, $subject, $body)) {
            $db->prepare("INSERT INTO admin_logs (admin_id,action) VALUES (?,?)")->execute([$_SESSION['user_id'], "Sent achievement confirmation request for dream #$dreamId"]);
            $sentTo = !empty($dreamInfo['student_email']) ? 'student' : 'guardian';
            setFlash('success', 'Confirmation email sent to the ' . $sentTo . '.');
        } else {
            setFlash('error', 'Could not send confirmation email right now. Please try again.');
        }
    }
    redirect($base . '/admin/manage_dreams.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $dreamId   = (int)$_POST['dream_id'];
    $newStatus = $_POST['new_status'] ?? '';
    $valid     = ['Submitted','Verified','Matched','In Progress','Dream Achieved'];

    if (in_array($newStatus, $valid, true)) {
        $infoStmt = $db->prepare("
            SELECT d.title, d.status AS old_status, u.email, u.name
            FROM dreams d
            JOIN students s ON d.student_id = s.id
            JOIN users u ON s.guardian_id = u.id
            WHERE d.id = ?
        ");
        $infoStmt->execute([$dreamId]);
        $dreamInfo = $infoStmt->fetch();
        $confirmation = getDreamAchievementConfirmation($db, $dreamId);

        if ($newStatus === 'Dream Achieved' && (!$confirmation || empty($confirmation['confirmed_at']))) {
            setFlash('error', 'Student confirmation is required before setting this dream to Dream Achieved.');
        } else {
            $db->prepare("UPDATE dreams SET status=?, rejection_reason=NULL WHERE id=?")->execute([$newStatus, $dreamId]);
            $db->prepare("INSERT INTO admin_logs (admin_id,action) VALUES (?,?)")->execute([$_SESSION['user_id'], "Dream #$dreamId -> $newStatus"]);
            setFlash('success', 'Dream status updated to "' . $newStatus . '".');

            if ($dreamInfo && !empty($dreamInfo['email'])) {
                $guardianEmail = $dreamInfo['email'];
                $guardianName  = $dreamInfo['name'];
                $title         = $dreamInfo['title'];
                $oldStatus     = $dreamInfo['old_status'];

                if ($newStatus === 'Verified' && $oldStatus !== 'Verified') {
                    $subject = 'Your dream has been approved';
                    $body    = '<p>Hi ' . e($guardianName) . ',</p>'
                             . '<p>Your dream "<strong>' . e($title) . '</strong>" has been <strong>approved</strong> by our team and is now visible for supporters to discover and adopt.</p>'
                             . '<p>You can review the status of all your dreams anytime from your guardian dashboard.</p>'
                             . '<p>With care,<br>' . APP_NAME . ' team</p>';
                    sendEmail($guardianEmail, $subject, $body);
                }
            }
        }
    }

    redirect($base . '/admin/manage_dreams.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : ''));
}
// ── Filters ──────────────────────────────────────────────────
$filterStatus   = $_GET['filter']   ?? '';
$filterCategory = $_GET['category'] ?? '';
$search         = trim($_GET['search'] ?? '');
$allStatuses    = ['Submitted','Verified','Matched','In Progress','Dream Achieved','Rejected'];
$where  = "WHERE 1=1"; $params = [];
if ($filterStatus && in_array($filterStatus, $allStatuses)) { $where .= " AND d.status=?"; $params[] = $filterStatus; }
if ($filterCategory) { $where .= " AND d.category=?"; $params[] = $filterCategory; }
if ($search) { $where .= " AND (d.title LIKE ? OR d.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("
    SELECT d.*, s.city, s.age_group, s.student_email, u.name AS guardian_name, u.email AS guardian_email,
           dac.requested_at AS completion_requested_at,
           dac.confirmed_at AS completion_confirmed_at,
           (SELECT COUNT(*) FROM dream_support ds WHERE ds.dream_id=d.id) AS support_count
    FROM dreams d
    JOIN students s ON d.student_id=s.id
    JOIN users u ON s.guardian_id=u.id
    LEFT JOIN dream_achievement_confirmations dac ON dac.dream_id = d.id
    $where
    ORDER BY CASE d.status WHEN 'Submitted' THEN 0 WHEN 'Rejected' THEN 1 ELSE 2 END, d.created_at DESC
");
$stmt->execute($params);
$dreams = $stmt->fetchAll();

$pendingCount  = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Submitted'")->fetchColumn();
$rejectedCount = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Rejected'")->fetchColumn();
$categories    = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support','Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.adm-layout{display:flex;min-height:calc(100vh - 72px)}
.adm-sb{width:240px;flex-shrink:0;background:linear-gradient(180deg,#2D4A3E,#1E3329);color:#fff;padding:1.5rem 0;position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto;transition:left .3s}
.adm-sb-title{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.5rem 1.5rem 1rem;font-weight:600}
.sb-link{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.5rem;color:rgba(255,255,255,.75);text-decoration:none;font-size:.875rem;transition:all .2s;border-left:3px solid transparent}
.sb-link:hover{color:#fff;background:rgba(255,255,255,.08)}
.sb-link.act{color:#fff;background:rgba(255,255,255,.12);border-left-color:#E8A838}
.sb-ico{font-size:1rem;width:20px;text-align:center}
.sb-num{margin-left:auto;background:#E07058;color:#fff;border-radius:100px;font-size:.68rem;padding:.1rem .45rem;font-weight:700}
.adm-main{flex:1;padding:2rem;min-width:0;background:#F7F3ED}
.adm-hdr h1{font-size:1.6rem;color:#1E3329;margin:0 0 .2rem}
.adm-hdr p{color:#6B7280;font-size:.875rem;margin:0 0 1.4rem}
.tab-pills{display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:1.2rem}
.tp{padding:.42rem .9rem;border-radius:100px;border:1px solid #D1D5DB;background:#fff;font-size:.8rem;font-weight:500;cursor:pointer;color:#374151;transition:all .2s;text-decoration:none;display:inline-block}
.tp:hover{border-color:#5C8C6A;color:#5C8C6A}
.tp.all{background:#5C8C6A;color:#fff;border-color:#5C8C6A}
.tp.warn{background:#F59E0B;color:#fff;border-color:#F59E0B}
.tp.blue{background:#2563EB;color:#fff;border-color:#2563EB}
.tp.red{background:#DC2626;color:#fff;border-color:#DC2626}
.dream-card{background:#fff;border-radius:14px;border:1px solid #EEE8E0;padding:1.35rem;margin-bottom:.85rem;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:box-shadow .2s}
.dream-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08)}
.dream-card.sub{border-left:4px solid #F59E0B}
.dream-card.rej{border-left:4px solid #DC2626;background:#FFFAFA}
.dc-wrap{display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap}
.dc-info{flex:1;min-width:200px}
.dc-info h4{font-size:.975rem;font-weight:700;color:#1E3329;margin:0 0 .25rem}
.dc-info p{font-size:.82rem;color:#6B7280;margin:0 0 .5rem;line-height:1.5}
.dc-meta{display:flex;gap:.55rem;flex-wrap:wrap;font-size:.77rem;color:#6B7280}
.chips{display:flex;gap:.3rem;flex-wrap:wrap;margin-bottom:.45rem}
.chip{background:#F3F4F6;border-radius:100px;padding:.15rem .5rem;font-size:.7rem;color:#4B5563;border:1px solid #E5E7EB}
.bdg{display:inline-block;padding:.17rem .52rem;border-radius:100px;font-size:.7rem;font-weight:600;white-space:nowrap}
.bdg-Submitted{background:#FEF3C7;color:#92400E}
.bdg-Verified{background:#DBEAFE;color:#1E40AF}
.bdg-Matched{background:#EDE9FE;color:#5B21B6}
.bdg-In-Progress{background:#FFF7ED;color:#C2410C}
.bdg-Dream-Achieved{background:#D1FAE5;color:#065F46}
.bdg-Rejected{background:#FEE2E2;color:#991B1B}
.rej-notice{background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:.8rem 1rem;margin-top:.7rem}
.rej-notice strong{display:block;color:#991B1B;font-size:.8rem;margin-bottom:.2rem}
.rej-notice p{color:#B91C1C;font-size:.8rem;margin:0;line-height:1.5}
.dc-actions{display:flex;flex-direction:column;gap:.45rem;flex-shrink:0;min-width:175px}
.btn-v{background:#059669;color:#fff;border:none;padding:.42rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;width:100%;transition:all .2s}
.btn-v:hover{background:#047857}
.btn-r{background:#DC2626;color:#fff;border:none;padding:.42rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;width:100%;transition:all .2s}
.btn-r:hover{background:#B91C1C}
.btn-st{background:#F3F4F6;color:#374151;border:1px solid #E5E7EB;padding:.42rem .9rem;border-radius:8px;font-size:.78rem;font-weight:500;cursor:pointer;width:100%;transition:all .2s}
.btn-st:hover{background:#E5E7EB}
/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;padding:1rem}
.modal-bg.open{display:flex}
.modal-box{background:#fff;border-radius:18px;padding:2rem;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:su .25s ease}
@keyframes su{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.modal-box .mi{font-size:2.5rem;text-align:center;margin-bottom:.6rem}
.modal-box h3{font-size:1.1rem;font-weight:700;color:#1E3329;text-align:center;margin:0 0 .3rem}
.modal-box .ms{font-size:.85rem;color:#6B7280;text-align:center;margin:0 0 1.1rem}
.modal-dream{background:#FEF3C7;border-radius:8px;padding:.55rem .9rem;font-size:.85rem;font-weight:600;color:#92400E;margin-bottom:1rem;text-align:center}
.modal-box label{display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.35rem}
.modal-box textarea{width:100%;border:1px solid #D1D5DB;border-radius:10px;padding:.7rem;font-size:.875rem;resize:vertical;min-height:96px;font-family:inherit;line-height:1.5;box-sizing:border-box;transition:border-color .2s}
.modal-box textarea:focus{outline:none;border-color:#DC2626;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
.qreasons{display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.8rem}
.qr{padding:.25rem .6rem;background:#F3F4F6;border:1px solid #E5E7EB;border-radius:100px;font-size:.71rem;cursor:pointer;transition:all .2s;color:#374151}
.qr:hover{background:#FEE2E2;border-color:#FECACA;color:#991B1B}
.modal-ft{display:flex;gap:.7rem;margin-top:1rem}
.modal-ft button{flex:1;padding:.6rem;border-radius:10px;font-size:.875rem;font-weight:600;cursor:pointer;border:none;transition:all .2s}
.btn-cancel{background:#F3F4F6;color:#374151}.btn-cancel:hover{background:#E5E7EB}
.btn-confirm{background:#DC2626;color:#fff}.btn-confirm:hover{background:#B91C1C}
.sb-toggle{display:none;position:fixed;bottom:1.5rem;right:1.5rem;width:50px;height:50px;background:#5C8C6A;color:#fff;border-radius:50%;border:none;font-size:1.2rem;cursor:pointer;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,.2);align-items:center;justify-content:center}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:49}
@media(max-width:900px){
  .adm-sb{position:fixed;left:-260px;top:0;height:100vh;z-index:50;width:260px}
  .adm-sb.open{left:0}
  .sb-toggle{display:flex}.sb-overlay.show{display:block}
  .adm-main{padding:1.1rem}
  .dc-actions{min-width:unset;width:100%}
}
@media(max-width:600px){.adm-main{padding:.85rem}.dc-wrap{flex-direction:column}}
</style>

<!-- Reject Dream Modal -->
<div class="modal-bg" id="rejectModal">
  <div class="modal-box">
    <div class="mi">❌</div>
    <h3>Reject This Dream</h3>
    <p class="ms">The guardian will see this reason clearly on their dashboard. Be constructive.</p>
    <div class="modal-dream" id="modalTitle">—</div>
    <form method="POST" action="" id="rejectForm">
      <input type="hidden" name="action" value="reject_dream">
      <input type="hidden" name="dream_id" id="modalDreamId" value="">
      <label>Quick reason (click to fill):</label>
      <div class="qreasons">
        <span class="qr" onclick="setR('Incomplete or unclear dream description.')">Incomplete description</span>
        <span class="qr" onclick="setR('Dream does not meet the platform guidelines.')">Not within guidelines</span>
        <span class="qr" onclick="setR('Budget requirement is too high for the platform.')">Budget too high</span>
        <span class="qr" onclick="setR('Duplicate submission — a similar dream already exists.')">Duplicate</span>
        <span class="qr" onclick="setR('Inappropriate content detected in the dream details.')">Inappropriate content</span>
        <span class="qr" onclick="setR('Missing required information about the student.')">Missing student info</span>
      </div>
      <label for="rejReason">Rejection reason <span style="color:#DC2626">*</span></label>
      <textarea name="rejection_reason" id="rejReason" placeholder="Explain clearly why this dream is being rejected — the guardian will read this..." required></textarea>
      <div class="modal-ft">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-confirm">❌ Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<div class="adm-layout">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="adm-main">
    <div class="adm-hdr">
      <h1>🌟 Manage Dreams</h1>
      <p><?= count($dreams) ?> dream<?= count($dreams)!==1?'s':'' ?> found</p>
    </div>

    <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center;">
      <input type="text" name="search" placeholder="Search dreams..." value="<?= e($search) ?>"
             style="padding:.45rem .75rem;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;flex:1;min-width:160px;max-width:260px;background:#fff">
      <select name="category" style="padding:.45rem .75rem;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
        <option value="">All Categories</option>
        <?php foreach($categories as $cat): ?>
          <option value="<?= e($cat) ?>" <?= $filterCategory===$cat?'selected':'' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="<?= $base ?>/admin/manage_dreams.php" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <div class="tab-pills">
      <a href="?" class="tp <?= $filterStatus===''?'all':'' ?>">All</a>
      <a href="?filter=Submitted" class="tp <?= $filterStatus==='Submitted'?'warn':'' ?>">🔔 Pending (<?=$pendingCount?>)</a>
      <a href="?filter=Verified" class="tp <?= $filterStatus==='Verified'?'blue':'' ?>">✅ Verified</a>
      <a href="?filter=Matched" class="tp <?= $filterStatus==='Matched'?'blue':'' ?>">🤝 Matched</a>
      <a href="?filter=In Progress" class="tp <?= $filterStatus==='In Progress'?'blue':'' ?>">🔄 In Progress</a>
      <a href="?filter=Dream Achieved" class="tp <?= $filterStatus==='Dream Achieved'?'all':'' ?>">🏆 Achieved</a>
      <a href="?filter=Rejected" class="tp <?= $filterStatus==='Rejected'?'red':'' ?>">❌ Rejected (<?=$rejectedCount?>)</a>
    </div>

    <?php if(empty($dreams)): ?>
      <div style="text-align:center;padding:3rem 1rem;background:#fff;border-radius:14px;border:1px solid #EEE8E0;color:#6B7280">
        <div style="font-size:2.5rem;margin-bottom:.5rem">🌟</div>
        <p style="font-weight:600;color:#374151;margin:.2rem 0">No dreams found</p>
        <p style="font-size:.85rem;margin:0">Try adjusting your filters.</p>
      </div>
    <?php else: ?>
      <?php foreach($dreams as $d):
        $isRej = $d['status']==='Rejected';
        $isSub = $d['status']==='Submitted';
        $isConfirmed = !empty($d['completion_confirmed_at']);
        $isRequested = !empty($d['completion_requested_at']);
        $cls = $isRej?'rej':($isSub?'sub':'');
        $slug = str_replace([' '],'-',$d['status']);
      ?>
      <div class="dream-card <?= $cls ?>">
        <div class="dc-wrap">
          <div class="dc-info">
            <div class="chips">
              <span class="chip"><?= e($d['category']) ?></span>
              <span class="bdg bdg-<?= $slug ?>"><?= e($d['status']) ?></span>
              <?php if($d['support_count']>0):?><span class="chip"><?= $d['support_count'] ?> supporter(s)</span><?php endif;?>
              <?php if($isConfirmed): ?>
                <span class="chip" style="background:#ECFDF5;border-color:#BBF7D0;color:#166534;">Completion confirmed</span>
              <?php elseif($isRequested): ?>
                <span class="chip" style="background:#FFFBEB;border-color:#FDE68A;color:#92400E;">Completion confirmation pending</span>
              <?php endif; ?>
            </div>
            <h4><?= e($d['title']) ?></h4>
            <p><?= e(mb_substr($d['description'],0,180)) ?><?= mb_strlen($d['description'])>180?'...':''?></p>
            <div class="dc-meta">
              <span><?= e($d['guardian_name']) ?></span>
              <span><?= e($d['guardian_email']) ?></span>
              <?php if(!empty($d['student_email'])): ?><span><?= e($d['student_email']) ?></span><?php endif; ?>
              <span><?= e($d['city']) ?></span>
              <span>Age <?= e($d['age_group']) ?></span>
              <span><?= e($d['budget_range']) ?></span>
              <span><?= date('M j, Y', strtotime($d['created_at'])) ?></span>
            </div>
            <?php if($isRej && $d['rejection_reason']): ?>
            <div class="rej-notice">
              <strong>Rejection Reason (visible to guardian):</strong>
              <p><?= e($d['rejection_reason']) ?></p>
            </div>
            <?php endif; ?>
          </div>

          <div class="dc-actions">
            <?php if($isSub): ?>
              <form method="POST"><input type="hidden" name="action" value="update_status"><input type="hidden" name="dream_id" value="<?=(int)$d['id']?>"><input type="hidden" name="new_status" value="Verified"><button type="submit" class="btn-v">Verify Dream</button></form>
              <button class="btn-r" onclick="openModal(<?=(int)$d['id']?>,<?=htmlspecialchars(json_encode($d['title']),ENT_QUOTES)?> )">Reject Dream</button>
              <form method="POST" onsubmit="return confirm('Delete this dream permanently? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_dream">
                <input type="hidden" name="dream_id" value="<?=(int)$d['id']?>">
                <button type="submit" class="btn-r">Delete Dream</button>
              </form>
            <?php elseif($isRej): ?>
              <form method="POST"><input type="hidden" name="action" value="update_status"><input type="hidden" name="dream_id" value="<?=(int)$d['id']?>"><input type="hidden" name="new_status" value="Verified"><button type="submit" class="btn-v">Re-verify Dream</button></form>
              <form method="POST" onsubmit="return confirm('Delete this dream permanently? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_dream">
                <input type="hidden" name="dream_id" value="<?=(int)$d['id']?>">
                <button type="submit" class="btn-r">Delete Dream</button>
              </form>
            <?php else: ?>
              <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="dream_id" value="<?=(int)$d['id']?>">
                <select name="new_status" style="width:100%;padding:.4rem .6rem;border:1px solid #D1D5DB;border-radius:8px;font-size:.8rem;background:#fff;margin-bottom:.4rem">
                  <?php foreach(['Verified','Matched','In Progress'] as $s): ?>
                    <option value="<?=e($s)?>" <?=$d['status']===$s?'selected':''?>><?=e($s)?></option>
                  <?php endforeach; ?>
                  <?php if($isConfirmed): ?>
                    <option value="Dream Achieved" <?=$d['status']==='Dream Achieved'?'selected':''?>>Dream Achieved</option>
                  <?php endif; ?>
                </select>
                <button type="submit" class="btn-st">Update Status</button>
              </form>
              <?php if($d['status'] !== 'Dream Achieved'): ?>
              <form method="POST">
                <input type="hidden" name="action" value="request_achievement_confirmation">
                <input type="hidden" name="dream_id" value="<?=(int)$d['id']?>">
                <button type="submit" class="btn-v"><?= $isRequested ? 'Resend Completion Email' : 'Send Completion Email' ?></button>
              </form>
              <?php endif; ?>
              <button class="btn-r" onclick="openModal(<?=(int)$d['id']?>,<?=htmlspecialchars(json_encode($d['title']),ENT_QUOTES)?> )">Reject</button>
              <form method="POST" onsubmit="return confirm('Delete this dream permanently? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_dream">
                <input type="hidden" name="dream_id" value="<?=(int)$d['id']?>">
                <button type="submit" class="btn-r">Delete Dream</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<button class="sb-toggle" onclick="toggleSb()">&#9776;</button>
<script>
function openModal(id, title) {
  document.getElementById('modalDreamId').value = id;
  document.getElementById('modalTitle').textContent = '🌟 ' + title;
  document.getElementById('rejReason').value = '';
  document.getElementById('rejectModal').classList.add('open');
  setTimeout(()=>document.getElementById('rejReason').focus(), 100);
}
function closeModal() { document.getElementById('rejectModal').classList.remove('open'); }
function setR(t) { document.getElementById('rejReason').value = t; document.getElementById('rejReason').focus(); }
document.getElementById('rejectModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
function toggleSb(){document.getElementById('adSb').classList.toggle('open');document.getElementById('sbOv').classList.toggle('show');}
function closeSb(){document.getElementById('adSb').classList.remove('open');document.getElementById('sbOv').classList.remove('show');}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

