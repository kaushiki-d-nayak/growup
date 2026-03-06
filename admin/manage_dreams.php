<?php
// ============================================================
// admin/manage_dreams.php — Approve, reject, and update dreams
// Place this file in: /before-i-grow-up/admin/manage_dreams.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

requireRole('admin');

$pageTitle = 'Manage Dreams';
$base = BASE_PATH;
$db   = getDB();

// ── Handle Status Update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dream_id'], $_POST['new_status'])) {
    $dreamId   = (int)$_POST['dream_id'];
    $newStatus = $_POST['new_status'];
    $validStatuses = ['Submitted','Verified','Matched','In Progress','Dream Achieved'];

    if (in_array($newStatus, $validStatuses)) {
        $upd = $db->prepare("UPDATE dreams SET status = ? WHERE id = ?");
        $upd->execute([$newStatus, $dreamId]);

        // Log the action
        $action = "Changed dream #$dreamId status to '$newStatus'";
        $log = $db->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        $log->execute([$_SESSION['user_id'], $action]);

        setFlash('success', 'Dream status updated to "' . $newStatus . '".');
    } else {
        setFlash('error', 'Invalid status provided.');
    }
    redirect($base . '/admin/manage_dreams.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
}

// ── Filters ─────────────────────────────────────────────────
$filterStatus   = $_GET['filter']   ?? '';
$filterCategory = $_GET['category'] ?? '';
$search         = trim($_GET['search'] ?? '');

$where  = "WHERE 1=1";
$params = [];

$validStatuses = ['Submitted','Verified','Matched','In Progress','Dream Achieved'];
if ($filterStatus && in_array($filterStatus, $validStatuses)) {
    $where   .= " AND d.status = ?";
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where   .= " AND d.category = ?";
    $params[] = $filterCategory;
}
if ($search) {
    $where   .= " AND (d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$dreams = $db->prepare("
    SELECT d.*, s.city, s.age_group, u.name AS guardian_name, u.email AS guardian_email,
           (SELECT COUNT(*) FROM dream_support ds WHERE ds.dream_id = d.id) AS support_count
    FROM dreams d
    JOIN students s ON d.student_id = s.id
    JOIN users u ON s.guardian_id = u.id
    $where
    ORDER BY
        CASE d.status WHEN 'Submitted' THEN 0 ELSE 1 END,
        d.created_at DESC
");
$dreams->execute($params);
$dreams = $dreams->fetchAll();

$categories = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support',
               'Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-title">Admin Panel</div>
        <nav>
            <a href="<?= $base ?>/admin/dashboard.php"      class="sidebar-link"><span class="sidebar-icon">📊</span> Dashboard</a>
            <a href="<?= $base ?>/admin/manage_dreams.php"  class="sidebar-link active"><span class="sidebar-icon">🌟</span> Manage Dreams</a>
            <a href="<?= $base ?>/admin/manage_users.php"   class="sidebar-link"><span class="sidebar-icon">👥</span> Manage Users</a>
            <a href="<?= $base ?>/supporter/browse_dreams.php" class="sidebar-link"><span class="sidebar-icon">🔍</span> View Public</a>
            <a href="<?= $base ?>/logout.php" class="sidebar-link" style="margin-top:2rem;border-top:1px solid rgba(255,255,255,.1);padding-top:1rem;"><span class="sidebar-icon">🚪</span> Logout</a>
        </nav>
    </aside>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Manage Dreams</h1>
            <p style="color:var(--muted);"><?= count($dreams) ?> dream<?= count($dreams) !== 1 ? 's' : '' ?> found</p>
        </div>

        <!-- Filters -->
        <form method="GET" action="" style="margin-bottom:1.5rem;">
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search dreams..." style="max-width:220px;" value="<?= e($search) ?>">
                <select name="filter" class="form-control" style="max-width:180px;">
                    <option value="">All Statuses</option>
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="category" class="form-control" style="max-width:200px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="<?= $base ?>/admin/manage_dreams.php" class="btn btn-outline btn-sm">Clear</a>
            </div>
        </form>

        <!-- Status filter quick tabs -->
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem;">
            <a href="?filter=" class="btn btn-sm <?= $filterStatus === '' ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="?filter=Submitted" class="btn btn-sm <?= $filterStatus === 'Submitted' ? 'btn-amber' : 'btn-outline' ?>">Pending</a>
            <a href="?filter=Verified" class="btn btn-sm <?= $filterStatus === 'Verified' ? 'btn-primary' : 'btn-outline' ?>">Verified</a>
            <a href="?filter=Matched" class="btn btn-sm <?= $filterStatus === 'Matched' ? 'btn-primary' : 'btn-outline' ?>">Matched</a>
            <a href="?filter=In Progress" class="btn btn-sm <?= $filterStatus === 'In Progress' ? 'btn-primary' : 'btn-outline' ?>">In Progress</a>
            <a href="?filter=Dream Achieved" class="btn btn-sm <?= $filterStatus === 'Dream Achieved' ? 'btn-primary' : 'btn-outline' ?>">Achieved</a>
        </div>

        <?php if (empty($dreams)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌟</div>
                <h3>No dreams found</h3>
                <p>Try adjusting your filters.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:1rem;">
            <?php foreach ($dreams as $dream): ?>
                <div class="card" style="padding:1.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">

                        <div style="flex:1;min-width:200px;">
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem;">
                                <span class="dream-category"><?= e($dream['category']) ?></span>
                                <span class="status-badge status-<?= str_replace(' ', '-', e($dream['status'])) ?>"><?= e($dream['status']) ?></span>
                                <?php if ($dream['support_count'] > 0): ?>
                                    <span style="font-size:.75rem;color:var(--muted);">💛 <?= $dream['support_count'] ?> supporter(s)</span>
                                <?php endif; ?>
                            </div>
                            <h4 style="margin-bottom:.3rem;"><?= e($dream['title']) ?></h4>
                            <p style="font-size:.85rem;margin-bottom:.75rem;"><?= e(mb_substr($dream['description'], 0, 180)) ?>...</p>
                            <div style="font-size:.8rem;color:var(--muted);display:flex;gap:1rem;flex-wrap:wrap;">
                                <span>👤 <?= e($dream['guardian_name']) ?></span>
                                <span>📧 <?= e($dream['guardian_email']) ?></span>
                                <span>📍 <?= e($dream['city']) ?></span>
                                <span>🎂 Age <?= e($dream['age_group']) ?></span>
                                <span>💰 <?= e($dream['budget_range']) ?></span>
                                <span>🗓️ <?= date('M j, Y', strtotime($dream['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Status Update Form -->
                        <form method="POST" action="" style="flex-shrink:0;">
                            <input type="hidden" name="dream_id" value="<?= (int)$dream['id'] ?>">
                            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                                <select name="new_status" class="form-control" style="max-width:180px;font-size:.875rem;">
                                    <?php foreach ($validStatuses as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $dream['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>