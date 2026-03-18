/**
 * Main JavaScript for Cash Flow System
 */

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
    if (overlay) {
        overlay.classList.toggle('active');
    }
}

// Update URL parameter
function updateUrlParameter(url, param, value) {
    const urlObj = new URL(url);
    urlObj.searchParams.set(param, value);
    return urlObj.toString();
}

// Auto-hide flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[name="delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-format currency inputs
    const amountInputs = document.querySelectorAll('input[name="amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            }
        });
    });
    
    // Close sidebar when clicking outside on mobile
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.remove('active');
            }
            overlay.classList.remove('active');
        });
    }
    
    // Close sidebar on navigation click (mobile)
    if (window.innerWidth <= 768) {
        const sidebarLinks = document.querySelectorAll('.sidebar-menu-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (sidebar) {
                    sidebar.classList.remove('active');
                }
                if (overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
    }
    
    // Intercept sidebar navigation clicks to open in tabs
    const sidebarLinks = document.querySelectorAll('.sidebar-menu-link');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Skip links that have target="_blank" (like POS links)
            if (this.getAttribute('target') === '_blank') {
                // Close sidebar on mobile
                if (window.innerWidth <= 768) {
                    if (sidebar) {
                        sidebar.classList.remove('active');
                    }
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                }
                return; // Let the browser handle it
            }
            
            // Only intercept internal links
            const href = this.getAttribute('href');
            if (href && href.startsWith('/sales/') && typeof openNewTab === 'function') {
                e.preventDefault();
                const title = this.textContent.trim();
                openNewTab(href, title);
                
                // Close sidebar on mobile
                if (window.innerWidth <= 768) {
                    if (sidebar) {
                        sidebar.classList.remove('active');
                    }
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                }
            }
        });
    });
});
