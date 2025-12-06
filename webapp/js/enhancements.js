// ════════════════════════════════════════════════════════════════
// UI/UX Enhancements
// Toast Notifications, Pull to Refresh, Swipe Actions
// ════════════════════════════════════════════════════════════════

// ────────────────────────────────────────────────────────────────
// Toast Notifications
// ────────────────────────────────────────────────────────────────

/**
 * Get icon based on toast type
 * @param {string} type - Toast type (success, error, warning, info)
 * @returns {string} Material icon name
 */
function getIcon(type) {
    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };
    return icons[type] || 'info';
}

/**
 * Show toast notification
 * @param {string} message - Message to display
 * @param {string} type - Type of toast (success, error, warning, info)
 * @param {number} duration - Duration in milliseconds (default: 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="material-icons">${getIcon(type)}</i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);
    
    // Hide and remove toast
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ────────────────────────────────────────────────────────────────
// Pull to Refresh
// ────────────────────────────────────────────────────────────────

let startY = 0;
let currentY = 0;
let pullToRefreshEnabled = false;
let refreshIndicator = null;

/**
 * Create refresh indicator element
 */
function createRefreshIndicator() {
    if (refreshIndicator) return;
    
    refreshIndicator = document.createElement('div');
    refreshIndicator.style.cssText = `
        position: fixed;
        top: -60px;
        left: 50%;
        transform: translateX(-50%);
        width: 40px;
        height: 40px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: top 0.3s ease;
        z-index: 9999;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    `;
    refreshIndicator.innerHTML = '<i class="material-icons" style="color: white; font-size: 24px;">refresh</i>';
    document.body.appendChild(refreshIndicator);
}

/**
 * Show refresh indicator
 */
function showRefreshIndicator() {
    createRefreshIndicator();
    if (refreshIndicator) {
        refreshIndicator.style.top = '70px';
    }
}

/**
 * Hide refresh indicator
 */
function hideRefreshIndicator() {
    if (refreshIndicator) {
        refreshIndicator.style.top = '-60px';
    }
}

/**
 * Refresh current page data
 */
async function refreshData() {
    if (refreshIndicator) {
        const icon = refreshIndicator.querySelector('i');
        if (icon) {
            icon.style.animation = 'rotate 1s linear infinite';
        }
    }
    
    // Get current active page
    const activePage = document.querySelector('.page.active');
    if (activePage) {
        const pageId = activePage.id.replace('-page', '');
        
        // Call loadPageData if it exists
        if (typeof loadPageData === 'function') {
            await loadPageData(pageId);
        }
    }
    
    setTimeout(() => {
        hideRefreshIndicator();
        if (refreshIndicator) {
            const icon = refreshIndicator.querySelector('i');
            if (icon) {
                icon.style.animation = '';
            }
        }
        showToast('به‌روزرسانی انجام شد', 'success', 2000);
    }, 1000);
}

/**
 * Initialize pull to refresh
 */
function initPullToRefresh() {
    document.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            startY = e.touches[0].clientY;
            pullToRefreshEnabled = true;
        }
    });

    document.addEventListener('touchmove', (e) => {
        if (!pullToRefreshEnabled) return;
        
        currentY = e.touches[0].clientY;
        const distance = currentY - startY;
        
        if (distance > 80) {
            showRefreshIndicator();
        } else {
            hideRefreshIndicator();
        }
    });

    document.addEventListener('touchend', () => {
        if (pullToRefreshEnabled) {
            const distance = currentY - startY;
            if (distance > 80) {
                refreshData();
            } else {
                hideRefreshIndicator();
            }
        }
        pullToRefreshEnabled = false;
        startY = 0;
        currentY = 0;
    });
}

// ────────────────────────────────────────────────────────────────
// Swipe Actions
// ────────────────────────────────────────────────────────────────

/**
 * Initialize swipe actions for elements
 * @param {string} selector - CSS selector for swipeable elements
 * @param {function} onSwipeLeft - Callback for left swipe
 * @param {function} onSwipeRight - Callback for right swipe
 */
function initSwipeActions(selector, onSwipeLeft, onSwipeRight) {
    const elements = document.querySelectorAll(selector);
    
    elements.forEach(element => {
        let startX = 0;
        let currentX = 0;
        let isSwiping = false;
        
        element.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isSwiping = true;
        });
        
        element.addEventListener('touchmove', (e) => {
            if (!isSwiping) return;
            currentX = e.touches[0].clientX;
            const diff = currentX - startX;
            
            // Visual feedback
            if (Math.abs(diff) > 10) {
                element.style.transform = `translateX(${diff}px)`;
                element.style.transition = 'none';
            }
        });
        
        element.addEventListener('touchend', () => {
            if (!isSwiping) return;
            
            const diff = currentX - startX;
            element.style.transition = 'transform 0.3s ease';
            element.style.transform = '';
            
            // Swipe left (delete action)
            if (diff < -80 && onSwipeLeft) {
                onSwipeLeft(element);
            }
            // Swipe right (edit action)
            else if (diff > 80 && onSwipeRight) {
                onSwipeRight(element);
            }
            
            isSwiping = false;
            startX = 0;
            currentX = 0;
        });
    });
}

// ────────────────────────────────────────────────────────────────
// Skeleton Loading Helpers
// ────────────────────────────────────────────────────────────────

/**
 * Show skeleton loading in container
 * @param {HTMLElement} container - Container element
 * @param {number} count - Number of skeleton items
 */
function showSkeleton(container, count = 3) {
    if (!container) return;
    
    container.innerHTML = '';
    for (let i = 0; i < count; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton-card';
        skeleton.innerHTML = `
            <div class="skeleton-line"></div>
            <div class="skeleton-line short"></div>
            <div class="skeleton-avatar"></div>
        `;
        container.appendChild(skeleton);
    }
}

/**
 * Hide skeleton and show content
 * @param {HTMLElement} container - Container element
 * @param {string} content - HTML content to show
 */
function hideSkeleton(container, content) {
    if (!container) return;
    
    container.innerHTML = content;
}

// ────────────────────────────────────────────────────────────────
// Empty State Helpers
// ────────────────────────────────────────────────────────────────

/**
 * Show empty state
 * @param {HTMLElement} container - Container element
 * @param {string} icon - Material icon name
 * @param {string} title - Empty state title
 * @param {string} description - Empty state description
 * @param {string} buttonText - Optional button text
 * @param {function} buttonAction - Optional button click handler
 */
function showEmptyState(container, icon, title, description, buttonText = null, buttonAction = null) {
    if (!container) return;
    
    const buttonHTML = buttonText && buttonAction ? `
        <button class="btn blue" onclick="(${buttonAction})()">${buttonText}</button>
    ` : '';
    
    container.innerHTML = `
        <div class="empty-state">
            <div class="empty-icon">
                <i class="material-icons">${icon}</i>
            </div>
            <h6 class="empty-title">${title}</h6>
            <p class="empty-description">${description}</p>
            ${buttonHTML}
        </div>
    `;
}

// ────────────────────────────────────────────────────────────────
// Circular Progress Helper
// ────────────────────────────────────────────────────────────────

/**
 * Create circular progress indicator
 * @param {number} percentage - Progress percentage (0-100)
 * @param {string} color - Progress color (CSS color value)
 * @returns {string} HTML string for circular progress
 */
function createCircularProgress(percentage, color = 'var(--primary)') {
    const circumference = 2 * Math.PI * 15.9155; // radius is 15.9155 for a 36x36 viewBox
    const dashArray = `${(percentage / 100) * circumference}, ${circumference}`;
    
    return `
        <div class="circular-progress">
            <svg viewBox="0 0 36 36" width="64" height="64">
                <path class="circle-bg"
                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <path class="circle"
                      stroke="${color}"
                      stroke-dasharray="${dashArray}"
                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
            </svg>
            <div class="percentage">${percentage}%</div>
        </div>
    `;
}

// ────────────────────────────────────────────────────────────────
// Initialize Enhancements
// ────────────────────────────────────────────────────────────────

window.addEventListener('load', function() {
    console.log('✨ Initializing UI enhancements...');
    
    // Initialize pull to refresh
    initPullToRefresh();
    
    console.log('✅ UI enhancements ready');
});

// Export functions to window
window.showToast = showToast;
window.showSkeleton = showSkeleton;
window.hideSkeleton = hideSkeleton;
window.showEmptyState = showEmptyState;
window.createCircularProgress = createCircularProgress;
window.initSwipeActions = initSwipeActions;