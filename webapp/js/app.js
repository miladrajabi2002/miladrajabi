// ═══════════════════════════════════════════════════════════════
// Telegram WebApp - Fixed Version
// ═══════════════════════════════════════════════════════════════

const tg = window.Telegram?.WebApp || {};
const API_URL = './api/';
const ALLOWED_USER_ID = 1253939828;

let userId = null;
let hapticEnabled = true;
let incomeChart = null;
let habitsChart = null;

// ───────────────────────────────────────────────────────────────
// Initialize
// ───────────────────────────────────────────────────────────────
function initTelegramWebApp() {
    if (tg.ready) tg.ready();
    if (tg.expand) tg.expand();
    
    // Get user data
    const user = tg.initDataUnsafe?.user;
    if (user) {
        userId = user.id;
        
        console.log('👤 User info:', {
            id: user.id,
            first_name: user.first_name,
            username: user.username,
            photo_url: user.photo_url
        });
        
        // Check authorization
        if (userId !== ALLOWED_USER_ID) {
            showAccessDenied();
            return;
        }
        
        const userName = user.first_name || 'کاربر';
        
        // Update user name
        const userNameEl = document.getElementById('user-name');
        const welcomeUserEl = document.getElementById('welcome-user');
        if (userNameEl) userNameEl.textContent = userName;
        if (welcomeUserEl) welcomeUserEl.textContent = userName;
        
        // Update avatar
        const avatarEl = document.getElementById('user-avatar');
        if (avatarEl) {
            if (user.photo_url) {
                console.log('🖼️ Using Telegram photo:', user.photo_url);
                avatarEl.src = user.photo_url;
            } else {
                const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128&bold=true`;
                console.log('🖼️ Using fallback avatar:', fallbackUrl);
                avatarEl.src = fallbackUrl;
            }
            avatarEl.onerror = function() {
                console.error('❌ Avatar failed to load, using fallback');
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128&bold=true`;
            };
        }
        
        console.log('✅ User authorized:', userId);
    } else {
        // Testing mode
        userId = ALLOWED_USER_ID;
        console.log('⚠️ Testing mode - using allowed user ID');
        
        // Set default avatar for testing
        const avatarEl = document.getElementById('user-avatar');
        if (avatarEl) {
            avatarEl.src = 'https://ui-avatars.com/api/?name=Test+User&background=6366f1&color=fff&size=128&bold=true';
        }
    }
    
    // Dark mode
    if (tg.colorScheme === 'dark') {
        document.body.classList.add('dark-mode');
        const toggle = document.getElementById('dark-mode-toggle');
        if (toggle) toggle.checked = true;
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
}

function showAccessDenied() {
    const splash = document.getElementById('splash-screen');
    const app = document.getElementById('app');
    
    if (splash) splash.style.display = 'none';
    if (app) {
        app.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100vh; text-align: center; padding: 20px; flex-direction: column;">
                <i class="material-icons" style="font-size: 80px; color: #ef4444; margin-bottom: 20px;">lock</i>
                <h4 style="margin: 10px 0;">دسترسی محدود</h4>
                <p class="grey-text" style="margin: 10px 0;">شما مجاز به استفاده از این وب‌اپ نیستید.</p>
                <p class="grey-text" style="font-size: 0.9rem;">User ID: ${userId}</p>
            </div>
        `;
        app.style.display = 'block';
    }
}

function updateDateTime() {
    const now = new Date();
    const days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
    const dayName = days[now.getDay()];
    const time = now.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    
    const el = document.getElementById('current-date-time');
    if (el) el.textContent = `${dayName}، ${time}`;
}

function initMaterialize() {
    if (typeof M !== 'undefined') {
        M.Sidenav.init(document.querySelectorAll('.sidenav'));
        M.FloatingActionButton.init(document.querySelectorAll('.fixed-action-btn'));
    }
}

// ───────────────────────────────────────────────────────────────
// API Calls
// ───────────────────────────────────────────────────────────────
async function apiCall(endpoint, data = {}) {
    try {
        const url = API_URL + endpoint;
        console.log('🔄 API Call:', url, 'Data:', { user_id: userId, ...data });
        
        const response = await fetch(url, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ user_id: userId, ...data })
        });
        
        console.log('📡 Response status:', response.status, response.statusText);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ HTTP Error:', errorText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('✅ API Response:', result);
        return result;
        
    } catch (error) {
        console.error('❌ API Error:', error);
        if (typeof M !== 'undefined') {
            M.toast({ html: `خطا در بارگذاری: ${error.message}`, classes: 'red rounded' });
        }
        return { success: false, demo: true, error: error.message };
    }
}

// ───────────────────────────────────────────────────────────────
// Format Money (بدون اعشار)
// ───────────────────────────────────────────────────────────────
function formatMoney(amount) {
    if (!amount || amount === 0) return '۰';
    
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    
    function toPersianNumber(num) {
        return String(num).split('').map(char => 
            char >= '0' && char <= '9' ? persianDigits[parseInt(char)] : char
        ).join('');
    }
    
    if (amount >= 1000000) {
        // گرد کردن به بالا بدون اعشار
        const millions = Math.ceil(amount / 1000000);
        return toPersianNumber(millions) + ' میلیون';
    } else if (amount >= 1000) {
        // هزار تومان
        const thousands = Math.ceil(amount / 1000);
        return toPersianNumber(thousands) + ' هزار تومان';
    }
    
    return toPersianNumber(amount) + ' تومان';
}

// ───────────────────────────────────────────────────────────────
// Page Navigation
// ───────────────────────────────────────────────────────────────
function showPage(pageName) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(pageName + '-page');
    if (targetPage) targetPage.classList.add('active');
    
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    if (typeof event !== 'undefined' && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
    
    const titles = {
        dashboard: 'داشبورد',
        incomes: 'درآمدها',
        reminders: 'یادآورها',
        notes: 'یادداشت‌ها',
        habits: 'عادت‌ها',
        settings: 'تنظیمات'
    };
    
    const titleEl = document.getElementById('page-title');
    if (titleEl) titleEl.textContent = titles[pageName] || 'داشبورد';
    
    if (typeof M !== 'undefined') {
        const sidenav = M.Sidenav.getInstance(document.querySelector('.sidenav'));
        if (sidenav) sidenav.close();
    }
    
    loadPageData(pageName);
    
    if (hapticEnabled && tg.HapticFeedback) {
        tg.HapticFeedback.impactOccurred('light');
    }
}

function loadPageData(pageName) {
    console.log('📄 Loading page:', pageName);
    switch(pageName) {
        case 'dashboard': loadDashboard(); break;
        case 'incomes': loadIncomes(); break;
        case 'reminders': loadReminders(); break;
        case 'notes': loadNotes(); break;
        case 'habits': loadHabits(); break;
    }
}

// ───────────────────────────────────────────────────────────────
// Dashboard
// ───────────────────────────────────────────────────────────────
async function loadDashboard() {
    console.log('📊 Loading dashboard...');
    const result = await apiCall('dashboard.php');
    
    console.log('📄 Dashboard result:', result);
    
    if (result.success && result.data) {
        console.log('✅ Dashboard data loaded successfully');
        const { stats, income_chart, habits_chart, recent_activities } = result.data;
        updateDashboardStats(stats);
        renderIncomeChart(income_chart);
        renderHabitsChart(habits_chart);
        renderActivities(recent_activities);
    } else {
        console.warn('⚠️ Dashboard API failed, loading demo data');
        loadDemoDashboard();
    }
}

function loadDemoDashboard() {
    console.log('📊 Demo dashboard mode');
    updateDashboardStats({
        monthly_income: 47000000,
        today_reminders: 5,
        completed_habits: 3,
        total_habits: 8,
        total_notes: 12
    });
    
    renderIncomeChart([
        { month: 'مرداد', amount: 35000000 },
        { month: 'شهریور', amount: 40000000 },
        { month: 'مهر', amount: 38000000 },
        { month: 'آبان', amount: 45000000 },
        { month: 'آذر', amount: 47000000 },
        { month: 'دی', amount: 47000000 }
    ]);
    
    renderHabitsChart([
        { day: 'ش', count: 5 },
        { day: 'ی', count: 6 },
        { day: 'د', count: 4 },
        { day: 'س', count: 7 },
        { day: 'چ', count: 5 },
        { day: 'پ', count: 6 },
        { day: 'ج', count: 3 }
    ]);
    
    renderActivities([
        { icon: 'check_circle', color: 'green', title: 'عادت ورزش انجام شد', time: '۲ ساعت پیش' },
        { icon: 'monetization_on', color: 'blue', title: 'درآمد جدید', time: '۵ ساعت پیش' }
    ]);
    
    if (typeof M !== 'undefined') {
        M.toast({ 
            html: '⚠️ در حال نمایش دیتای نمونه - لطفاً config.php را بررسی کنید', 
            classes: 'orange rounded', 
            displayLength: 5000 
        });
    }
}

function updateDashboardStats(stats) {
    console.log('📊 Updating stats:', stats);
    
    const incomeEl = document.getElementById('stat-income');
    const remindersEl = document.getElementById('stat-reminders');
    const habitsEl = document.getElementById('stat-habits');
    const notesEl = document.getElementById('stat-notes');
    
    if (incomeEl) {
        incomeEl.textContent = formatMoney(stats.monthly_income);
        console.log('✅ Income stat updated:', incomeEl.textContent);
    }
    if (remindersEl) remindersEl.textContent = stats.today_reminders || 0;
    if (habitsEl) habitsEl.textContent = `${stats.completed_habits || 0}/${stats.total_habits || 0}`;
    if (notesEl) notesEl.textContent = stats.total_notes || 0;
}

function renderIncomeChart(data) {
    const ctx = document.getElementById('incomeChart');
    if (!ctx) {
        console.warn('⚠️ incomeChart canvas not found');
        return;
    }
    
    if (incomeChart) incomeChart.destroy();
    
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js not loaded');
        return;
    }
    
    incomeChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.map(d => d.month),
            datasets: [{
                label: 'درآمد',
                data: data.map(d => Math.ceil(d.amount / 1000000)),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        callback: v => v + ' میلیون',
                        stepSize: 5
                    }
                }
            }
        }
    });
    
    console.log('✅ Income chart rendered');
}

function renderHabitsChart(data) {
    const ctx = document.getElementById('habitsChart');
    if (!ctx) {
        console.warn('⚠️ habitsChart canvas not found');
        return;
    }
    
    if (habitsChart) habitsChart.destroy();
    
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js not loaded');
        return;
    }
    
    habitsChart = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.day),
            datasets: [{
                label: 'عادت',
                data: data.map(d => d.count),
                backgroundColor: '#10b981',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
    
    console.log('✅ Habits chart rendered');
}

function renderActivities(activities) {
    const container = document.getElementById('recent-activities');
    if (!container) {
        console.warn('⚠️ recent-activities not found');
        return;
    }
    
    if (!activities || activities.length === 0) {
        container.innerHTML = '<li class="collection-item center grey-text">فعالیتی ثبت نشده</li>';
        return;
    }
    
    container.innerHTML = activities.map(act => `
        <li class="collection-item avatar">
            <i class="material-icons circle ${act.color}">${act.icon}</i>
            <span class="title">${act.title}</span>
            <p class="grey-text">${act.time}</p>
        </li>
    `).join('');
    
    console.log('✅ Activities rendered:', activities.length);
}

// ───────────────────────────────────────────────────────────────
// Incomes
// ───────────────────────────────────────────────────────────────
async function loadIncomes() {
    const result = await apiCall('incomes.php');
    
    if (result.success && result.data) {
        const { incomes, stats } = result.data;
        
        const totalEl = document.getElementById('income-total');
        const monthlyEl = document.getElementById('income-monthly');
        const inactiveEl = document.getElementById('income-inactive');
        
        if (totalEl) totalEl.textContent = stats.total_active || 0;
        if (monthlyEl) monthlyEl.textContent = formatMoney(stats.monthly_total || 0);
        if (inactiveEl) inactiveEl.textContent = stats.total_inactive || 0;
        
        const container = document.getElementById('incomes-list');
        if (!container) return;
        
        if (incomes.length === 0) {
            container.innerHTML = '<p class="center grey-text">درآمدی ثبت نشده</p>';
            return;
        }
        
        container.innerHTML = '<ul class="collection">' + incomes.map(inc => `
            <li class="collection-item hoverable" onclick="showIncomeDetail(${inc.id})" style="cursor: pointer;">
                <div>
                    <span class="title">${inc.client_name}</span>
                    ${inc.client_username ? `<a href="https://t.me/${inc.client_username.replace('@', '')}" target="_blank" class="grey-text" onclick="event.stopPropagation()"> @${inc.client_username.replace('@', '')}</a>` : ''}
                    <p class="grey-text">${inc.service_type}</p>
                    <p class="grey-text">مبلغ ماهانه: <strong>${formatMoney(inc.monthly_amount)}</strong></p>
                    <p class="grey-text">${inc.months} ماه فعال | کل: ${formatMoney(inc.total_earned)}</p>
                    ${inc.days_until_payment ? `<p class="orange-text">🔔 ${inc.days_until_payment} روز تا پرداخت</p>` : ''}
                </div>
                <span class="secondary-content">
                    <span class="badge ${inc.is_active ? 'green' : 'grey'} white-text">${inc.is_active ? 'فعال' : 'غیرفعال'}</span><br>
                    <i class="material-icons grey-text" style="margin-top: 8px;">chevron_left</i>
                </span>
            </li>
        `).join('') + '</ul>';
    }
}

async function showIncomeDetail(incomeId) {
    if (hapticEnabled && tg.HapticFeedback) tg.HapticFeedback.impactOccurred('medium');
    if (typeof M !== 'undefined') {
        M.toast({ html: `بارگذاری جزئیات #${incomeId}...`, classes: 'blue rounded' });
    }
}

// ───────────────────────────────────────────────────────────────
// Habits
// ───────────────────────────────────────────────────────────────
async function loadHabits() {
    const result = await apiCall('habits.php', { action: 'list' });
    
    if (result.success && result.data) {
        const { habits } = result.data;
        const container = document.getElementById('habits-list');
        if (!container) return;
        
        if (habits.length === 0) {
            container.innerHTML = '<p class="center grey-text">عادتی ثبت نشده</p>';
            return;
        }
        
        container.innerHTML = '<ul class="collection">' + habits.map(habit => `
            <li class="collection-item">
                <div>
                    <label>
                        <input type="checkbox" class="filled-in" ${habit.is_completed_today ? 'checked' : ''} 
                               onchange="toggleHabit(${habit.id}, this.checked)">
                        <span><strong>${habit.name}</strong></span>
                    </label>
                    <div class="progress" style="margin-top: 8px;">
                        <div class="determinate ${habit.status_color}" style="width: ${habit.success_rate}%"></div>
                    </div>
                    <p class="grey-text">
                        نرخ موفقیت: <strong class="${habit.status_color}-text">${habit.success_rate}%</strong> (${habit.status}) |
                        ${habit.total_completed} از ${habit.total_days} روز
                    </p>
                </div>
            </li>
        `).join('') + '</ul>';
    }
}

async function toggleHabit(habitId, checked) {
    if (hapticEnabled && tg.HapticFeedback) tg.HapticFeedback.impactOccurred('medium');
    
    const result = await apiCall('habits.php', { action: 'toggle', habit_id: habitId });
    
    if (result.success) {
        if (typeof M !== 'undefined') {
            M.toast({ html: result.message, classes: 'green rounded' });
        }
        loadHabits();
        loadDashboard();
    }
}

// Other pages
async function loadReminders() {
    const result = await apiCall('reminders.php');
    if (result.success && result.data) {
        const { reminders } = result.data;
        const container = document.getElementById('reminders-list');
        if (!container) return;
        
        if (reminders.length === 0) {
            container.innerHTML = '<p class="center grey-text">یادآوری برای امروز ندارید</p>';
            return;
        }
        
        container.innerHTML = '<ul class="collection">' + reminders.map(rem => `
            <li class="collection-item avatar">
                <i class="material-icons circle ${rem.is_past ? 'grey' : 'orange'}">notifications</i>
                <span class="title">${rem.title}</span>
                <p>${rem.description || ''}</p>
                <p class="grey-text">ساعت: ${rem.time_fa}</p>
            </li>
        `).join('') + '</ul>';
    }
}

async function loadNotes() {
    const result = await apiCall('notes.php');
    if (result.success && result.data) {
        const { notes } = result.data;
        const container = document.getElementById('notes-list');
        if (!container) return;
        
        if (notes.length === 0) {
            container.innerHTML = '<p class="center grey-text">یادداشتی ندارید</p>';
            return;
        }
        
        container.innerHTML = notes.map(note => `
            <div class="card hoverable">
                <div class="card-content">
                    <p>${note.preview}</p>
                    <p class="grey-text" style="font-size: 0.8rem; margin-top: 8px;">${note.created_at_fa}</p>
                </div>
            </div>
        `).join('');
    }
}

// Dark Mode
document.addEventListener('DOMContentLoaded', function() {
    const darkToggle = document.getElementById('dark-mode-toggle');
    const hapticToggle = document.getElementById('haptic-toggle');
    
    if (darkToggle) {
        darkToggle.addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
            if (hapticEnabled && tg.HapticFeedback) tg.HapticFeedback.impactOccurred('medium');
        });
    }
    
    if (hapticToggle) {
        hapticToggle.addEventListener('change', function() {
            hapticEnabled = this.checked;
        });
    }
});

// App Init
window.addEventListener('load', function() {
    console.log('🚀 App starting...');
    console.log('📍 API URL:', API_URL);
    console.log('🔒 Allowed User ID:', ALLOWED_USER_ID);
    console.log('📚 Chart.js loaded:', typeof Chart !== 'undefined');
    console.log('📚 Materialize loaded:', typeof M !== 'undefined');
    console.log('📚 Telegram SDK loaded:', typeof window.Telegram !== 'undefined');
    
    initTelegramWebApp();
    
    if (userId === ALLOWED_USER_ID) {
        initMaterialize();
        loadDashboard();
        
        setTimeout(() => {
            const splash = document.getElementById('splash-screen');
            const app = document.getElementById('app');
            
            if (splash) {
                splash.style.opacity = '0';
                setTimeout(() => {
                    splash.style.display = 'none';
                    if (app) app.style.display = 'block';
                }, 500);
            } else if (app) {
                app.style.display = 'block';
            }
        }, 2000);
    }
});

window.showPage = showPage;
window.toggleHabit = toggleHabit;
window.showIncomeDetail = showIncomeDetail;