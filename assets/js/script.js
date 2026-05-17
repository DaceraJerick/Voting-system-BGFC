// filename: assets/js/script.js
// MODERN LOADING, CONFIRMATION, AND NOTIFICATION SYSTEM

// =============================================
// MODERN LOADING OVERLAY (1 SECOND)
// =============================================

class ModernLoader {
    constructor() {
        this.createLoader();
    }
    
    createLoader() {
        const overlay = document.createElement('div');
        overlay.className = 'modern-loading-overlay hidden';
        overlay.id = 'modernLoadingOverlay';
        
        overlay.innerHTML = `
            <div class="modern-loader">
                <div class="loader-circle"></div>
                <div class="loader-circle"></div>
                <div class="loader-circle"></div>
                <div class="loader-dot"></div>
                <div class="loading-text">LOADING</div>
            </div>
        `;
        
        document.body.appendChild(overlay);
    }
    
    show(duration = 1000) {
        const overlay = document.getElementById('modernLoadingOverlay');
        overlay.classList.remove('hidden');
        
        if (duration > 0) {
            setTimeout(() => this.hide(), duration);
        }
        
        return this;
    }
    
    hide() {
        const overlay = document.getElementById('modernLoadingOverlay');
        overlay.classList.add('hidden');
        return this;
    }
    
    isVisible() {
        const overlay = document.getElementById('modernLoadingOverlay');
        return !overlay.classList.contains('hidden');
    }
}

// =============================================
// MODERN CONFIRMATION DIALOG (CENTERED)
// =============================================

class ModernConfirmDialog {
    constructor() {
        this.createDialog();
    }
    
    createDialog() {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop-modern';
        backdrop.id = 'modernDialogBackdrop';
        
        backdrop.innerHTML = `
            <div class="modern-dialog" id="modernDialogBox">
                <div class="modern-dialog-header">
                    <div class="modern-dialog-icon" id="dialogIcon">✓</div>
                </div>
                <h2 class="modern-dialog-title" id="dialogTitle">Confirm</h2>
                <p class="modern-dialog-message" id="dialogMessage">Are you sure?</p>
                <div class="modern-dialog-buttons" id="dialogButtons">
                    <button class="modern-btn modern-btn-secondary" id="dialogCancel">Cancel</button>
                    <button class="modern-btn modern-btn-primary" id="dialogConfirm">Confirm</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(backdrop);
        
        // Close on backdrop click
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                this.hide();
            }
        });
        
        // Close on cancel button
        document.getElementById('dialogCancel').addEventListener('click', () => {
            this.hide();
        });
    }
    
    show(options = {}) {
        const {
            title = 'Confirm',
            message = 'Are you sure?',
            type = 'info', // info, success, warning, error
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;
        
        // Update dialog content
        document.getElementById('dialogTitle').textContent = title;
        document.getElementById('dialogMessage').textContent = message;
        document.getElementById('dialogConfirm').textContent = confirmText;
        document.getElementById('dialogCancel').textContent = cancelText;
        
        // Update icons
        const icon = document.getElementById('dialogIcon');
        icon.className = 'modern-dialog-icon ' + type;
        
        const iconMap = {
            info: '?',
            success: '✓',
            warning: '!',
            error: '✕'
        };
        
        icon.textContent = iconMap[type] || '?';
        
        // Handle button colors based on type
        const confirmBtn = document.getElementById('dialogConfirm');
        const cancelBtn = document.getElementById('dialogCancel');
        
        confirmBtn.className = 'modern-btn';
        if (type === 'error' || type === 'warning') {
            confirmBtn.classList.add('modern-btn-danger');
        } else {
            confirmBtn.classList.add('modern-btn-primary');
        }
        cancelBtn.className = 'modern-btn modern-btn-secondary';
        
        // Attach callbacks
        confirmBtn.onclick = () => {
            onConfirm();
            this.hide();
        };
        
        cancelBtn.onclick = () => {
            onCancel();
            this.hide();
        };
        
        // Show dialog with animation
        const backdrop = document.getElementById('modernDialogBackdrop');
        backdrop.classList.add('active');
        
        return this;
    }
    
    hide() {
        const backdrop = document.getElementById('modernDialogBackdrop');
        backdrop.classList.remove('active');
        return this;
    }
    
    confirm(title, message, onConfirm, onCancel) {
        return this.show({
            title: title,
            message: message,
            type: 'info',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    }
    
    alert(title, message, type = 'info') {
        return this.show({
            title: title,
            message: message,
            type: type,
            confirmText: 'OK',
            cancelText: '',
            onConfirm: () => {},
            onCancel: () => {}
        });
    }
}

// =============================================
// TOAST NOTIFICATION SYSTEM
// =============================================

class ToastNotification {
    constructor() {
        this.createContainer();
    }
    
    createContainer() {
        if (!document.getElementById('toastContainer')) {
            const container = document.createElement('div');
            container.className = 'toast-container';
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }
    }
    
    show(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toastContainer');
        
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        
        const iconMap = {
            success: '✓',
            error: '✕',
            warning: '!',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${iconMap[type] || '✓'}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close">×</button>
        `;
        
        container.appendChild(toast);
        
        // Close button functionality
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.style.animation = 'none';
            setTimeout(() => toast.remove(), 10);
        });
        
        // Auto remove after duration
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duration);
    }
    
    success(message, duration = 3000) {
        this.show(message, 'success', duration);
    }
    
    error(message, duration = 4000) {
        this.show(message, 'error', duration);
    }
    
    warning(message, duration = 3500) {
        this.show(message, 'warning', duration);
    }
    
    info(message, duration = 3000) {
        this.show(message, 'info', duration);
    }
}

// =============================================
// GLOBAL INSTANCES
// =============================================

const loader = new ModernLoader();
const confirmDialog = new ModernConfirmDialog();
const toast = new ToastNotification();

// =============================================
// AUTO-HIDE ALERTS & LEGACY SUPPORT
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide Bootstrap alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                alert.style.display = 'none';
            }
        });
    }, 5000);
});

// =============================================
// MODERN DELETE CONFIRMATION
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delete').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemName = this.getAttribute('data-item') || 'this item';
            
            confirmDialog.show({
                title: 'Delete ' + itemName + '?',
                message: 'This action cannot be undone. Are you sure you want to delete ' + itemName + '?',
                type: 'warning',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: () => {
                    loader.show(1000);
                    const form = button.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
    });
});

// =============================================
// FORM SUBMISSION WITH LOADING
// =============================================

document.addEventListener('submit', function(e) {
    const form = e.target;
    
    // Skip if form has class 'no-loader'
    if (form.classList.contains('no-loader')) return;
    
    // Show loading for 1 second before submission
    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
        loader.show(1000);
    }
});

// =============================================
// FORM DATE VALIDATION
// =============================================

function validateDates(startDateId, endDateId) {
    const startDate = document.getElementById(startDateId);
    const endDate = document.getElementById(endDateId);
    
    if(startDate && endDate) {
        endDate.addEventListener('change', function() {
            if(startDate.value && endDate.value) {
                if(new Date(endDate.value) <= new Date(startDate.value)) {
                    toast.warning('End date must be after start date');
                    endDate.value = '';
                }
            }
        });
    }
}

// =============================================
// AJAX LOADING WITH MODERN LOADER
// =============================================

function loadWithModernLoader(url, containerSelector) {
    loader.show();
    
    fetch(url)
        .then(response => response.text())
        .then(data => {
            loader.hide();
            document.querySelector(containerSelector).innerHTML = data;
        })
        .catch(error => {
            loader.hide();
            toast.error('Failed to load content: ' + error.message);
            console.error('Error:', error);
        });
}

// =============================================
// PERFORMANCE MONITORING
// =============================================

(function() {
    let pageLoadStartTime = performance.now();
    
    window.addEventListener('load', function() {
        let pageLoadEndTime = performance.now();
        let loadTime = pageLoadEndTime - pageLoadStartTime;
        console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
    });
})();
