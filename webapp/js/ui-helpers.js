// ════════════════════════════════════════════════════════════════
// UI/UX Helper Functions
// توابع کمکی برای بهبود UI/UX بدون تغییر کدهای فعلی
// ════════════════════════════════════════════════════════════════

const UIHelpers = {
    
    // ────────────────────────────────────────────────────────────────
    // 1. Toast Notifications
    // ────────────────────────────────────────────────────────────────
    
    /**
     * نمایش Toast Notification
     * @param {string} message - متن پیغام
     * @param {string} type - نوع: success, error, warning, info
     * @param {number} duration - مدت زمان نمایش (میلی‌ثانیه)
     */
    showToast(message, type = 'info', duration = 3000) {
        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="material-icons">${icons[type] || 'info'}</i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);
        
        // نمایش Toast
        setTimeout(() => toast.classList.add('show'), 100);
        
        // مخفی و حذف Toast
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    // ────────────────────────────────────────────────────────────────
    // 2. Skeleton Loading
    // ────────────────────────────────────────────────────────────────
    
    /**
     * نمایش Skeleton Loading
     * @param {HTMLElement} container - المان مقصد
     * @param {number} count - تعداد skeleton
     * @param {string} type - نوع: card, list, text
     */
    showSkeleton(container, count = 3, type = 'card') {
        if (!container) return;
        
        container.innerHTML = '';
        
        for (let i = 0; i < count; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'skeleton-card';
            
            if (type === 'card') {
                skeleton.innerHTML = `
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line medium"></div>
                    <div class="skeleton-line short"></div>
                `;
            } else if (type === 'list') {
                skeleton.innerHTML = `
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div class="skeleton-circle"></div>
                        <div style="flex: 1;">
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line short"></div>
                        </div>
                    </div>
                `;
            } else if (type === 'text') {
                skeleton.innerHTML = `
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line short"></div>
                `;
            }
            
            container.appendChild(skeleton);
        }
    },
    
    /**
     * مخفی کردن Skeleton و نمایش محتوا
     * @param {HTMLElement} container - المان مقصد
     * @param {string} content - محتوای HTML
     */
    hideSkeleton(container, content) {
        if (!container) return;
        container.innerHTML = content;
    },
    
    // ────────────────────────────────────────────────────────────────
    // 3. Circular Progress
    // ────────────────────────────────────────────────────────────────
    
    /**
     * ایجاد Circular Progress
     * @param {number} percentage - درصد (0-100)
     * @param {string} colorClass - کلاس رنگ: success, warning, danger
     * @param {string} size - اندازه: small, medium, large
     * @returns {string} HTML رشته
     */
    createCircularProgress(percentage, colorClass = '', size = 'medium') {
        const circumference = 2 * Math.PI * 15.9155;
        const dashArray = `${(percentage / 100) * circumference}, ${circumference}`;
        
        return `
            <div class="circular-progress ${size}">
                <svg viewBox="0 0 36 36" width="64" height="64">
                    <path class="circle-bg"
                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                    />
                    <path class="circle ${colorClass}"
                          stroke-dasharray="${dashArray}"
                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                    />
                </svg>
                <div class="percentage">${percentage}%</div>
            </div>
        `;
    },
    
    // ────────────────────────────────────────────────────────────────
    // 4. Modern Stat Cards
    // ────────────────────────────────────────────────────────────────
    
    /**
     * ایجاد Modern Stat Card
     * @param {object} options - تنظیمات
     * @returns {string} HTML رشته
     */
    createModernStatCard(options) {
        const {
            icon = 'trending_up',
            iconColor = '',
            label = '',
            value = '0',
            change = null,
            changeType = 'positive'
        } = options;
        
        const changeHTML = change ? `
            <p class="stat-change ${changeType}">
                <i class="material-icons tiny">${changeType === 'positive' ? 'arrow_upward' : 'arrow_downward'}</i>
                ${change}
            </p>
        ` : '';
        
        return `
            <div class="stat-card-modern">
                <div class="stat-icon ${iconColor}">
                    <i class="material-icons">${icon}</i>
                </div>
                <div class="stat-content">
                    <p class="stat-label">${label}</p>
                    <h3 class="stat-value-modern">${value}</h3>
                    ${changeHTML}
                </div>
            </div>
        `;
    },
    
    // ────────────────────────────────────────────────────────────────
    // 5. Progress Bar
    // ────────────────────────────────────────────────────────────────
    
    /**
     * ایجاد Modern Progress Bar
     * @param {number} percentage - درصد (0-100)
     * @param {string} colorClass - کلاس رنگ: success, warning, danger
     * @returns {string} HTML رشته
     */
    createProgressBar(percentage, colorClass = '') {
        return `
            <div class="progress-modern">
                <div class="progress-bar-modern ${colorClass}" style="width: ${percentage}%"></div>
            </div>
        `;
    },
    
    // ────────────────────────────────────────────────────────────────
    // 6. Empty State
    // ────────────────────────────────────────────────────────────────
    
    /**
     * نمایش Empty State
     * @param {HTMLElement} container - المان مقصد
     * @param {object} options - تنظیمات
     */
    showEmptyState(container, options) {
        if (!container) return;
        
        const {
            icon = 'inbox',
            title = 'هیچ موردی وجود ندارد',
            description = 'برای شروع یک مورد اضافه کنید',
            buttonText = null,
            buttonAction = null
        } = options;
        
        const buttonHTML = buttonText && buttonAction ? `
            <button class="btn blue waves-effect waves-light" onclick="${buttonAction}">
                ${buttonText}
            </button>
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
    },
    
    // ────────────────────────────────────────────────────────────────
    // 7. Loading Overlay
    // ────────────────────────────────────────────────────────────────
    
    /**
     * نمایش Loading Overlay
     */
    showLoading() {
        let overlay = document.getElementById('loading-overlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(overlay);
        }
        
        setTimeout(() => overlay.classList.add('active'), 10);
    },
    
    /**
     * مخفی کردن Loading Overlay
     */
    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    },
    
    // ────────────────────────────────────────────────────────────────
    // 8. Animation Helpers
    // ────────────────────────────────────────────────────────────────
    
    /**
     * اضافه کردن انیمیشن به المان
     * @param {HTMLElement} element - المان
     * @param {string} animationClass - کلاس انیمیشن
     */
    addAnimation(element, animationClass) {
        if (!element) return;
        
        element.classList.add(animationClass);
        element.addEventListener('animationend', () => {
            element.classList.remove(animationClass);
        }, { once: true });
    },
    
    /**
     * افزودن کلاس enhanced به المان‌ها
     * @param {string} selector - CSS selector
     */
    enhanceElements(selector) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => el.classList.add('enhanced'));
    }
};

// Export به window برای دسترسی آسان
window.UIHelpers = UIHelpers;

// شورتکات‌ها برای استفاده آسان‌تر
window.showToast = UIHelpers.showToast.bind(UIHelpers);
window.showSkeleton = UIHelpers.showSkeleton.bind(UIHelpers);
window.hideSkeleton = UIHelpers.hideSkeleton.bind(UIHelpers);
window.showEmptyState = UIHelpers.showEmptyState.bind(UIHelpers);
window.showLoading = UIHelpers.showLoading.bind(UIHelpers);
window.hideLoading = UIHelpers.hideLoading.bind(UIHelpers);

console.log('✨ UI Helpers loaded successfully!');
