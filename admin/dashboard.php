<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

requireRole('admin');
$pageTitle = 'Admin Dashboard';
$base = BASE_PATH;
$db   = getDB();
$adminSidebarActive = 'dashboard';

$totalDreams      = $db->query("SELECT COUNT(*) FROM dreams")->fetchColumn();
$pendingDreams    = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Submitted'")->fetchColumn();
$verifiedDreams   = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Verified'")->fetchColumn();
$matchedDreams    = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Matched'")->fetchColumn();
$inProgressDreams = $db->query("SELECT COUNT(*) FROM dreams WHERE status='In Progress'")->fetchColumn();
$achievedDreams   = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Dream Achieved'")->fetchColumn();
$totalGuardians   = $db->query("SELECT COUNT(*) FROM users WHERE role='guardian'")->fetchColumn();
$totalSupporters  = $db->query("SELECT COUNT(*) FROM users WHERE role='supporter'")->fetchColumn();
$pendingRequests  = $db->query("SELECT COUNT(*) FROM dream_support WHERE status='Pending'")->fetchColumn();

$pendingList = $db->query("
    SELECT ds.id AS req_id, ds.support_type, ds.created_at AS req_date,
           d.title AS dream_title,
           u.name AS supporter_name,
           gu.name AS guardian_name
    FROM dream_support ds
    JOIN dreams d ON ds.dream_id = d.id
    JOIN users u ON ds.supporter_id = u.id
    JOIN students s ON d.student_id = s.id
    JOIN users gu ON s.guardian_id = gu.id
    WHERE ds.status = 'Pending'
    ORDER BY ds.created_at ASC LIMIT 5
")->fetchAll();

$catStats = $db->query("SELECT category, COUNT(*) as cnt FROM dreams GROUP BY category ORDER BY cnt DESC")->fetchAll();

$recentDreams = $db->query("
    SELECT d.title, d.status, d.category, d.created_at, u.name AS guardian_name, s.city
    FROM dreams d JOIN students s ON d.student_id = s.id JOIN users u ON s.guardian_id = u.id
    ORDER BY d.created_at DESC LIMIT 8
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.ap{display:flex;min-height:calc(100vh - 72px)}
.adm-sb{width:230px;flex-shrink:0;background:#1A2E25;padding:1.5rem 0 2rem;position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto;display:flex;flex-direction:column;transition:left .25s}
.adm-sb-title{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.5rem 1.5rem 1rem;font-weight:600}
.sb-link{display:flex;align-items:center;gap:.65rem;padding:.62rem 1.5rem;color:rgba(255,255,255,.6);text-decoration:none;font-size:.86rem;font-weight:500;transition:all .18s;border-left:3px solid transparent}
.sb-link:hover{color:#fff;background:rgba(255,255,255,.07);border-left-color:rgba(255,255,255,.15)}
.sb-link.act{color:#fff;background:rgba(232,168,56,.13);border-left-color:#E8A838}
.sb-ico{width:18px;text-align:center}
.sb-num{margin-left:auto;background:#E07058;color:#fff;border-radius:20px;font-size:.64rem;font-weight:700;padding:.08rem .38rem}
.ap-sep{border:none;border-top:1px solid rgba(255,255,255,.07);margin:.4rem 1.25rem}
.ap-main{flex:1;padding:2rem 2.5rem;background:#F5F0E8;min-width:0}
.ap-hdr h1{font-family:'Fraunces',serif;font-size:1.75rem;color:#1A2E25;margin:0 0 .2rem;font-weight:700}
.ap-hdr p{color:#7A7060;font-size:.875rem;margin:0 0 1.75rem}
.alert-box{border-radius:14px;padding:1.2rem 1.4rem;margin-bottom:1.25rem}
.alert-amber{background:#FEFCE8;border:1.5px solid #FCD34D}
.alert-indigo{background:#EEF2FF;border:1.5px solid #818CF8}
.alert-top{display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap}
.alert-ico{font-size:1.5rem;line-height:1;padding-top:.1rem}
.alert-txt{flex:1;min-width:180px}
.alert-txt strong{display:block;font-size:.94rem;margin-bottom:.15rem}
.alert-amber .alert-txt strong{color:#78350F}
.alert-amber .alert-txt span{color:#92400E;font-size:.82rem}
.alert-indigo .alert-txt strong{color:#312E81}
.alert-indigo .alert-txt span{color:#4338CA;font-size:.82rem}
.req-list{margin-top:.9rem;display:flex;flex-direction:column;gap:.45rem}
.req-row{background:#fff;border-radius:9px;padding:.7rem 1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;border:1px solid #FDE68A}
.req-dream{font-weight:600;color:#1A2E25;font-size:.85rem;flex:1;min-width:140px}
.req-meta{font-size:.77rem;color:#6B7280;flex:1;min-width:140px;line-height:1.55}
.req-btns{display:flex;gap:.35rem;flex-shrink:0}
.bap{padding:.3rem .7rem;border-radius:7px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;transition:all .15s}
.bap-g{background:#059669;color:#fff}.bap-g:hover{background:#047857}
.bap-r{background:#DC2626;color:#fff}.bap-r:hover{background:#B91C1C}
.bap-o{background:#fff;color:#374151;border:1px solid #D1D5DB}.bap-o:hover{background:#F3F4F6}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:.9rem;margin-bottom:1.75rem}
.stat-box{display:block;background:#fff;border-radius:12px;padding:1rem;border:1px solid #E8E0D4;text-align:center;text-decoration:none;transition:all .16s}
.stat-box:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08);border-color:#D8CCBC}
.stat-box:focus-visible{outline:2px solid #5C8C6A;outline-offset:2px}
.stat-n{font-family:'Fraunces',serif;font-size:1.9rem;font-weight:700;line-height:1;margin-bottom:.2rem}
.stat-l{font-size:.67rem;color:#7A7060;text-transform:uppercase;letter-spacing:.06em;font-weight:600}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.4rem;margin-bottom:1.4rem}
.ap-card{background:#fff;border-radius:14px;border:1px solid #E8E0D4;padding:1.4rem}
.ap-card h3{font-family:'Fraunces',serif;font-size:1rem;color:#1A2E25;margin:0 0 1.1rem;font-weight:600;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap}
.bar-wrap{margin-bottom:.65rem}
.bar-label{display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.28rem;color:#374151}
.bar-label span:last-child{color:#7A7060}
.bar-track{background:#F0EBE2;border-radius:100px;height:6px;overflow:hidden}
.bar-fill{height:100%;border-radius:100px;background:linear-gradient(90deg,#5C8C6A,#E8A838)}
.ap-tbl{width:100%;border-collapse:collapse;font-size:.83rem}
.ap-tbl th{padding:.55rem .8rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#7A7060;border-bottom:2px solid #F0EBE2;font-weight:600;white-space:nowrap}
.ap-tbl td{padding:.55rem .8rem;border-bottom:1px solid #F5F0E8;vertical-align:middle}
.ap-tbl tr:last-child td{border-bottom:none}
.ap-tbl tr:hover td{background:#FDFAF5}
.b{display:inline-block;padding:.16rem .5rem;border-radius:100px;font-size:.69rem;font-weight:600;white-space:nowrap}
.b-sub{background:#FEF3C7;color:#92400E}.b-ver{background:#DBEAFE;color:#1D4ED8}
.b-mat{background:#EDE9FE;color:#6D28D9}.b-inp{background:#FFEDD5;color:#C2410C}.b-ach{background:#D1FAE5;color:#065F46}
.ap-tog{display:none;position:fixed;bottom:1.5rem;right:1.5rem;width:48px;height:48px;background:#5C8C6A;color:#fff;border-radius:50%;border:none;font-size:1.1rem;cursor:pointer;z-index:200;box-shadow:0 4px 14px rgba(0,0,0,.2);align-items:center;justify-content:center}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99}
@media(max-width:900px){
  .adm-sb{position:fixed;left:-250px;top:0;height:100vh;z-index:100;width:250px}
  .adm-sb.open{left:0}
  .ap-tog{display:flex}
  .sb-overlay.show{display:block}
  .ap-main{padding:1.25rem}
  .two-col{grid-template-columns:1fr}
}
@media(max-width:560px){
  .ap-main{padding:.9rem}
  .stat-grid{grid-template-columns:repeat(2,1fr)}
  .req-row{flex-direction:column;align-items:flex-start}
}
</style>

<div class="ap">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="ap-main">
    <div class="ap-hdr">
      <h1>👋 Welcome, <?= e($_SESSION['name']) ?></h1>
      <p><?= date('l, F j, Y') ?></p>
    </div>

    <?php if ($pendingRequests > 0): ?>
    <div class="alert-box alert-amber">
      <div class="alert-top">
        <span class="alert-ico">🔔</span>
        <div class="alert-txt">
          <strong><?= $pendingRequests ?> Supporter Request<?= $pendingRequests>1?'s':'' ?> Awaiting Review</strong>
          <span>A supporter wants to adopt a dream. Review requests in the Adoptions panel.</span>
        </div>
        <a href="<?= $base ?>/admin/manage_adoptions.php?status=Pending" class="btn btn-amber" style="flex-shrink:0;white-space:nowrap">Review →</a>
      </div>
      <?php if (!empty($pendingList)): ?>
      <div class="req-list">
        <?php foreach ($pendingList as $r): ?>
        <div class="req-row">
          <div class="req-dream">🌟 <?= e(mb_substr($r['dream_title'],0,40)) ?><?= mb_strlen($r['dream_title'])>40?'…':''?></div>
          <div class="req-meta">
            <strong><?= e($r['supporter_name']) ?></strong> · <?= e($r['support_type']) ?><br>
            Guardian: <?= e($r['guardian_name']) ?> · <?= date('M j, g:ia', strtotime($r['req_date'])) ?>
          </div>
          <div class="req-btns">
            <form method="POST" action="<?= $base ?>/admin/manage_adoptions.php" style="display:inline">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="support_id" value="<?= (int)$r['req_id'] ?>">
              <button type="submit" class="bap bap-g">✅ Approve</button>
            </form>
            <form method="POST" action="<?= $base ?>/admin/manage_adoptions.php" style="display:inline">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="support_id" value="<?= (int)$r['req_id'] ?>">
              <button type="submit" class="bap bap-r">✕ Decline</button>
            </form>
          </div>
        </div>
        <?php endforeach ?>
        <?php if ($pendingRequests > 5): ?>
          <a href="<?= $base ?>/admin/manage_adoptions.php?status=Pending" style="font-size:.78rem;color:#92400E;display:inline-block;padding:.1rem 0">+ <?= $pendingRequests-5 ?> more — view all in Adoptions →</a>
        <?php endif ?>
      </div>
      <?php endif ?>
    </div>
    <?php endif ?>

    <?php if ($pendingDreams > 0): ?>
    <div class="alert-box alert-indigo">
      <div class="alert-top">
        <span class="alert-ico">📋</span>
        <div class="alert-txt">
          <strong><?= $pendingDreams ?> Dream<?= $pendingDreams>1?'s':'' ?> Need Verification</strong>
          <span>Guardians submitted new dreams. Verify them so supporters can discover and adopt them.</span>
        </div>
        <a href="<?= $base ?>/admin/manage_dreams.php?filter=Submitted" class="btn btn-primary" style="flex-shrink:0;white-space:nowrap">Verify →</a>
      </div>
    </div>
    <?php endif ?>

    <div class="stat-grid">
      <a href="<?= $base ?>/admin/manage_dreams.php" class="stat-box"><div class="stat-n" style="color:#5C8C6A"><?= $totalDreams ?></div><div class="stat-l">Total Dreams</div></a>
      <a href="<?= $base ?>/admin/manage_dreams.php?filter=Submitted" class="stat-box"><div class="stat-n" style="color:#D97706"><?= $pendingDreams ?></div><div class="stat-l">Pending Verify</div></a>
      <a href="<?= $base ?>/admin/manage_dreams.php?filter=Verified" class="stat-box"><div class="stat-n" style="color:#1D4ED8"><?= $verifiedDreams ?></div><div class="stat-l">Verified</div></a>
      <a href="<?= $base ?>/admin/manage_dreams.php?filter=Matched" class="stat-box"><div class="stat-n" style="color:#6D28D9"><?= $matchedDreams ?></div><div class="stat-l">Matched</div></a>
      <a href="<?= $base ?>/admin/manage_dreams.php?filter=In+Progress" class="stat-box"><div class="stat-n" style="color:#C2410C"><?= $inProgressDreams ?></div><div class="stat-l">In Progress</div></a>
      <a href="<?= $base ?>/admin/manage_dreams.php?filter=Dream+Achieved" class="stat-box"><div class="stat-n" style="color:#065F46"><?= $achievedDreams ?></div><div class="stat-l">Achieved</div></a>
      <a href="<?= $base ?>/admin/manage_users.php?role=guardian" class="stat-box"><div class="stat-n" style="color:#5C8C6A"><?= $totalGuardians ?></div><div class="stat-l">Guardians</div></a>
      <a href="<?= $base ?>/admin/manage_users.php?role=supporter" class="stat-box"><div class="stat-n" style="color:#E8A838"><?= $totalSupporters ?></div><div class="stat-l">Supporters</div></a>
    </div>

    <div class="two-col">
      <div class="ap-card">
        <h3>📊 Dreams by Category</h3>
        <?php if (empty($catStats)): ?>
          <p style="color:#7A7060;font-size:.85rem">No data yet.</p>
        <?php else: foreach ($catStats as $row):
          $pct = $totalDreams>0 ? round(($row['cnt']/$totalDreams)*100) : 0; ?>
          <div class="bar-wrap">
            <div class="bar-label"><span><?= e($row['category']) ?></span><span><?= $row['cnt'] ?> · <?= $pct ?>%</span></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
          </div>
        <?php endforeach; endif ?>
      </div>

      <div class="ap-card">
        <h3>⚡ Quick Actions</h3>
        <div style="display:flex;flex-direction:column;gap:.7rem">
          <a href="<?= $base ?>/admin/manage_dreams.php?filter=Submitted" class="btn btn-amber">📋 Verify Pending (<?= $pendingDreams ?>)</a>
          <a href="<?= $base ?>/admin/manage_adoptions.php?status=Pending" class="btn btn-primary">🤝 Supporter Requests (<?= $pendingRequests ?>)</a>
          <a href="<?= $base ?>/admin/manage_dreams.php?filter=In+Progress" class="btn btn-outline">🚀 In-Progress Dreams</a>
          <a href="<?= $base ?>/admin/manage_users.php" class="btn btn-outline">👥 All Users</a>
        </div>
      </div>
    </div>

    <div class="ap-card">
      <h3>🕐 Recent Dream Submissions
        <a href="<?= $base ?>/admin/manage_dreams.php" class="bap bap-o">View All →</a>
      </h3>
      <?php if (empty($recentDreams)): ?>
        <p style="color:#7A7060;font-size:.85rem">No dreams yet.</p>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="ap-tbl">
          <thead><tr><th>Title</th><th>Category</th><th>Guardian</th><th>City</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php $sc=['Submitted'=>'b-sub','Verified'=>'b-ver','Matched'=>'b-mat','In Progress'=>'b-inp','Dream Achieved'=>'b-ach'];
          foreach ($recentDreams as $d): ?>
            <tr>
              <td><strong><?= e(mb_substr($d['title'],0,36)) ?><?= mb_strlen($d['title'])>36?'…':''?></strong></td>
              <td style="color:#7A7060;font-size:.77rem"><?= e($d['category']) ?></td>
              <td><?= e($d['guardian_name']) ?></td>
              <td><?= e($d['city']) ?></td>
              <td><span class="b <?= $sc[$d['status']]??'b-sub' ?>"><?= e($d['status']) ?></span></td>
              <td style="color:#7A7060;font-size:.77rem"><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php endif ?>
    </div>
  </main>
</div>

<button class="ap-tog" onclick="toggleSb()">&#9776;</button>
<script>
function toggleSb(){document.getElementById('adSb').classList.toggle('open');document.getElementById('sbOv').classList.toggle('show')}
function closeSb(){document.getElementById('adSb').classList.remove('open');document.getElementById('sbOv').classList.remove('show')}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

