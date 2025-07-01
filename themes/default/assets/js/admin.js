/**
 * Shopologic Admin Panel JavaScript
 */

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize admin features
    initSidebar();
    initDropdowns();
    initNotifications();
    initCharts();
});

// Sidebar Navigation
function initSidebar() {
    const sidebarLinks = document.querySelectorAll('.admin-sidebar a');
    const currentPath = window.location.pathname;
    
    sidebarLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });
}

// Dropdown Menus
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.dropdown-trigger');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (trigger && menu) {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                menu.classList.toggle('show');
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
}

// Notifications
function initNotifications() {
    // Auto-hide success messages after 5 seconds
    const successAlerts = document.querySelectorAll('.alert-success');
    successAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
}

// Charts (placeholder for now)
function initCharts() {
    const chartCanvas = document.getElementById('salesChart');
    if (chartCanvas) {
        // Chart initialization would go here
        console.log('Chart canvas found, ready for chart library integration');
    }
}

// Helper Functions
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

function showLoading() {
    // Show loading indicator
    const loader = document.createElement('div');
    loader.className = 'loading-overlay';
    loader.innerHTML = '<div class=\"loading-spinner\"></div>';
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.querySelector('.loading-overlay');
    if (loader) {
        loader.remove();
    }
}

// AJAX Helper
function ajaxRequest(url, options = {}) {
    showLoading();
    
    const defaultOptions = {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    return fetch(url, { ...defaultOptions, ...options })
        .then(response => {
            hideLoading();
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            throw error;
        });
}

// Form Validation
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Export functions for global use
window.adminHelpers = {
    confirmDelete,
    showLoading,
    hideLoading,
    ajaxRequest,
    validateForm
};