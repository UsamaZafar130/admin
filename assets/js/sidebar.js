// Sidebar Navigation JavaScript
// Handles active state management, responsive behavior, and toggle functionality

document.addEventListener('DOMContentLoaded', function() {
    // Set active navigation item based on current URL
    setActiveNavItem();
    
    // Handle submenu state persistence
    handleSubmenuState();
    
    // Auto-close mobile sidebar when clicking on links
    handleMobileSidebarClose();
    
    // Initialize sidebar toggle functionality
    initSidebarToggle();
    
    // Initialize settings modal
    initSettingsModal();
});

function setActiveNavItem() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-sidebar .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        
        // Remove active class from all links first
        link.classList.remove('active');
        
        // Set active class for exact matches or if current path starts with the link path
        if (href && (currentPath === href || (href !== '/' && currentPath.startsWith(href)))) {
            link.classList.add('active');
            
            // If this is a submenu item, also expand the parent menu
            const submenu = link.closest('.collapse');
            if (submenu) {
                submenu.classList.add('show');
                const toggleButton = document.querySelector(`[href="#${submenu.id}"]`);
                if (toggleButton) {
                    toggleButton.setAttribute('aria-expanded', 'true');
                }
            }
        }
    });
}

function handleSubmenuState() {
    // Store submenu state in localStorage
    const submenuToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    
    submenuToggles.forEach(toggle => {
        const targetId = toggle.getAttribute('href') || toggle.getAttribute('data-bs-target');
        const target = document.querySelector(targetId);
        
        if (target) {
            // Restore saved state
            const savedState = localStorage.getItem(`submenu_${targetId}`);
            if (savedState === 'open') {
                target.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            }
            
            // Listen for state changes
            target.addEventListener('shown.bs.collapse', function() {
                localStorage.setItem(`submenu_${targetId}`, 'open');
            });
            
            target.addEventListener('hidden.bs.collapse', function() {
                localStorage.setItem(`submenu_${targetId}`, 'closed');
            });
        }
    });
}

function handleMobileSidebarClose() {
    // Auto-close mobile sidebar when clicking on navigation links
    const mobileNavLinks = document.querySelectorAll('#mobileSidebar .nav-link');
    const mobileSidebar = document.getElementById('mobileSidebar');
    
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Only close if it's not a toggle button for submenu
            if (!this.hasAttribute('data-bs-toggle')) {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(mobileSidebar);
                if (bsOffcanvas) {
                    bsOffcanvas.hide();
                }
            }
        });
    });
}

function initSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const footer = document.querySelector('.fixed-footer');
    
    if (!sidebarToggle || !sidebar) return;
    
    // Restore saved sidebar state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        collapseSidebar();
    } else {
        // Ensure expanded state has correct icon
        const icon = sidebarToggle.querySelector('i');
        const text = sidebarToggle.querySelector('.toggle-text');
        if (icon) {
            icon.className = 'fas fa-chevron-left me-1';
        }
        if (text) {
            text.textContent = 'Collapse';
        }
    }
    
    // Handle toggle button click
    sidebarToggle.addEventListener('click', function() {
        if (sidebar.classList.contains('collapsed')) {
            expandSidebar();
        } else {
            collapseSidebar();
        }
    });
    
    // Auto-expand sidebar when submenu is toggled
    const submenuToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            if (sidebar.classList.contains('collapsed')) {
                // Auto-expand sidebar when submenu is opened
                expandSidebar();
            }
        });
    });
    
    // Add tooltips to navigation items in collapsed state
    addTooltipsToNavItems();
    
    function collapseSidebar() {
        sidebar.classList.add('collapsed');
        if (mainContent) mainContent.classList.add('sidebar-collapsed');
        if (footer) footer.classList.add('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'true');
        
        // Update toggle button icon and text
        const icon = sidebarToggle.querySelector('i');
        const text = sidebarToggle.querySelector('.toggle-text');
        if (icon) {
            icon.className = 'fas fa-chevron-right';
        }
        if (text) {
            text.textContent = 'Expand';
        }
        
        // Auto-collapse all open submenus
        const openSubmenus = document.querySelectorAll('.collapse.show');
        openSubmenus.forEach(submenu => {
            const bsCollapse = bootstrap.Collapse.getInstance(submenu) || new bootstrap.Collapse(submenu, {show: false});
            bsCollapse.hide();
            
            // Update toggle button aria-expanded
            const toggleButton = document.querySelector(`[href="#${submenu.id}"], [data-bs-target="#${submenu.id}"]`);
            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'false');
            }
        });
    }
    
    function expandSidebar() {
        sidebar.classList.remove('collapsed');
        if (mainContent) mainContent.classList.remove('sidebar-collapsed');
        if (footer) footer.classList.remove('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');
        
        // Update toggle button icon and text
        const icon = sidebarToggle.querySelector('i');
        const text = sidebarToggle.querySelector('.toggle-text');
        if (icon) {
            icon.className = 'fas fa-chevron-left me-1';
        }
        if (text) {
            text.textContent = 'Collapse';
        }
    }
}

function addTooltipsToNavItems() {
    // Add tooltip data attributes to navigation items for collapsed state
    const navLinks = document.querySelectorAll('.nav-sidebar .nav-link');
    
    navLinks.forEach(link => {
        const textElement = link.querySelector('.nav-text');
        if (textElement) {
            const tooltipText = textElement.textContent.trim();
            link.setAttribute('data-tooltip', tooltipText);
        }
    });
}

function initSettingsModal() {
    const modalThemeSelector = document.getElementById('modal-theme-selector');
    const modalTimezoneSelector = document.getElementById('modal-timezone-selector');
    const saveSettingsBtn = document.getElementById('saveSettings');
    const settingsModal = document.getElementById('settingsModal');
    
    if (!modalThemeSelector || !modalTimezoneSelector) return;
    
    // Load current settings when modal is shown
    settingsModal.addEventListener('show.bs.modal', function() {
        // Load current theme
        const currentTheme = localStorage.getItem('selectedTheme') || 'spacelab';
        modalThemeSelector.value = currentTheme;
        
        // Load current timezone
        const currentTimezone = localStorage.getItem('selectedTimezone') || 'UTC';
        modalTimezoneSelector.value = currentTimezone;
    });
    
    // Handle save settings
    saveSettingsBtn.addEventListener('click', function() {
        const selectedTheme = modalThemeSelector.value;
        const selectedTimezone = modalTimezoneSelector.value;
        
        // Save theme
        localStorage.setItem('selectedTheme', selectedTheme);
        if (window.changeTheme) {
            window.changeTheme(selectedTheme);
        }
        
        // Save timezone
        localStorage.setItem('selectedTimezone', selectedTimezone);
        
        // Close modal
        const bsModal = bootstrap.Modal.getInstance(settingsModal);
        if (bsModal) {
            bsModal.hide();
        }
        
        // Show success message
        showNotification('Settings saved successfully!', 'success');
    });
}

function showNotification(message, type = 'info') {
    // Create a simple notification
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}