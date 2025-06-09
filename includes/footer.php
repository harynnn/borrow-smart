<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}
?>
    </main>

    <!-- Footer -->
    <footer class="bg-white mt-8">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Logo and Description -->
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center space-x-4">
                        <img src="/images/borrowsmart.png" alt="BorrowSmart Logo" class="h-8">
                        <img src="/images/uthmlogo.png" alt="UTHM Logo" class="h-8">
                    </div>
                    <p class="mt-4 text-sm text-gray-500">
                        BorrowSmart is UTHM's instrument borrowing management system, 
                        streamlining the process of borrowing and returning instruments 
                        for students and staff.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">
                        Quick Links
                    </h3>
                    <ul class="mt-4 space-y-4">
                        <li>
                            <a href="help.php" class="text-base text-gray-500 hover:text-gray-900">
                                Help Center
                            </a>
                        </li>
                        <li>
                            <a href="faq.php" class="text-base text-gray-500 hover:text-gray-900">
                                FAQ
                            </a>
                        </li>
                        <li>
                            <a href="contact.php" class="text-base text-gray-500 hover:text-gray-900">
                                Contact Support
                            </a>
                        </li>
                        <li>
                            <a href="terms.php" class="text-base text-gray-500 hover:text-gray-900">
                                Terms of Service
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">
                        Contact Us
                    </h3>
                    <ul class="mt-4 space-y-4">
                        <li class="flex">
                            <i class="fas fa-phone text-gray-400 mt-1"></i>
                            <span class="ml-3 text-base text-gray-500">
                                <?php echo CONTACT_PHONE; ?>
                            </span>
                        </li>
                        <li class="flex">
                            <i class="fas fa-envelope text-gray-400 mt-1"></i>
                            <span class="ml-3 text-base text-gray-500">
                                <?php echo SUPPORT_EMAIL; ?>
                            </span>
                        </li>
                        <li class="flex">
                            <i class="fas fa-map-marker-alt text-gray-400 mt-1"></i>
                            <span class="ml-3 text-base text-gray-500">
                                <?php echo CONTACT_ADDRESS; ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Social Links -->
            <div class="mt-8 border-t border-gray-200 pt-8">
                <div class="flex justify-center space-x-6">
                    <a href="<?php echo FACEBOOK_URL; ?>" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Facebook</span>
                        <i class="fab fa-facebook text-xl"></i>
                    </a>
                    <a href="<?php echo TWITTER_URL; ?>" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Twitter</span>
                        <i class="fab fa-twitter text-xl"></i>
                    </a>
                    <a href="<?php echo INSTAGRAM_URL; ?>" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Instagram</span>
                        <i class="fab fa-instagram text-xl"></i>
                    </a>
                    <a href="<?php echo YOUTUBE_URL; ?>" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">YouTube</span>
                        <i class="fab fa-youtube text-xl"></i>
                    </a>
                </div>
            </div>

            <!-- Copyright -->
            <div class="mt-8 border-t border-gray-200 pt-8">
                <p class="text-center text-sm text-gray-400">
                    &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                </p>
                <?php if (APP_ENV === 'development'): ?>
                    <p class="text-center text-xs text-gray-400 mt-2">
                        Environment: Development | PHP Version: <?php echo PHP_VERSION; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Mobile menu
        document.querySelector('.mobile-menu-button').addEventListener('click', function() {
            document.querySelector('.mobile-menu').classList.toggle('hidden');
        });

        // Profile menu
        document.querySelector('.profile-menu-button')?.addEventListener('click', function() {
            document.querySelector('.profile-menu').classList.toggle('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            // Profile menu
            if (!event.target.closest('.profile-menu-button') && !event.target.closest('.profile-menu')) {
                document.querySelector('.profile-menu')?.classList.add('hidden');
            }
        });

        // Auto-hide flash messages
        document.querySelectorAll('[role="alert"]').forEach(function(alert) {
            setTimeout(function() {
                alert.remove();
            }, 5000);
        });

        // Form validation
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    // Highlight invalid fields
                    form.querySelectorAll(':invalid').forEach(function(field) {
                        field.classList.add('border-red-500');
                    });
                }
            });
        });

        // Remove validation styling on input
        document.querySelectorAll('input, select, textarea').forEach(function(field) {
            field.addEventListener('input', function() {
                this.classList.remove('border-red-500');
            });
        });

        // Confirm dangerous actions
        document.querySelectorAll('[data-confirm]').forEach(function(element) {
            element.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });

        // Initialize tooltips
        document.querySelectorAll('[data-tooltip]').forEach(function(element) {
            element.addEventListener('mouseenter', function(e) {
                let tooltip = document.createElement('div');
                tooltip.className = 'absolute z-10 px-2 py-1 text-xs text-white bg-gray-900 rounded-md';
                tooltip.textContent = this.dataset.tooltip;
                tooltip.style.top = (e.target.offsetTop - 25) + 'px';
                tooltip.style.left = e.target.offsetLeft + 'px';
                document.body.appendChild(tooltip);

                element.addEventListener('mouseleave', function() {
                    tooltip.remove();
                });
            });
        });

        // Handle back button
        if (window.history.length > 1) {
            document.querySelectorAll('[data-back]').forEach(function(element) {
                element.classList.remove('hidden');
            });
        }
    </script>
</body>
</html>
