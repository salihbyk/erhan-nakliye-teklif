// Modern Sidebar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.modern-sidebar');
    const overlay = document.querySelector('.mobile-overlay');

    // Create mobile menu button if it doesn't exist
    if (!mobileMenuBtn && window.innerWidth <= 768) {
        const btn = document.createElement('button');
        btn.className = 'mobile-menu-btn';
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        btn.setAttribute('aria-label', 'Menüyü Aç');
        document.body.appendChild(btn);

        // Create overlay
        const overlayDiv = document.createElement('div');
        overlayDiv.className = 'mobile-overlay';
        document.body.appendChild(overlayDiv);

        setupMobileMenu(btn, overlayDiv);
    }

    function setupMobileMenu(btn, overlay) {
        btn.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');

            const isOpen = sidebar.classList.contains('mobile-open');
            btn.innerHTML = isOpen ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            btn.setAttribute('aria-label', isOpen ? 'Menüyü Kapat' : 'Menüyü Aç');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-bars"></i>';
            btn.setAttribute('aria-label', 'Menüyü Aç');
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                btn.innerHTML = '<i class="fas fa-bars"></i>';
                btn.setAttribute('aria-label', 'Menüyü Aç');
            }
        });
    }

    // Smooth scrolling for nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Add loading state
            const icon = this.querySelector('.nav-icon i');
            if (!icon) return;
            const originalIcon = icon.className;

            if (!this.classList.contains('logout-item')) {
                icon.className = 'fas fa-spinner fa-spin';

                setTimeout(() => {
                    icon.className = originalIcon;
                }, 500);
            }
        });
    });

    // Add ripple effect to nav items
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;

            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add CSS for ripple animation
    if (!document.querySelector('#ripple-styles')) {
        const style = document.createElement('style');
        style.id = 'ripple-styles';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            const overlay = document.querySelector('.mobile-overlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }
    });

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key >= '1' && e.key <= '9') {
            e.preventDefault();
            const index = parseInt(e.key) - 1;
            const navItems = document.querySelectorAll('.nav-item:not(.logout-item)');
            if (navItems[index]) {
                navItems[index].click();
            }
        }
    });

    // Show keyboard shortcuts tooltip on Alt key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Alt') {
            showKeyboardShortcuts();
        }
    });

    document.addEventListener('keyup', function(e) {
        if (e.key === 'Alt') {
            hideKeyboardShortcuts();
        }
    });

    function showKeyboardShortcuts() {
        const navItems = document.querySelectorAll('.nav-item:not(.logout-item)');
        navItems.forEach((item, index) => {
            if (index < 9) {
                const badge = document.createElement('span');
                badge.className = 'keyboard-shortcut-badge';
                badge.textContent = index + 1;
                badge.style.cssText = `
                    position: absolute;
                    top: 5px;
                    right: 5px;
                    background: #3b82f6;
                    color: white;
                    border-radius: 50%;
                    width: 18px;
                    height: 18px;
                    font-size: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                    z-index: 10;
                `;
                item.style.position = 'relative';
                item.appendChild(badge);
            }
        });
    }

    function hideKeyboardShortcuts() {
        document.querySelectorAll('.keyboard-shortcut-badge').forEach(badge => {
            badge.remove();
        });
    }
});
