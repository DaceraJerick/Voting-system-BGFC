    </main>
    <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
        </div> <!-- end main-content -->
    </div> <!-- end app-layout -->
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Move all modals to the body
        document.addEventListener('DOMContentLoaded', function() {
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                document.body.appendChild(modal);
            });
        });
    </script>

    <script>
    /* ══════════════════════════════════════════
       THEME ENGINE — Dark / Light Mode
    ══════════════════════════════════════════ */

    function applyTheme(theme) {
        var html = document.documentElement;
        html.setAttribute('data-theme', theme);
        localStorage.setItem('cv_theme', theme);

        var isDark = theme === 'dark';

        /* ── Update all toggle pills ── */
        document.querySelectorAll('.theme-toggle-pill').forEach(function(btn) {
            var icon  = btn.querySelector('i');
            var label = btn.querySelector('.toggle-label');

            if (icon) {
                if (isDark) {
                    icon.className = 'bi bi-sun-fill';
                } else {
                    icon.className = 'bi bi-moon-fill';
                }
                /* Spin animation */
                icon.style.transform = 'rotate(360deg)';
                setTimeout(function() { icon.style.transform = ''; }, 400);
            }

            if (label) {
                label.textContent = isDark ? 'Light' : 'Dark';
            }
        });
    }

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    /* ── Apply on page load (no flash) ── */
    document.addEventListener('DOMContentLoaded', function() {
        var saved = localStorage.getItem('cv_theme') || 'light';
        applyTheme(saved);
    });
    </script>

    <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student'): ?>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    <nav class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="dashboard.php" class="mobile-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-house-door-fill"></i>
                <span>Home</span>
            </a>
            <a href="results.php" class="mobile-nav-item <?php echo $current_page == 'results.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-fill"></i>
                <span>Results</span>
            </a>
            <a href="winners.php" class="mobile-nav-item <?php echo $current_page == 'winners.php' ? 'active' : ''; ?>">
                <i class="bi bi-trophy-fill"></i>
                <span>Winners</span>
            </a>
            <a href="profile.php" class="mobile-nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-fill"></i>
                <span>Profile</span>
            </a>
        </div>
    </nav>
    <?php endif; ?>

</body>
</html>