<?php
// admin/matched_pairs.php � All confirmed dream-supporter matches with progress tracking
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/dream_achievement.php';

requireRole('admin');
$pageTitle = 'Matched Pairs';
$base = BASE_PATH;
$db   = getDB();
$adminSidebarActive = 'matched_pairs';
ensureDreamAchievementSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_achievement_confirmation') {
    $dreamId = (int)$_POST['dream_id'];
    $infoStmt = $db->prepare("
        SELECT d.id, d.title, d.status, s.student_email, gu.email AS guardian_email, gu.name AS guardian_name
        FROM dreams d
        JOIN students s ON d.student_id = s.id
        JOIN users gu ON s.guardian_id = gu.id
        WHERE d.id = ?
        LIMIT 1
    ");
    $infoStmt->execute([$dreamId]);
    $dreamInfo = $infoStmt->fetch();

    if (!$dreamInfo) {
        setFlash('error', 'Dream not found.');
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
            setFlash('success', 'Completion confirmation email sent.');
        } else {
            setFlash('error', 'Could not send confirmation email right now. Please try again.');
        }
    }

    redirect($base . '/admin/matched_pairs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dream_id'], $_POST['new_status'])) {
    $dreamId   = (int)$_POST['dream_id'];
    $newStatus = $_POST['new_status'];

    if (in_array($newStatus, ['Matched','In Progress','Dream Achieved'], true)) {
        $confirmation = getDreamAchievementConfirmation($db, $dreamId);
        if ($newStatus === 'Dream Achieved' && (!$confirmation || empty($confirmation['confirmed_at']))) {
            setFlash('error', 'Student confirmation is required before setting Dream Achieved.');
        } else {
            $db->prepare("UPDATE dreams SET status=? WHERE id=?")->execute([$newStatus, $dreamId]);
            $db->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?,?)")->execute([$_SESSION['user_id'], "Updated dream #$dreamId to '$newStatus'"]);
            setFlash('success', 'Progress updated to "' . $newStatus . '".');
        }
    }
    redirect($base . '/admin/matched_pairs.php');
}

$filterCategory = $_GET['category'] ?? '';
$filterStatus   = $_GET['status']   ?? '';
$search         = trim($_GET['search'] ?? '');
$where  = "WHERE ds.status='Approved'";
$params = [];
if ($filterCategory) { $where .= " AND d.category=?"; $params[] = $filterCategory; }
if ($filterStatus)   { $where .= " AND d.status=?";   $params[] = $filterStatus; }
if ($search) {
    $where .= " AND (d.title LIKE ? OR u.name LIKE ? OR gu.name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$stmt = $db->prepare("
    SELECT ds.id AS support_id, ds.support_type, ds.created_at AS matched_at,
           d.id AS dream_id, d.title, d.category, d.status AS dream_status, d.description, d.budget_range,
           u.name AS supporter_name, u.email AS supporter_email,
           sp.profession,
           s.city, s.age_group, s.student_email,
           gu.name AS guardian_name, gu.email AS guardian_email,
           dac.requested_at AS completion_requested_at,
           dac.confirmed_at AS completion_confirmed_at
    FROM dream_support ds
    JOIN dreams d ON ds.dream_id=d.id
    JOIN users u ON ds.supporter_id=u.id
    LEFT JOIN supporters sp ON sp.user_id=u.id
    JOIN students s ON d.student_id=s.id
    JOIN users gu ON s.guardian_id=gu.id
    LEFT JOIN dream_achievement_confirmations dac ON dac.dream_id = d.id
    $where
    ORDER BY CASE d.status WHEN 'In Progress' THEN 0 WHEN 'Matched' THEN 1 WHEN 'Dream Achieved' THEN 2 ELSE 3 END, ds.created_at DESC
");
$stmt->execute($params);
$pairs = $stmt->fetchAll();

$categories = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support',
               'Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];
$catIcons   = ['Skills to Learn'=>'???','Creative Arts'=>'??','STEM Exploration'=>'??','Academic Support'=>'??',
               'Language Learning'=>'???','Music and Performance'=>'??','Technology and Coding'=>'??',
               'Competition Preparation'=>'??','Others'=>'?'];
$totalMatched    = $db->query("SELECT COUNT(*) FROM dream_support WHERE status='Approved'")->fetchColumn();
$inProgressCount = $db->query("SELECT COUNT(*) FROM dreams d JOIN dream_support ds ON ds.dream_id=d.id WHERE ds.status='Approved' AND d.status='In Progress'")->fetchColumn();
$achievedCount   = $db->query("SELECT COUNT(*) FROM dreams d JOIN dream_support ds ON ds.dream_id=d.id WHERE ds.status='Approved' AND d.status='Dream Achieved'")->fetchColumn();
$pendingAdoptions= $db->query("SELECT COUNT(*) FROM dream_support WHERE status='Pending'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.adm-wrap{display:flex;min-height:calc(100vh - 72px)}
.adm-sb{width:240px;flex-shrink:0;background:linear-gradient(180deg,#2D4A3E,#1E3329);color:#fff;padding:1.5rem 0;position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto}
.adm-sb-title{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.5rem 1.5rem 1rem;font-weight:600}
.sb-link{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.5rem;color:rgba(255,255,255,.75);text-decoration:none;font-size:.875rem;transition:all .2s;border-left:3px solid transparent}
.sb-link:hover{color:#fff;background:rgba(255,255,255,.08)}
.sb-link.act{color:#fff;background:rgba(255,255,255,.12);border-left-color:#E8A838}
.sb-ico{width:20px;text-align:center}
.sb-num{margin-left:auto;background:#E07058;color:#fff;border-radius:100px;font-size:.68rem;padding:.1rem .45rem;font-weight:700}
.adm-main{flex:1;padding:2rem;min-width:0;background:#F7F3ED}
.adm-hdr{margin-bottom:1.5rem}
.adm-hdr h1{font-size:1.6rem;color:#1E3329;margin:0 0 .2rem}
.adm-hdr p{color:#6B7280;font-size:.875rem;margin:0}
.stat-row{display:flex;gap:.85rem;flex-wrap:wrap;margin-bottom:1.5rem}
.stat-pill{background:#fff;border-radius:10px;padding:.7rem 1.1rem;text-align:center;border:1px solid #EEE8E0;flex:1;min-width:110px}
.stat-pill-n{font-size:1.5rem;font-weight:700;line-height:1;margin-bottom:.15rem}
.stat-pill-l{font-size:.7rem;color:#6B7280;text-transform:uppercase;letter-spacing:.04em}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center}
.filter-bar input,.filter-bar select{padding:.45rem .75rem;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff;color:#1F2937}
.pair-card{background:#fff;border-radius:14px;border:1px solid #EEE8E0;padding:1.35rem;margin-bottom:1rem;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:box-shadow .2s}
.pair-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.08)}
.pair-card.achieved{border-color:#86EFAC;background:linear-gradient(135deg,#F0FDF4,#fff)}
.pc-top{display:flex;gap:1rem;flex-wrap:wrap}
.pc-dream{flex:1;min-width:200px}
.pc-dream h3{font-size:.975rem;font-weight:700;color:#1E3329;margin:0 0 .25rem}
.pc-dream p{font-size:.8rem;color:#6B7280;margin:0 0 .5rem;line-height:1.5}
.chips{display:flex;gap:.3rem;flex-wrap:wrap}
.chip{background:#F3F4F6;border-radius:100px;padding:.15rem .5rem;font-size:.7rem;color:#4B5563;border:1px solid #E5E7EB}
.pc-supp{background:#F9F7F4;border-radius:10px;padding:.9rem;min-width:200px;width:215px;flex-shrink:0}
.pc-supp h4{font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:#9CA3AF;margin:0 0 .45rem;font-weight:600}
.sa-row{display:flex;align-items:center;gap:.45rem;margin-bottom:.4rem}
.sa-av{width:32px;height:32px;border-radius:50%;background:#5C8C6A;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;flex-shrink:0}
.sa-inf{font-size:.8rem;color:#1F2937;font-weight:500}
.sa-inf small{color:#6B7280;font-weight:400;font-size:.72rem;display:block}
.stype{display:inline-block;padding:.12rem .45rem;border-radius:100px;font-size:.68rem;font-weight:600;background:#D1FAE5;color:#065F46}
.divider{height:1px;background:#F3F0EA;margin:.85rem 0}
.progress-line{display:flex;align-items:center;gap:0;overflow-x:auto}
.ps{display:flex;align-items:center;gap:.3rem;padding:.3rem .6rem;border-radius:100px;font-size:.72rem;font-weight:600;white-space:nowrap}
.ps.done{color:#fff}
.ps.curr{color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.12)}
.ps.todo{background:#F3F4F6;color:#9CA3AF;border:1px dashed #D1D5DB}
.ps-arr{color:#D1D5DB;font-size:.68rem;padding:0 .1rem;flex-shrink:0}
.pc-foot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
.bxs{padding:.33rem .7rem;font-size:.77rem;border-radius:8px;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;transition:all .2s}
.bxs-grn{background:#059669;color:#fff}.bxs-grn:hover{background:#047857}
.bxs-grey{background:#F3F4F6;color:#374151;border:1px solid #E5E7EB}
.bdg{display:inline-block;padding:.15rem .5rem;border-radius:100px;font-size:.7rem;font-weight:600}
.bdg-mat{background:#EDE9FE;color:#5B21B6}
.bdg-inp{background:#FFF7ED;color:#C2410C}
.bdg-ach{background:#D1FAE5;color:#065F46}
.empty-box{text-align:center;padding:3rem 1rem;color:#6B7280;background:#fff;border-radius:14px;border:1px solid #EEE8E0}
.sb-toggle{display:none;position:fixed;bottom:1.5rem;right:1.5rem;width:50px;height:50px;background:#5C8C6A;color:#fff;border-radius:50%;border:none;font-size:1.2rem;cursor:pointer;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,.2);align-items:center;justify-content:center}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:49}
@media(max-width:900px){
  .adm-sb{position:fixed;left:-260px;top:0;height:100vh;z-index:50;width:260px;transition:left .3s}
  .adm-sb.open{left:0}
  .sb-toggle{display:flex}
  .sb-overlay.show{display:block}
  .adm-main{padding:1.1rem}
  .pc-supp{width:100%}
}
@media(max-width:600px){
  .adm-main{padding:.85rem}
  .pc-top{flex-direction:column}
  .pc-foot{flex-direction:column;align-items:flex-start}
}
</style>

<div class="adm-wrap">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="adm-main">
    <div class="adm-hdr">
      <h1>✅ Matched Pairs</h1>
      <p>All dreams successfully matched with a mentor or sponsor. Track and update their progress here.</p>
    </div>

    <div class="stat-row">
      <div class="stat-pill"><div class="stat-pill-n" style="color:#7C3AED"><?=$totalMatched?></div><div class="stat-pill-l">Total Matched</div></div>
      <div class="stat-pill"><div class="stat-pill-n" style="color:#EA580C"><?=$inProgressCount?></div><div class="stat-pill-l">In Progress</div></div>
      <div class="stat-pill"><div class="stat-pill-n" style="color:#059669"><?=$achievedCount?></div><div class="stat-pill-l">Achieved 🏆</div></div>
    </div>

    <form method="GET" class="filter-bar">
      <input type="text" name="search" placeholder="Search dream, supporter, guardian..." value="<?= e($search) ?>" style="flex:1;min-width:180px;max-width:280px">
      <select name="status">
        <option value="">All Progress</option>
        <option value="Matched" <?= $filterStatus==='Matched'?'selected':'' ?>>Matched</option>
        <option value="In Progress" <?= $filterStatus==='In Progress'?'selected':'' ?>>In Progress</option>
        <option value="Dream Achieved" <?= $filterStatus==='Dream Achieved'?'selected':'' ?>>Dream Achieved</option>
      </select>
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach($categories as $c): ?>
          <option value="<?= e($c) ?>" <?= $filterCategory===$c?'selected':'' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="<?= $base ?>/admin/matched_pairs.php" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <p style="font-size:.82rem;color:#6B7280;margin:0 0 1rem"><?= count($pairs) ?> pair<?= count($pairs)!==1?'s':'' ?> found</p>

    <?php if(empty($pairs)): ?>
      <div class="empty-box">
        <div style="font-size:2.5rem;margin-bottom:.5rem">🌱</div>
        <p style="font-weight:600;color:#374151;margin:.2rem 0">No matched pairs yet</p>
        <p style="font-size:.85rem;margin:0">Approve adoption requests to create matches.</p>
      </div>
    <?php else: ?>
      <?php foreach($pairs as $p):
        $icon = $catIcons[$p['category']] ?? '[Dream]';
        $isConfirmed = !empty($p['completion_confirmed_at']);
        $isRequested = !empty($p['completion_requested_at']);
        $cls = $p['dream_status']==='Dream Achieved' ? 'achieved' : '';
        $stepColors = ['Matched'=>'#7C3AED','In Progress'=>'#EA580C','Dream Achieved'=>'#059669'];
        $stepList = ['Matched','In Progress','Dream Achieved'];
        $currIdx = array_search($p['dream_status'], $stepList);
        if($currIdx===false) $currIdx=-1;
      ?>
      <div class="pair-card <?= $cls ?>">
        <div class="pc-top">
          <div class="pc-dream">
            <?php if($p['dream_status']==='Dream Achieved'): ?>
              <div style="font-size:.75rem;color:#059669;font-weight:700;margin-bottom:.3rem;letter-spacing:.04em">🏆 DREAM ACHIEVED!</div>
            <?php endif; ?>
            <h3><?= $icon ?> <?= e($p['title']) ?></h3>
            <p><?= e(mb_substr($p['description'],0,130)) ?><?= mb_strlen($p['description'])>130?'…':'' ?></p>
            <div class="chips">
              <span class="chip">📂 <?= e($p['category']) ?></span>
              <span class="chip">📍 <?= e($p['city']) ?></span>
              <span class="chip">🎂 <?= e($p['age_group']) ?></span>
              <span class="chip">💰 <?= e($p['budget_range']) ?></span>
              <span class="chip">👨‍👩‍👧 <?= e($p['guardian_name']) ?></span>
              <?php if($isConfirmed): ?>
              <span class="chip" style="background:#ECFDF5;border-color:#BBF7D0;color:#166534;">Completion confirmed</span>
              <?php elseif($isRequested): ?>
              <span class="chip" style="background:#FFFBEB;border-color:#FDE68A;color:#92400E;">Confirmation pending</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="pc-supp">
            <h4>Supporter</h4>
            <div class="sa-row">
              <div class="sa-av"><?= strtoupper(substr($p['supporter_name'],0,1)) ?></div>
              <div class="sa-inf"><?= e($p['supporter_name']) ?>
                <small><?= e($p['supporter_email']) ?></small>
                <?php if($p['profession']): ?><small>💼 <?= e($p['profession']) ?></small><?php endif; ?>
              </div>
            </div>
            <span class="stype">🤲 <?= e($p['support_type']) ?></span>
            <div style="font-size:.7rem;color:#6B7280;margin-top:.4rem">Matched <?= date('M j, Y', strtotime($p['matched_at'])) ?></div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="pc-foot">
          <div class="progress-line">
            <?php foreach($stepList as $i => $step):
              $done = $i < $currIdx; $curr = $i===$currIdx; ?>
              <?php if($i>0): ?><span class="ps-arr">→</span><?php endif; ?>
              <span class="ps <?= $done?'done':($curr?'curr':'todo') ?>"
                    style="<?= ($done||$curr)?'background:'.$stepColors[$step].';':'' ?>">
                <?= $done?'✓ ':'' ?><?= $step ?>
              </span>
            <?php endforeach; ?>
          </div>

          <?php if($p['dream_status'] !== 'Dream Achieved'): ?>
          <form method="POST" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="dream_id" value="<?= (int)$p['dream_id'] ?>">
            <select name="new_status" style="padding:.33rem .6rem;border:1px solid #D1D5DB;border-radius:8px;font-size:.78rem;background:#fff">
              <?php foreach(['Matched','In Progress'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $p['dream_status']===$s?'selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
              <?php if($isConfirmed): ?>
                <option value="Dream Achieved" <?= $p['dream_status']==='Dream Achieved'?'selected':'' ?>>Dream Achieved</option>
              <?php endif; ?>
            </select>
            <button type="submit" class="bxs bxs-grn">Update Progress</button>
          </form>
          <form method="POST" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="action" value="request_achievement_confirmation">
            <input type="hidden" name="dream_id" value="<?= (int)$p['dream_id'] ?>">
            <button type="submit" class="bxs bxs-grey"><?= $isRequested ? 'Resend confirmation email' : 'Send completion email' ?></button>
          </form>
          <?php else: ?>
            <span style="font-size:.8rem;color:#059669;font-weight:600">🎉 Completed!</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<button class="sb-toggle" onclick="toggleSb()">&#9776;</button>
<script>
function toggleSb(){document.getElementById('adSb').classList.toggle('open');document.getElementById('sbOv').classList.toggle('show');}
function closeSb(){document.getElementById('adSb').classList.remove('open');document.getElementById('sbOv').classList.remove('show');}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>



