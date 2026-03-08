<?php
// admin/manage_adoptions.php
// CORRECT FLOW:
//   Supporter applies  → dream_support.status='Pending',  dream.status stays 'Verified'
//   Admin APPROVES     → dream_support.status='Approved', dream.status → 'Matched'
//   Admin REJECTS supp → dream_support.status='Rejected', dream.status → back to 'Verified'
//                        Dream stays OPEN for other supporters to apply

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/mail.php';
requireRole('admin');
$pageTitle = 'Manage Adoptions';
$base = BASE_PATH;
$db   = getDB();
$adminSidebarActive = 'adoptions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sid    = (int)($_POST['support_id'] ?? 0);

    if ($action === 'approve' && $sid) {
        $db->prepare("UPDATE dream_support SET status='Approved', rejection_reason=NULL WHERE id=?")->execute([$sid]);
        $r = $db->prepare("SELECT dream_id FROM dream_support WHERE id=?"); $r->execute([$sid]);
        $did = $r->fetchColumn();
        if ($did) {
            // Dream → Matched
            $db->prepare("UPDATE dreams SET status='Matched' WHERE id=? AND status NOT IN ('In Progress','Dream Achieved')")->execute([$did]);
            // Auto-reject other PENDING requests for same dream
            $db->prepare("UPDATE dream_support SET status='Rejected', rejection_reason='Another supporter was selected for this dream.' WHERE dream_id=? AND id!=? AND status='Pending'")->execute([$did, $sid]);
        }

        // Notify approved supporter with guardian contact
        $mailStmt = $db->prepare("
            SELECT ds.support_type,
                   d.title AS dream_title,
                   su.name AS supporter_name,
                   su.email AS supporter_email,
                   gu.name AS guardian_name,
                   gu.email AS guardian_email
            FROM dream_support ds
            JOIN dreams d ON ds.dream_id = d.id
            JOIN users su ON ds.supporter_id = su.id
            JOIN students s ON d.student_id = s.id
            JOIN users gu ON s.guardian_id = gu.id
            WHERE ds.id = ?
            LIMIT 1
        ");
        $mailStmt->execute([$sid]);
        $m = $mailStmt->fetch();
        if ($m && !empty($m['supporter_email'])) {
            $supportType = strtolower((string)$m['support_type']);
            $subject = 'Your adoption is approved - proceed with ' . $supportType;
            $body = '<p>Hi ' . e($m['supporter_name']) . ',</p>'
                  . '<p>Your adoption request has been <strong>approved</strong> for this dream:</p>'
                  . '<p><strong>' . e($m['dream_title']) . '</strong></p>'
                  . '<p>You can now proceed with the <strong>' . e($m['support_type']) . '</strong>.</p>'
                  . '<p>Guardian contact details:</p>'
                  . '<p>Name: <strong>' . e($m['guardian_name']) . '</strong><br>'
                  . 'Email: <a href="mailto:' . e($m['guardian_email']) . '">' . e($m['guardian_email']) . '</a></p>'
                  . '<p>With care,<br>' . APP_NAME . ' team</p>';
            if (!sendEmail($m['supporter_email'], $subject, $body)) {
                error_log('Supporter approval email failed for support_id: ' . $sid);
            }
        }

        $db->prepare("INSERT INTO admin_logs(admin_id,action) VALUES(?,?)")->execute([$_SESSION['user_id'], "Approved supporter #$sid for dream #$did"]);
        setFlash('success', '✅ Supporter approved! Dream is now Matched.');
        redirect($base . '/admin/manage_adoptions.php');
    }

    if ($action === 'reject' && $sid) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (!$reason) $reason = 'No specific reason provided.';
        $db->prepare("UPDATE dream_support SET status='Rejected', rejection_reason=? WHERE id=?")->execute([$reason, $sid]);
        $r = $db->prepare("SELECT dream_id FROM dream_support WHERE id=?"); $r->execute([$sid]);
        $did = $r->fetchColumn();
        if ($did) {
            // Check if dream still has any other active (Approved/Pending) supporter
            $still = $db->prepare("SELECT COUNT(*) FROM dream_support WHERE dream_id=? AND id!=? AND status IN ('Approved','Pending')");
            $still->execute([$did, $sid]);
            if ($still->fetchColumn() == 0) {
                // No other supporters — revert dream back to Verified so it stays open
                $db->prepare("UPDATE dreams SET status='Verified' WHERE id=? AND status='Matched'")->execute([$did]);
            }
        }
        $db->prepare("INSERT INTO admin_logs(admin_id,action) VALUES(?,?)")->execute([$_SESSION['user_id'], "Rejected supporter #$sid. Reason: $reason"]);
        setFlash('info', 'Supporter rejected. Dream reverted to Verified and remains open.');
        redirect($base . '/admin/manage_adoptions.php');
    }
}

$filterStatus  = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');
$valid         = ['Pending','Approved','Rejected'];
$where = "WHERE 1=1"; $params = [];
if ($filterStatus && in_array($filterStatus, $valid)) { $where .= " AND ds.status=?"; $params[] = $filterStatus; }
if ($search) { $where .= " AND (d.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("
    SELECT ds.id AS support_id, ds.support_type, ds.status AS support_status,
           ds.created_at AS applied_at, ds.rejection_reason,
           d.id AS dream_id, d.title AS dream_title, d.category, d.status AS dream_status, d.budget_range,
           u.name AS supporter_name, u.email AS supporter_email,
           sp.profession, sp.interest_area,
           s.city, s.age_group,
           gu.name AS guardian_name
    FROM dream_support ds
    JOIN dreams d ON ds.dream_id=d.id
    JOIN users u ON ds.supporter_id=u.id
    LEFT JOIN supporters sp ON sp.user_id=u.id
    JOIN students s ON d.student_id=s.id
    JOIN users gu ON s.guardian_id=gu.id
    $where
    ORDER BY CASE ds.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END, ds.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Group by dream
$grouped = [];
foreach ($rows as $a) {
    $did = $a['dream_id'];
    if (!isset($grouped[$did])) {
        $grouped[$did] = ['dream' => $a, 'supporters' => []];
    }
    $grouped[$did]['supporters'][] = $a;
}

$cntPending  = $db->query("SELECT COUNT(*) FROM dream_support WHERE status='Pending'")->fetchColumn();
$cntApproved = $db->query("SELECT COUNT(*) FROM dream_support WHERE status='Approved'")->fetchColumn();
$cntRejected = $db->query("SELECT COUNT(*) FROM dream_support WHERE status='Rejected'")->fetchColumn();
$pendingDreams = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Submitted'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.adm-wrap{display:flex;min-height:calc(100vh - 72px)}
.adm-sb{width:240px;flex-shrink:0;background:linear-gradient(180deg,#2D4A3E,#1E3329);color:#fff;padding:1.5rem 0;position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto;transition:left .3s}
.adm-sb-title{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.5rem 1.5rem 1rem;font-weight:600}
.sb-link{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.5rem;color:rgba(255,255,255,.75);text-decoration:none;font-size:.875rem;transition:all .2s;border-left:3px solid transparent}
.sb-link:hover{color:#fff;background:rgba(255,255,255,.08)}
.sb-link.act{color:#fff;background:rgba(255,255,255,.12);border-left-color:#E8A838}
.sb-ico{width:20px;text-align:center;font-size:1rem}
.sb-num{margin-left:auto;background:#E07058;color:#fff;border-radius:100px;font-size:.68rem;padding:.1rem .45rem;font-weight:700}
.adm-main{flex:1;padding:2rem;min-width:0;background:#F7F3ED}
.adm-hdr{margin-bottom:1.5rem}
.adm-hdr h1{font-size:1.6rem;color:#1E3329;margin:0 0 .25rem}
.adm-hdr p{color:#6B7280;font-size:.85rem;margin:0;line-height:1.5}
.stat-row{display:flex;gap:.85rem;flex-wrap:wrap;margin-bottom:1.4rem}
.sp{background:#fff;border-radius:10px;padding:.7rem 1.1rem;text-align:center;border:1px solid #EEE8E0;flex:1;min-width:100px}
.sp-n{font-size:1.5rem;font-weight:700;line-height:1;margin-bottom:.15rem}
.sp-l{font-size:.7rem;color:#6B7280;text-transform:uppercase;letter-spacing:.04em}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center}
.filter-bar input{padding:.45rem .75rem;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff;color:#1F2937;flex:1;min-width:200px;max-width:340px}
.tab-pills{display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:1.2rem}
.tp{padding:.42rem .9rem;border-radius:100px;border:1px solid #D1D5DB;background:#fff;font-size:.8rem;font-weight:500;text-decoration:none;display:inline-block;color:#374151;transition:all .2s}
.tp.all{background:#5C8C6A;color:#fff;border-color:#5C8C6A}
.tp.warn{background:#F59E0B;color:#fff;border-color:#F59E0B}
.tp.grn{background:#059669;color:#fff;border-color:#059669}
.tp.red{background:#DC2626;color:#fff;border-color:#DC2626}
.dream-group{background:#fff;border-radius:14px;border:1px solid #EEE8E0;margin-bottom:1.1rem;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.dg-hdr{padding:1rem 1.35rem;border-bottom:2px solid #F3F0EA;display:flex;gap:.75rem;align-items:flex-start;flex-wrap:wrap;background:#FAFAF8}
.dg-hdr h3{font-size:.975rem;font-weight:700;color:#1E3329;margin:0 0 .25rem}
.dg-meta{display:flex;gap:.5rem;flex-wrap:wrap;font-size:.75rem;color:#6B7280}
.chips{display:flex;gap:.3rem;flex-wrap:wrap;margin-bottom:.35rem}
.chip{background:#F3F4F6;border-radius:100px;padding:.15rem .5rem;font-size:.7rem;color:#4B5563;border:1px solid #E5E7EB}
.bdg{display:inline-block;padding:.17rem .52rem;border-radius:100px;font-size:.7rem;font-weight:600;white-space:nowrap}
.bdg-Submitted{background:#FEF3C7;color:#92400E}
.bdg-Verified{background:#DBEAFE;color:#1E40AF}
.bdg-Matched{background:#EDE9FE;color:#5B21B6}
.bdg-In-Progress{background:#FFF7ED;color:#C2410C}
.bdg-Dream-Achieved{background:#D1FAE5;color:#065F46}
.bdg-Pending{background:#FEF3C7;color:#92400E}
.bdg-Approved{background:#D1FAE5;color:#065F46}
.bdg-Rejected{background:#FEE2E2;color:#991B1B}
.supp-row{padding:.9rem 1.35rem;display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;border-bottom:1px solid #F9F7F4}
.supp-row:last-child{border-bottom:none}
.supp-row.pend{background:#FFFBF5}
.supp-row.rej{background:#FAFAFA;opacity:.8}
.supp-av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;font-weight:700;flex-shrink:0;margin-top:.1rem}
.supp-detail{flex:1;min-width:160px}
.supp-detail strong{display:block;font-size:.875rem;color:#1E3329}
.supp-detail small{font-size:.75rem;color:#6B7280;display:block;line-height:1.4}
.rej-reason-box{background:#FEF2F2;border-left:3px solid #DC2626;border-radius:0 8px 8px 0;padding:.45rem .7rem;margin-top:.4rem;font-size:.75rem;color:#B91C1C;line-height:1.5}
.rej-reason-box b{display:block;font-size:.68rem;color:#991B1B;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.1rem}
.supp-actions{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;padding-top:.15rem}
.btn-app{background:#059669;color:#fff;border:none;padding:.38rem .85rem;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .2s}
.btn-app:hover{background:#047857}
.btn-rej{background:#fff;color:#DC2626;border:1px solid #FECACA;padding:.38rem .85rem;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .2s}
.btn-rej:hover{background:#FEF2F2;border-color:#DC2626}
.notif-dot{display:inline-block;width:7px;height:7px;background:#E07058;border-radius:50%;margin-left:.3rem;vertical-align:middle;animation:blink 1.5s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;padding:1rem}
.modal-bg.open{display:flex}
.modal-box{background:#fff;border-radius:18px;padding:2rem;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:su .25s ease}
@keyframes su{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.modal-box .mi{font-size:2.5rem;text-align:center;margin-bottom:.6rem}
.modal-box h3{font-size:1.1rem;font-weight:700;color:#1E3329;text-align:center;margin:0 0 .3rem}
.modal-sub{font-size:.85rem;color:#6B7280;text-align:center;margin:0 0 1rem;line-height:1.5}
.modal-who{background:#FEF3C7;border-radius:8px;padding:.55rem .9rem;font-size:.85rem;font-weight:600;color:#92400E;margin-bottom:.85rem;text-align:center}
.modal-box label{display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.35rem}
.modal-box textarea{width:100%;border:1px solid #D1D5DB;border-radius:10px;padding:.7rem;font-size:.875rem;resize:vertical;min-height:90px;font-family:inherit;line-height:1.5;box-sizing:border-box;transition:border-color .2s}
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
@media(max-width:900px){.adm-sb{position:fixed;left:-260px;top:0;height:100vh;z-index:50;width:260px}.adm-sb.open{left:0}.sb-toggle{display:flex}.sb-overlay.show{display:block}.adm-main{padding:1.1rem}}
@media(max-width:600px){.adm-main{padding:.85rem}.supp-row{flex-direction:column}}
</style>

<!-- Reject Modal -->
<div class="modal-bg" id="rejectModal">
  <div class="modal-box">
    <div class="mi">🚫</div>
    <h3>Reject This Supporter</h3>
    <p class="modal-sub">Only this <strong>supporter's request</strong> will be rejected.<br>The <strong>dream stays Verified</strong> and remains open for others.</p>
    <div class="modal-who" id="modalWho">—</div>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="support_id" id="modalSid" value="">
      <label>Quick reason:</label>
      <div class="qreasons">
        <span class="qr" onclick="setR('Already matched with another supporter.')">Already matched</span>
        <span class="qr" onclick="setR('Support type does not match this dream\'s needs.')">Type mismatch</span>
        <span class="qr" onclick="setR('Your profile does not align with this dream\'s requirements.')">Profile mismatch</span>
        <span class="qr" onclick="setR('The guardian has selected a different supporter.')">Different supporter chosen</span>
        <span class="qr" onclick="setR('Please complete your supporter profile before applying.')">Incomplete profile</span>
        <span class="qr" onclick="setR('This dream requires a local mentor — your location does not match.')">Location mismatch</span>
      </div>
      <label for="rejReason">Reason <span style="color:#DC2626">*</span></label>
      <textarea name="rejection_reason" id="rejReason" placeholder="Be clear and constructive — the supporter will see this on their dashboard..." required></textarea>
      <div class="modal-ft">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-confirm">🚫 Reject Supporter</button>
      </div>
    </form>
  </div>
</div>

<div class="adm-wrap">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="adm-main">
    <div class="adm-hdr">
      <h1>🤝 Adoption Requests</h1>
      <p>Each card shows a dream and all supporters who applied for it.<br>
         Approving one supporter matches them with the dream. Rejecting a supporter keeps the dream open for others.</p>
    </div>

    <div class="stat-row">
      <div class="sp"><div class="sp-n" style="color:#D97706"><?=$cntPending?></div><div class="sp-l">Pending</div></div>
      <div class="sp"><div class="sp-n" style="color:#059669"><?=$cntApproved?></div><div class="sp-l">Approved</div></div>
      <div class="sp"><div class="sp-n" style="color:#DC2626"><?=$cntRejected?></div><div class="sp-l">Rejected</div></div>
    </div>

    <form method="GET" class="filter-bar">
      <input type="text" name="search" placeholder="Search by dream title, supporter name or email..." value="<?= e($search) ?>">
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <a href="<?= $base ?>/admin/manage_adoptions.php" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <div class="tab-pills">
      <a href="?" class="tp <?= $filterStatus===''?'all':'' ?>">All</a>
      <a href="?status=Pending" class="tp <?= $filterStatus==='Pending'?'warn':'' ?>">
        🔔 Pending (<?=$cntPending?>)<?php if($cntPending>0):?><span class="notif-dot"></span><?php endif;?>
      </a>
      <a href="?status=Approved" class="tp <?= $filterStatus==='Approved'?'grn':'' ?>">✅ Approved (<?=$cntApproved?>)</a>
      <a href="?status=Rejected" class="tp <?= $filterStatus==='Rejected'?'red':'' ?>">❌ Rejected (<?=$cntRejected?>)</a>
    </div>

    <?php if(empty($grouped)): ?>
      <div style="text-align:center;padding:3rem 1rem;background:#fff;border-radius:14px;border:1px solid #EEE8E0;color:#6B7280">
        <div style="font-size:2.5rem;margin-bottom:.5rem">🤝</div>
        <p style="font-weight:600;color:#374151;margin:.2rem 0">No adoption requests found</p>
        <p style="font-size:.85rem;margin:0">Try adjusting your filters.</p>
      </div>
    <?php else: ?>
      <?php foreach($grouped as $gDreamId => $g):
        $dream = $g['dream'];
        $supporters = $g['supporters'];
        $pendingCount = count(array_filter($supporters, fn($s) => $s['support_status']==='Pending'));
      ?>
      <div class="dream-group">
        <!-- Dream header -->
        <div class="dg-hdr">
          <div style="flex:1">
            <div class="chips">
              <span class="chip">📂 <?= e($dream['category']) ?></span>
              <span class="bdg bdg-<?= str_replace(' ','-',$dream['dream_status']) ?>"><?= e($dream['dream_status']) ?></span>
              <?php if($pendingCount): ?><span class="bdg bdg-Pending">⏳ <?=$pendingCount?> pending</span><?php endif; ?>
            </div>
            <h3><?= e($dream['dream_title']) ?></h3>
            <div class="dg-meta">
              <span>👨‍👩‍👧 <?= e($dream['guardian_name']) ?></span>
              <span>📍 <?= e($dream['city']) ?></span>
              <span>🎂 Age <?= e($dream['age_group']) ?></span>
              <span>💰 <?= e($dream['budget_range']) ?></span>
            </div>
          </div>
          <div style="font-size:.8rem;color:#6B7280;flex-shrink:0"><?= count($supporters) ?> application<?= count($supporters)!==1?'s':'' ?></div>
        </div>

        <!-- Supporter rows -->
        <?php foreach($supporters as $s):
          $isPend = $s['support_status']==='Pending';
          $isApp  = $s['support_status']==='Approved';
          $isRej  = $s['support_status']==='Rejected';
          $avBg   = $isApp ? '#059669' : ($isRej ? '#9CA3AF' : '#5C8C6A');
          $rowCls = $isPend?'pend':($isRej?'rej':'');
        ?>
        <div class="supp-row <?= $rowCls ?>">
          <div class="supp-av" style="background:<?= $avBg ?>"><?= strtoupper(substr($s['supporter_name'],0,1)) ?></div>
          <div class="supp-detail">
            <strong><?= e($s['supporter_name']) ?></strong>
            <small><?= e($s['supporter_email']) ?></small>
            <?php if($s['profession']): ?><small>💼 <?= e($s['profession']) ?></small><?php endif; ?>
            <?php if($s['interest_area']): ?><small>📌 Interests: <?= e($s['interest_area']) ?></small><?php endif; ?>
            <small>🤲 <strong><?= e($s['support_type']) ?></strong> · Applied <?= date('M j, Y', strtotime($s['applied_at'])) ?></small>
            <?php if($isRej && $s['rejection_reason']): ?>
            <div class="rej-reason-box">
              <b>Rejection reason sent to supporter:</b>
              <?= e($s['rejection_reason']) ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="supp-actions">
            <?php if($isPend): ?>
              <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="support_id" value="<?= (int)$s['support_id'] ?>">
                <button type="submit" class="btn-app">✅ Approve</button>
              </form>
              <button class="btn-rej" onclick="openModal(<?=(int)$s['support_id']?>, <?= htmlspecialchars(json_encode($s['supporter_name'].' → '.$dream['dream_title']), ENT_QUOTES) ?>)">🚫 Reject</button>
            <?php elseif($isApp): ?>
              <span style="font-size:.78rem;color:#059669;font-weight:600">✅ Approved</span>
            <?php else: ?>
              <span style="font-size:.78rem;color:#9CA3AF">❌ Rejected</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<button class="sb-toggle" onclick="toggleSb()">&#9776;</button>
<script>
function openModal(sid, who) {
  document.getElementById('modalSid').value = sid;
  document.getElementById('modalWho').textContent = '🤝 ' + who;
  document.getElementById('rejReason').value = '';
  document.getElementById('rejectModal').classList.add('open');
  setTimeout(() => document.getElementById('rejReason').focus(), 100);
}
function closeModal(){ document.getElementById('rejectModal').classList.remove('open'); }
function setR(t){ document.getElementById('rejReason').value = t; document.getElementById('rejReason').focus(); }
document.getElementById('rejectModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
function toggleSb(){document.getElementById('adSb').classList.toggle('open');document.getElementById('sbOv').classList.toggle('show');}
function closeSb(){document.getElementById('adSb').classList.remove('open');document.getElementById('sbOv').classList.remove('show');}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

