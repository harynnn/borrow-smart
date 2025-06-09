<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}
?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white">
        <div class="mx-auto max-w-7xl overflow-hidden px-6 py-8 sm:py-12 lg:px-8">
            <nav class="-mb-6 columns-2 sm:flex sm:justify-center sm:space-x-12" aria-label="Footer">
                <div class="pb-6">
                    <a href="about.php" class="text-sm leading-6 text-gray-600 hover:text-gray-900">About</a>
                </div>
                <div class="pb-6">
                    <a href="help.php" class="text-sm leading-6 text-gray-600 hover:text-gray-900">Help Center</a>
                </div>
                <div class="pb-6">
                    <a href="privacy.php" class="text-sm leading-6 text-gray-600 hover:text-gray-900">Privacy Policy</a>
                </div>
                <div class="pb-6">
                    <a href="terms.php" class="text-sm leading-6 text-gray-600 hover:text-gray-900">Terms of Service</a>
                </div>
                <div class="pb-6">
                    <a href="contact.php" class="text-sm leading-6 text-gray-600 hover:text-gray-900">Contact</a>
                </div>
                <?php if (DEBUG_MODE): ?>
                    <div class="pb-6">
                        <a href="phpinfo.php" class="text-sm leading-6 text-gray-600 hover:text-gray-900">System Info</a>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="mt-8 flex justify-center space-x-10">
                <a href="<?php echo SOCIAL_FACEBOOK; ?>" class="text-gray-400 hover:text-gray-500" target="_blank" rel="noopener noreferrer">
                    <span class="sr-only">Facebook</span>
                    <i class="fab fa-facebook text-xl"></i>
                </a>
                <a href="<?php echo SOCIAL_TWITTER; ?>" class="text-gray-400 hover:text-gray-500" target="_blank" rel="noopener noreferrer">
                    <span class="sr-only">Twitter</span>
                    <i class="fab fa-twitter text-xl"></i>
                </a>
                <a href="<?php echo SOCIAL_INSTAGRAM; ?>" class="text-gray-400 hover:text-gray-500" target="_blank" rel="noopener noreferrer">
                    <span class="sr-only">Instagram</span>
                    <i class="fab fa-instagram text-xl"></i>
                </a>
            </div>

            <div class="mt-8 border-t border-gray-900/10 pt-8">
                <div class="flex justify-center space-x-10">
                    <img class="h-8" src="/images/borrowsmart.png" alt="BorrowSmart Logo">
                    <img class="h-8" src="/images/uthmlogo.png" alt="UTHM Logo">
                </div>
            </div>

            <p class="mt-8 text-center text-xs leading-5 text-gray-500">
                &copy; <?php echo date('Y'); ?> BorrowSmart - UTHM. All rights reserved.
            </p>

            <?php if (DEBUG_MODE): ?>
                <div class="mt-4 text-center text-xs text-gray-400">
                    <p>Page generated in: <?php echo number_format((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000, 2); ?>ms</p>
                    <p>Memory usage: <?php echo formatFileSize(memory_get_usage()); ?></p>
                    <?php if (isset($_SESSION['uid'])): ?>
                        <p>User ID: <?php echo $_SESSION['uid']; ?> | Role: <?php echo $_SESSION['role']; ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-button')?.addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // Toggle user menu
        document.getElementById('user-menu-button')?.addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const userMenu = document.getElementById('user-menu');
            const userMenuButton = document.getElementById('user-menu-button');

            if (mobileMenu && mobileMenuButton && !mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }

            if (userMenu && userMenuButton && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.bg-green-50, .bg-red-50').forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 1s ease-out';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 1000);
            }, 5000);
        });

        // Add loading indicator for forms
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                }
            });
        });

        // Confirm dangerous actions
        document.querySelectorAll('[data-confirm]').forEach(function(element) {
            element.addEventListener('click', function(event) {
                if (!confirm(this.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });

        // Handle session timeout warning
        <?php if (isset($_SESSION['last_activity'])): ?>
            const sessionTimeout = <?php echo SESSION_LIFETIME; ?> * 1000;
            const warningTime = 5 * 60 * 1000; // 5 minutes before timeout
            
            function checkSessionTimeout() {
                const timeElapsed = Date.now() - <?php echo $_SESSION['last_activity'] * 1000; ?>;
                const timeRemaining = sessionTimeout - timeElapsed;
                
                if (timeRemaining <= warningTime && timeRemaining > 0) {
                    if (!document.getElementById('session-warning')) {
                        const warning = document.createElement('div');
                        warning.id = 'session-warning';
                        warning.className = 'fixed bottom-4 right-4 bg-yellow-100 border-l-4 border-yellow-500 p-4';
                        warning.innerHTML = `
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        Your session will expire in ${Math.ceil(timeRemaining / 60000)} minutes.
                                        <a href="login.php" class="font-medium underline">Click here to stay logged in</a>
                                    </p>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(warning);
                    }
                } else if (timeRemaining <= 0) {
                    window.location.href = 'logout.php?expired=true';
                }
            }

            setInterval(checkSessionTimeout, 60000); // Check every minute
        <?php endif; ?>
    </script>
</body>
</html>
