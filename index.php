<?php
// ============================================================
// index.php — Home Page
// Place this file in: /before-i-grow-up/index.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Home';
$base = BASE_PATH;

// Fetch 3 featured verified/matched dreams
$db = getDB();
$stmt = $db->prepare("
    SELECT d.*, s.city, s.age_group
    FROM dreams d
    JOIN students s ON d.student_id = s.id
    WHERE d.status IN ('Verified', 'Matched', 'In Progress')
    ORDER BY d.created_at DESC
    LIMIT 3
");
$stmt->execute();
$featured = $stmt->fetchAll();

// Stats
$totalDreams  = $db->query("SELECT COUNT(*) FROM dreams")->fetchColumn();
$dreamsDone   = $db->query("SELECT COUNT(*) FROM dreams WHERE status='Dream Achieved'")->fetchColumn();
$totalSupport = $db->query("SELECT COUNT(*) FROM supporters")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── HERO ──────────────────────────────────────────────── -->
<section class="hero">
    <div class="container hero-content">
        <span class="hero-eyebrow">🌟 Safe · Moderated · Inspiring</span>
        <h1>Where Young <em>Dreams</em><br>Find Helping Hands</h1>
        <p class="hero-sub">A safe, moderated platform connecting students under 18 with mentors and sponsors who care — no direct contact, full guardian oversight.</p>
        <div class="hero-actions">
            <a href="<?= $base ?>/guardian/submit_dream.php" class="btn btn-primary btn-lg">🌱 Submit a Dream</a>
            <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-amber btn-lg">💛 Help a Dream</a>
        </div>

        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-number"><?= number_format($totalDreams) ?></div>
                <div class="stat-label">Dreams Shared</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($totalSupport) ?></div>
                <div class="stat-label">Supporters</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($dreamsDone) ?></div>
                <div class="stat-label">Dreams Achieved</div>
            </div>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────── -->
<section class="section how-it-works">
    <div class="container">
        <div class="section-header">
            <span class="section-label">The Process</span>
            <h2>How It Works</h2>
            <p>Simple, safe, and transparent — every dream is reviewed before it's shared.</p>
        </div>
        <div class="grid-4">
            <div class="step-card">
                <div class="step-icon">👨‍👩‍👧</div>
                <h3>Guardian Submits</h3>
                <p>A parent or teacher submits a learning dream on behalf of their student.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">🔍</div>
                <h3>Admin Reviews</h3>
                <p>Our team reviews every submission to ensure it's safe and appropriate.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">💛</div>
                <h3>Supporter Adopts</h3>
                <p>A mentor or sponsor discovers the dream and chooses to help fulfill it.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">🎉</div>
                <h3>Dream Achieved!</h3>
                <p>The student's learning goal is realized — safely and joyfully.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── FEATURED DREAMS ────────────────────────────────────── -->
<?php if (!empty($featured)): ?>
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-label">Spotlight</span>
            <h2>Featured Dreams</h2>
            <p>These young learners are waiting for a helping hand — will you be the one?</p>
        </div>
        <div class="grid-3">
            <?php foreach ($featured as $dream): ?>
            <div class="card card-dream">
                <span class="dream-category"><?= e($dream['category']) ?></span>
                <h3><?= e($dream['title']) ?></h3>
                <p><?= e(mb_substr($dream['description'], 0, 130)) ?>...</p>
                <div class="dream-meta">
                    <span>📍 <?= e($dream['city']) ?></span>
                    <span>🎂 Age <?= e($dream['age_group']) ?></span>
                    <span>💰 <?= e($dream['budget_range']) ?></span>
                    <span class="status-badge status-<?= str_replace(' ', '-', e($dream['status'])) ?>"><?= e($dream['status']) ?></span>
                </div>
                <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-outline btn-sm" style="margin-top:.75rem;">View Dream →</a>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-primary">Browse All Dreams</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── CATEGORIES ─────────────────────────────────────────── -->
<section class="section categories">
    <div class="container">
        <div class="section-header">
            <span class="section-label">Categories</span>
            <h2>Every Kind of Dream</h2>
            <p>From coding to painting, music to robotics — we celebrate every learning path.</p>
        </div>
        <div class="category-grid">
            <?php
            $cats = [
                ['🛠️','Skills to Learn'],['🎨','Creative Arts'],['🔬','STEM Exploration'],
                ['📚','Academic Support'],['🗣️','Language Learning'],['🎵','Music and Performance'],
                ['💻','Technology and Coding'],['🏆','Competition Preparation'],['✨','Others']
            ];
            foreach ($cats as [$icon, $label]):
            ?>
            <a href="<?= $base ?>/supporter/browse_dreams.php?category=<?= urlencode($label) ?>" class="category-pill">
                <?= $icon ?> <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── TRUST SECTION ──────────────────────────────────────── -->
<section class="section" style="background: var(--warm-white);">
    <div class="container">
        <div class="grid-2" style="align-items:center; gap:3rem;">
            <div>
                <span class="section-label">Our Promise</span>
                <h2>Safe by Design</h2>
                <p style="font-size:1.05rem; margin:1rem 0 1.5rem;">We built this platform with one priority: the wellbeing of young learners. Every dream is reviewed, no personal information is shared, and supporters never contact students directly.</p>
                <ul style="display:flex;flex-direction:column;gap:.75rem;">
                    <li style="display:flex;gap:.75rem;align-items:flex-start;">
                        <span style="color:var(--sage);font-size:1.2rem;">✓</span>
                        <span><strong>No direct contact</strong> between students and supporters</span>
                    </li>
                    <li style="display:flex;gap:.75rem;align-items:flex-start;">
                        <span style="color:var(--sage);font-size:1.2rem;">✓</span>
                        <span><strong>Admin moderation</strong> on every submission</span>
                    </li>
                    <li style="display:flex;gap:.75rem;align-items:flex-start;">
                        <span style="color:var(--sage);font-size:1.2rem;">✓</span>
                        <span><strong>Guardian oversight</strong> at every step</span>
                    </li>
                    <li style="display:flex;gap:.75rem;align-items:flex-start;">
                        <span style="color:var(--sage);font-size:1.2rem;">✓</span>
                        <span><strong>Personal info protected</strong> — no names or locations shared publicly</span>
                    </li>
                </ul>
            </div>
            <div style="background:linear-gradient(135deg,#EEF6EC,#FDF0DC);border-radius:var(--radius-xl);padding:3rem;text-align:center;">
                <div style="font-size:4rem;margin-bottom:1rem;">🛡️</div>
                <h3 style="font-size:1.5rem;margin-bottom:.75rem;">Fully Moderated</h3>
                <p>Every dream is reviewed by a human admin before going live. We take child safety seriously.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container">
        <h2>Ready to Make a Difference?</h2>
        <p>Whether you're a guardian sharing a dream or a supporter ready to help — join our growing community of dreamers and doers.</p>
        <div class="cta-actions">
            <a href="<?= $base ?>/register.php" class="btn btn-white btn-lg">Create an Account</a>
            <a href="<?= $base ?>/supporter/browse_dreams.php" class="btn btn-ghost btn-lg">Browse Dreams First</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>