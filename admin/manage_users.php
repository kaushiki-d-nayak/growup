<?php
// admin/manage_users.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

requireRole('admin');
$pageTitle = 'Manage Users';
$base = BASE_PATH;
$db   = getDB();

$filterRole = $_GET['role']   ?? '';
$search     = trim($_GET['search'] ?? '');

$where  = "WHERE u.role != 'admin'";
$params = [];
if ($filterRole && in_array($filterRole, ['guardian','supporter'])) { $where .= " AND u.role=?"; $params[] = $filterRole; }
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("
    SELECT u.*,
           CASE WHEN u.role='guardian'  THEN (SELECT COUNT(*) FROM students s WHERE s.guardian_id=u.id) ELSE 0 END AS student_count,
           CASE WHEN u.role='supporter' THEN (SELECT COUNT(*) FROM dream_support ds WHERE ds.supporter_id=u.id) ELSE 0 END AS adoption_count,
           sp.profession, sp.interest_area
    FROM users u LEFT JOIN supporters sp ON sp.user_id=u.id
    $where ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$totalGuardians  = $db->query("SELECT COUNT(*) FROM users WHERE role='guardian'")->fetchColumn();
$totalSupporters = $db->query("SELECT COUNT(*) FROM users WHERE role='supporter'")->fetchColumn();
$pendingDreams   = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Submitted'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.ap{display:flex;min-height:calc(100vh - 72px)}
.ap-nav{width:230px;flex-shrink:0;background:#1A2E25;padding:1.5rem 0 2rem;position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto;display:flex;flex-direction:column;transition:left .25s}
.ap-logo{font-family:'Fraunces',serif;font-size:.9rem;color:rgba(255,255,255,.4);padding:0 1.5rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:.5rem}
.apl{display:flex;align-items:center;gap:.65rem;padding:.62rem 1.5rem;color:rgba(255,255,255,.6);text-decoration:none;font-size:.86rem;font-weight:500;transition:all .18s;border-left:3px solid transparent}
.apl:hover{color:#fff;background:rgba(255,255,255,.07);border-left-color:rgba(255,255,255,.15)}
.apl.on{color:#fff;background:rgba(232,168,56,.13);border-left-color:#E8A838}
.apl .i{width:18px;text-align:center}
.apl .n{margin-left:auto;background:#E07058;color:#fff;border-radius:20px;font-size:.64rem;font-weight:700;padding:.08rem .38rem}
.ap-sep{border:none;border-top:1px solid rgba(255,255,255,.07);margin:.4rem 1.25rem}
.ap-main{flex:1;padding:2rem 2.5rem;background:#F5F0E8;min-width:0}
.ap-hdr{margin-bottom:1.5rem}
.ap-hdr h1{font-family:'Fraunces',serif;font-size:1.6rem;color:#1A2E25;margin:0 0 .2rem;font-weight:700}
.ap-hdr p{color:#7A7060;font-size:.875rem;margin:0}
.stat-row{display:flex;gap:.9rem;flex-wrap:wrap;margin-bottom:1.5rem}
.stat-pill{background:#fff;border-radius:12px;padding:.85rem 1.1rem;text-align:center;border:1px solid #E8E0D4;flex:1;min-width:120px}
.stat-pill-n{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:700;line-height:1;margin-bottom:.2rem}
.stat-pill-l{font-size:.67rem;color:#7A7060;text-transform:uppercase;letter-spacing:.06em;font-weight:600}
.fbar{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1.1rem}
.fbar input,.fbar select{padding:.42rem .75rem;border:1px solid #D6CFC4;border-radius:8px;font-size:.84rem;background:#fff;color:#1A2E25;outline:none}
.fbar input:focus,.fbar select:focus{border-color:#5C8C6A}
.rtabs{display:flex;gap:.3rem;margin-bottom:1.25rem;flex-wrap:wrap}
.rtab{padding:.38rem .85rem;border-radius:100px;border:1px solid #D6CFC4;background:#fff;font-size:.78rem;font-weight:500;cursor:pointer;color:#5C5447;text-decoration:none;transition:all .15s}
.rtab:hover{border-color:#5C8C6A;color:#5C8C6A}
.rtab.on{background:#1A2E25;color:#fff;border-color:#1A2E25}
.ap-card{background:#fff;border-radius:14px;border:1px solid #E8E0D4;padding:1.4rem;overflow-x:auto}
.ap-tbl{width:100%;border-collapse:collapse;font-size:.835rem;min-width:600px}
.ap-tbl th{padding:.55rem .85rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#7A7060;border-bottom:2px solid #F0EBE2;font-weight:600;white-space:nowrap}
.ap-tbl td{padding:.6rem .85rem;border-bottom:1px solid #F5F0E8;vertical-align:middle}
.ap-tbl tr:last-child td{border-bottom:none}
.ap-tbl tr:hover td{background:#FDFAF5}
.role-g{display:inline-flex;align-items:center;gap:.3rem;padding:.18rem .6rem;background:#EDE9FE;color:#5B21B6;border-radius:100px;font-size:.75rem;font-weight:600}
.role-s{display:inline-flex;align-items:center;gap:.3rem;padding:.18rem .6rem;background:#ECFDF5;color:#065F46;border-radius:100px;font-size:.75rem;font-weight:600}
.empty{text-align:center;padding:3rem 1rem;color:#7A7060}
.empty .ei{font-size:2.5rem;margin-bottom:.6rem}
.ap-tog{display:none;position:fixed;bottom:1.5rem;right:1.5rem;width:48px;height:48px;background:#5C8C6A;color:#fff;border-radius:50%;border:none;font-size:1.1rem;cursor:pointer;z-index:200;box-shadow:0 4px 14px rgba(0,0,0,.2);align-items:center;justify-content:center}
.ap-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99}
@media(max-width:900px){
  .ap-nav{position:fixed;left:-250px;top:0;height:100vh;z-index:100;width:250px}
  .ap-nav.open{left:0}
  .ap-tog{display:flex}
  .ap-ov.show{display:block}
  .ap-main{padding:1.25rem}
}
@media(max-width:580px){.ap-main{padding:.9rem}}
</style>

<div class="ap">
  <nav class="ap-nav" id="apNav">
    <div class="ap-logo">Before I Grow Up</div>
    <a href="<?= $base ?>/admin/dashboard.php" class="apl"><span class="i">📊</span> Dashboard</a>
    <a href="<?= $base ?>/admin/manage_dreams.php" class="apl">
      <span class="i">🌟</span> Manage Dreams
      <?php if($pendingDreams>0):?><span class="n"><?=$pendingDreams?></span><?php endif?>
    </a>
    <a href="<?= $base ?>/admin/manage_users.php" class="apl on"><span class="i">👥</span> Manage Users</a>
    <hr class="ap-sep">
    <a href="<?= $base ?>/supporter/browse_dreams.php" class="apl"><span class="i">🌐</span> View Site</a>
    <a href="<?= $base ?>/logout.php" class="apl" style="margin-top:auto"><span class="i">🚪</span> Logout</a>
  </nav>
  <div class="ap-ov" id="apOv" onclick="closeNav()"></div>

  <main class="ap-main">
    <div class="ap-hdr">
      <h1>👥 Manage Users</h1>
      <p><?= $totalGuardians ?> Guardians · <?= $totalSupporters ?> Supporters</p>
    </div>

    <div class="stat-row">
      <div class="stat-pill"><div class="stat-pill-n" style="color:#5B21B6"><?= $totalGuardians ?></div><div class="stat-pill-l">Guardians</div></div>
      <div class="stat-pill"><div class="stat-pill-n" style="color:#065F46"><?= $totalSupporters ?></div><div class="stat-pill-l">Supporters</div></div>
      <div class="stat-pill"><div class="stat-pill-n" style="color:#374151"><?= $totalGuardians + $totalSupporters ?></div><div class="stat-pill-l">Total Users</div></div>
    </div>

    <form method="GET" class="fbar">
      <input type="text" name="search" placeholder="Search name or email..." value="<?= e($search) ?>" style="flex:1;min-width:200px;max-width:300px">
      <select name="role">
        <option value="">All Roles</option>
        <option value="guardian"  <?= $filterRole==='guardian' ?'selected':''?>>Guardians</option>
        <option value="supporter" <?= $filterRole==='supporter'?'selected':''?>>Supporters</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <a href="<?= $base ?>/admin/manage_users.php" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <div class="rtabs">
      <a href="?" class="rtab <?= $filterRole===''?'on':''?>">All (<?= $totalGuardians+$totalSupporters ?>)</a>
      <a href="?role=guardian"  class="rtab <?= $filterRole==='guardian' ?'on':''?>">👨‍👩‍👧 Guardians (<?= $totalGuardians ?>)</a>
      <a href="?role=supporter" class="rtab <?= $filterRole==='supporter'?'on':''?>">💛 Supporters (<?= $totalSupporters ?>)</a>
    </div>

    <?php if (empty($users)): ?>
      <div class="empty">
        <div class="ei">👥</div>
        <p style="font-weight:600;color:#374151;margin:.2rem 0">No users found</p>
        <p style="font-size:.85rem;margin:0">Try a different search term.</p>
      </div>
    <?php else: ?>
    <div class="ap-card">
      <table class="ap-tbl">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Details</th>
            <th>Activity</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:#9CA3AF;font-size:.78rem"><?= (int)$u['id'] ?></td>
            <td><strong><?= e($u['name']) ?></strong></td>
            <td style="font-size:.82rem;color:#5C5447"><?= e($u['email']) ?></td>
            <td>
              <?php if ($u['role']==='guardian'): ?>
                <span class="role-g">👨‍👩‍👧 Guardian</span>
              <?php else: ?>
                <span class="role-s">💛 Supporter</span>
              <?php endif ?>
            </td>
            <td style="font-size:.79rem;color:#7A7060;max-width:180px">
              <?php if ($u['role']==='supporter'): ?>
                <?= $u['profession'] ? e($u['profession']) : '—' ?>
                <?php if($u['interest_area']): ?><br><span style="color:#9CA3AF">📌 <?= e($u['interest_area']) ?></span><?php endif?>
              <?php else: ?>
                <?= (int)$u['student_count'] ?> child<?= $u['student_count']!=1?'ren':'' ?> registered
              <?php endif ?>
            </td>
            <td style="font-size:.82rem">
              <?php if ($u['role']==='guardian'): ?>
                <span style="color:#5B21B6"><?= (int)$u['student_count'] ?> dream<?= $u['student_count']!=1?'s':''?> submitted</span>
              <?php else: ?>
                <span style="color:#065F46"><?= (int)$u['adoption_count'] ?> adoption<?= $u['adoption_count']!=1?'s':''?></span>
              <?php endif ?>
            </td>
            <td style="font-size:.79rem;color:#7A7060;white-space:nowrap"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </main>
</div>

<button class="ap-tog" onclick="openNav()">☰</button>
<script>
function openNav(){document.getElementById('apNav').classList.add('open');document.getElementById('apOv').classList.add('show')}
function closeNav(){document.getElementById('apNav').classList.remove('open');document.getElementById('apOv').classList.remove('show')}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>