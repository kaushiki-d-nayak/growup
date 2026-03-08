<?php
// includes/footer.php
if (!isset($base)) {
    require_once __DIR__ . '/../config/app.php';
    $base = BASE_PATH;
}
$role = function_exists('userRole') ? userRole() : '';
?>
</main>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <a href="<?= $base ?>/index.php" class="nav-brand">
                <span class="brand-icon">*</span>
                <span class="brand-text">Before I Grow Up</span>
            </a>
            <p class="footer-tagline">
                Safe, moderated support for student dreams.
            </p>
            <div class="footer-contact-cards">
                <a class="footer-contact-card" href="mailto:beforeigrowup@gmail.com">
                    <span>Email</span>
                    <strong>beforeigrowup@gmail.com</strong>
                </a>
                <a class="footer-contact-card" href="tel:+919743295253">
                    <span>Phone</span>
                    <strong>+91 9743295253</strong>
                </a>
            </div>
            <p class="footer-location">TMA Pai Polytechnic, Manipal</p>
        </div>

        <div class="footer-links">
            <div class="footer-col">
                <h4>Explore</h4>
                <ul>
                    <li><a href="<?= $base ?>/index.php">Home</a></li>
                    <li><a href="<?= $base ?>/supporter/browse_dreams.php">Browse Dreams</a></li>
                    <?php if (!isLoggedIn()): ?>
                    <li><a href="<?= $base ?>/register.php">Register</a></li>
                    <li><a href="<?= $base ?>/login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Guardians</h4>
                <ul>
                    <li><a href="<?= $base ?>/guardian/submit_dream.php">Submit Dream</a></li>
                    <li><a href="<?= $base ?>/guardian/my_dreams.php">Track Dreams</a></li>
                    <?php if ($role === 'guardian'): ?>
                    <li><a href="<?= $base ?>/logout.php">Logout</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Supporters</h4>
                <ul>
                    <li><a href="<?= $base ?>/supporter/browse_dreams.php">Find Dreams</a></li>
                    <li><a href="<?= $base ?>/supporter/adopt_dream.php">My Support</a></li>
                    <?php if ($role === 'supporter'): ?>
                    <li><a href="<?= $base ?>/logout.php">Logout</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Before I Grow Up. Built for safe, guided student support.</p>
    </div>
</footer>

<script>
(function () {
    var path = (window.location.pathname || '').toLowerCase();
    if (path.indexOf('/admin/') !== -1) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    document.body.classList.add('js-reveal');

    var autoRevealSelectors = [
        '.hero-eyebrow',
        '.hero h1',
        '.hero-sub',
        '.hero-actions',
        '.stat-item',
        '.section-header',
        '.step-card',
        '.card',
        '.category-pill',
        '.form-card',
        '.detail-section',
        '.empty-state',
        '.flash',
        '.cta-banner h2',
        '.cta-banner p',
        '.cta-actions'
    ];

    var autoNodes = document.querySelectorAll(autoRevealSelectors.join(','));
    autoNodes.forEach(function (el) {
        if (!el.hasAttribute('data-reveal')) {
            el.setAttribute('data-reveal', '');
        }
    });

    var items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    items.forEach(function (el) { observer.observe(el); });
})();
</script>

</body>
</html>
