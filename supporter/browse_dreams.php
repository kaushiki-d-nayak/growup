<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Browse Dreams';
$base = BASE_PATH;
$db   = getDB();

$filterCategory = $_GET['category'] ?? '';
$filterBudget   = $_GET['budget']   ?? '';
$filterStatus   = $_GET['status']   ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 9;
$offset   = ($page - 1) * $perPage;

$categories = ['Skills to Learn','Creative Arts','STEM Exploration','Academic Support',
               'Language Learning','Music and Performance','Technology and Coding','Competition Preparation','Others'];
$budgets    = ['No Money Needed','Under Rs500','Rs500-Rs2,000','Rs2,000-Rs10,000','Rs10,000+'];
$statuses   = ['Verified'];

$where  = "WHERE d.status = 'Verified'
           AND NOT EXISTS (
               SELECT 1
               FROM dream_support ds_open
               WHERE ds_open.dream_id = d.id
                 AND ds_open.status = 'Approved'
           )";
$params = [];

if ($filterCategory && in_array($filterCategory, $categories)) {
    $where   .= " AND d.category = ?";
    $params[] = $filterCategory;
}
if ($filterBudget && in_array($filterBudget, $budgets)) {
    $where   .= " AND d.budget_range = ?";
    $params[] = $filterBudget;
}
if ($filterStatus && in_array($filterStatus, $statuses)) {
    $where   .= " AND d.status = ?";
    $params[] = $filterStatus;
}

// Count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM dreams d $where");
$countStmt->execute($params);
$total    = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch dreams
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->prepare("
    SELECT d.*, s.age_group, s.city,
           (SELECT COUNT(*) FROM dream_support ds WHERE ds.dream_id = d.id) AS support_count
    FROM dreams d
    JOIN students s ON d.student_id = s.id
    $where
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$dreams = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

$categoryIcons = [
    'Skills to Learn' => '🛠️', 'Creative Arts' => '🎨', 'STEM Exploration' => '🔬',
    'Academic Support' => '📚', 'Language Learning' => '🗣️', 'Music and Performance' => '🎵',
    'Technology and Coding' => '💻', 'Competition Preparation' => '🏆', 'Others' => '✨'
];
?>

<section class="page-hero">
    <div class="container">
        <h1>Browse Dreams</h1>
        <p>Every card represents a real child's learning aspiration. Find a dream to adopt and help make it real.</p>
    </div>
</section>

<div class="section-sm">
    <div class="container">

        <!-- Filter Bar -->
        <form method="GET" action="">
            <div class="filter-bar">
                <select name="category" class="form-control" style="max-width:220px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="budget" class="form-control" style="max-width:160px;">
                    <option value="">Any Budget</option>
                    <?php foreach ($budgets as $b): ?>
                        <option value="<?= e($b) ?>" <?= $filterBudget === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control" style="max-width:160px;">
                    <option value="">Any Status</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if ($filterCategory || $filterBudget || $filterStatus): ?>
                    <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>

                <span style="margin-left:auto;font-size:.85rem;color:var(--muted);"><?= $total ?> dream<?= $total !== 1 ? 's' : '' ?> found</span>
            </div>
        </form>

        <?php if (empty($dreams)): ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h3>No dreams found</h3>
                <p>Try changing your filters, or check back soon as new dreams are added regularly.</p>
                <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-outline" style="margin-top:1rem;">Clear Filters</a>
            </div>
        <?php else: ?>

            <div class="grid-3">
            <?php foreach ($dreams as $dream):
                $icon = $categoryIcons[$dream['category']] ?? '🌟';
            ?>
                <div class="card card-dream">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                        <span class="dream-category"><?= $icon ?> <?= e($dream['category']) ?></span>
                        <span class="status-badge status-<?= str_replace(' ', '-', e($dream['status'])) ?>"><?= e($dream['status']) ?></span>
                    </div>

                    <h3><?= e($dream['title']) ?></h3>
                    <p><?= e(mb_substr($dream['description'], 0, 150)) ?>...</p>

                    <div class="dream-meta">
                        <span>📍 <?= e($dream['city']) ?></span>
                        <span>🎂 Age <?= e($dream['age_group']) ?></span>
                        <span>💰 <?= displayBudget($dream['budget_range'] ?? null) ?></span>
                        <?php if ($dream['support_count'] > 0): ?>
                            <span>💛 <?= $dream['support_count'] ?> supporter(s)</span>
                        <?php endif; ?>
                    </div>

                    <?php if (isLoggedIn() && userRole() === 'supporter'): ?>
                        <?php if ($dream['status'] === 'Verified' || $dream['status'] === 'Matched'): ?>
                            <a href="<?= $base ?>/supporter/adopt_dream.php?dream_id=<?= $dream['id'] ?>" class="btn btn-amber btn-sm" style="margin-top:.75rem;">
                                💛 Adopt this Dream
                            </a>
                        <?php else: ?>
                            <span class="btn btn-outline btn-sm" style="margin-top:.75rem;opacity:.6;cursor:not-allowed;">
                                <?= e($dream['status']) ?>
                            </span>
                        <?php endif; ?>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="<?= $base ?>/login.php" class="btn btn-outline btn-sm" style="margin-top:.75rem;">
                            Login to Support
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $qBase = '?category=' . urlencode($filterCategory) . '&budget=' . urlencode($filterBudget) . '&status=' . urlencode($filterStatus);
                    for ($p = 1; $p <= $totalPages; $p++):
                    ?>
                        <a href="<?= $base ?>/supporter/browse_dreams.php<?= $qBase ?>&page=<?= $p ?>"
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (!isLoggedIn()): ?>
            <div style="text-align:center;padding:2rem;margin-top:2rem;background:linear-gradient(135deg,#EEF6EC,#FDF0DC);border-radius:var(--radius-lg);">
                <h3 style="margin-bottom:.5rem;">Ready to Help?</h3>
                <p style="margin-bottom:1.25rem;">Register as a supporter to adopt a dream and make a difference.</p>
                <a href="<?= $base ?>/register.php" class="btn btn-primary">Create Supporter Account</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

