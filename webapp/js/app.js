// ═══════════════════════════════════════════════════════════════
// Telegram WebApp - Fully Fixed Version
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
    
    const user = tg.initDataUnsafe?.user;
    if (user) {
        userId = user.id;
        
        console.log('👤 User info:', user);
        
        if (userId !== ALLOWED_USER_ID) {
            showAccessDenied();
            return;
        }
        
        const userName = user.first_name || 'کاربر';
        
        const userNameEl = document.getElementById('user-name');
        const welcomeUserEl = document.getElementById('welcome-user');
        if (userNameEl) userNameEl.textContent = userName;
        if (welcomeUserEl) welcomeUserEl.textContent = userName;
        
        // عکس پروفایل
        const avatarEl = document.getElementById('user-avatar');
        if (avatarEl) {
            const avatarUrl = user.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128&bold=true`;
            avatarEl.src = avatarUrl;
            avatarEl.onerror = function() {
                this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128&bold=true`;
            };
        }
        
        console.log('✅ User authorized:', userId);
    } else {
        userId = ALLOWED_USER_ID;
        console.log('⚠️ Testing mode');
    }
    
    if (tg.colorScheme === 'dark') {
        document.body.classList.add('dark-mode');
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
                <h4>دسترسی محدود</h4>
                <p class="grey-text">شما مجاز به استفاده از این وب‌اپ نیستید.</p>
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
    // بدون jQuery - مستقیم با vanilla JS
    if (typeof M !== 'undefined') {
        const sidenavElems = document.querySelectorAll('.sidenav');
        if (sidenavElems.length > 0) {
            M.Sidenav.init(sidenavElems);
        }
        
        const fabElems = document.querySelectorAll('.fixed-action-btn');
        if (fabElems.length > 0) {
            M.FloatingActionButton.init(fabElems);
        }
    }
}

// ───────────────────────────────────────────────────────────────
// API Calls
// ───────────────────────────────────────────────────────────────
async function apiCall(endpoint, data = {}) {
    try {
        const url = API_URL + endpoint;
        console.log('🔄 Calling:', url);
        
        const response = await fetch(url, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ user_id: userId, ...data })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('✅', endpoint, '→', result.success ? 'OK' : 'FAIL');
        return result;
        
    } catch (error) {
        console.error('❌', endpoint, '→', error.message);
        return { success: false, error: error.message };
    }
}

// ───────────────────────────────────────────────────────────────
// Format Money
// ───────────────────────────────────────────────────────────────
function formatMoney(amount) {
    if (!amount || amount === 0) return '۰';
    
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    
    function toPersian(n) {
        return String(n).split('').map(c => 
            c >= '0' && c <= '9' ? persianDigits[parseInt(c)] : c
        ).join('');
    }
    
    if (num >= 1000000) {
        return toPersian(Math.ceil(num / 1000000)) + ' میلیون';
    } else if (num >= 1000) {
        return toPersian(Math.ceil(num / 1000)) + ' هزار';
    }
    return toPersian(num);
}

// ───────────────────────────────────────────────────────────────
// Page Navigation
// ───────────────────────────────────────────────────────────────
function showPage(pageName) {
    console.log('📄 Show page:', pageName);
    
    // تغییر صفحه
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(pageName + '-page');
    if (targetPage) targetPage.classList.add('active');
    
    // تغییر nav
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    
    // عنوان
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
    
    // بستن منو
    if (typeof M !== 'undefined') {
        const sidenavElem = document.querySelector('.sidenav');
        if (sidenavElem) {
            const instance = M.Sidenav.getInstance(sidenavElem);
            if (instance) instance.close();
        }
    }
    
    // بارگذاری دیتا
    loadPageData(pageName);
    
    // Haptic
    if (hapticEnabled && tg.HapticFeedback) {
        tg.HapticFeedback.impactOccurred('light');
    }
}

function loadPageData(pageName) {
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
    
    if (result.success && result.data) {
        const { stats, income_chart, habits_chart, recent_activities } = result.data;
        
        // آمار
        const incomeEl = document.getElementById('stat-income');
        const remindersEl = document.getElementById('stat-reminders');
        const habitsEl = document.getElementById('stat-habits');
        const notesEl = document.getElementById('stat-notes');
        
        if (incomeEl) incomeEl.textContent = formatMoney(stats.monthly_income);
        if (remindersEl) remindersEl.textContent = stats.today_reminders || 0;
        if (habitsEl) habitsEl.textContent = `${stats.completed_habits || 0}/${stats.total_habits || 0}`;
        if (notesEl) notesEl.textContent = stats.total_notes || 0;
        
        // نمودارها
        if (income_chart && income_chart.length > 0) {
            renderIncomeChart(income_chart);
        }
        
        if (habits_chart && habits_chart.length > 0) {
            renderHabitsChart(habits_chart);
        }
        
        // فعالیت‌ها
        if (recent_activities && recent_activities.length > 0) {
            const container = document.getElementById('recent-activities');
            if (container) {
                container.innerHTML = recent_activities.map(act => `
                    <li class="collection-item avatar">
                        <i class="material-icons circle ${act.color}">${act.icon}</i>
                        <span class="title">${act.title}</span>
                        <p class="grey-text">${act.time}</p>
                    </li>
                `).join('');
            }
        }
        
        console.log('✅ Dashboard loaded');
    } else {
        console.warn('⚠️ Dashboard failed, showing demo');
    }
}

function renderIncomeChart(data) {
    const ctx = document.getElementById('incomeChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    if (incomeChart) incomeChart.destroy();
    
    incomeChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.map(d => d.month),
            datasets: [{
                label: 'درآمد',
                data: data.map(d => {
                    const num = typeof d.amount === 'string' ? parseFloat(d.amount) : d.amount;
                    return Math.ceil(num / 1000000);
                }),
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
                    ticks: { callback: v => v + ' میلیون' }
                }
            }
        }
    });
    
    console.log('✅ Income chart OK');
}

function renderHabitsChart(data) {
    const ctx = document.getElementById('habitsChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    if (habitsChart) habitsChart.destroy();
    
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
    
    console.log('✅ Habits chart OK');
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
            <li class="collection-item hoverable" style="cursor: pointer;">
                <div>
                    <span class="title">${inc.client_name}</span>
                    ${inc.client_username ? `<a href="https://t.me/${inc.client_username.replace('@', '')}" class="grey-text"> @${inc.client_username.replace('@', '')}</a>` : ''}
                    <p class="grey-text">${inc.service_type}</p>
                    <p class="grey-text">ماهانه: <strong>${formatMoney(inc.monthly_amount)}</strong></p>
                    <p class="grey-text">${inc.months} ماه | کل: ${formatMoney(inc.total_earned)}</p>
                </div>
                <span class="secondary-content">
                    <span class="badge ${inc.is_active ? 'green' : 'grey'} white-text">${inc.is_active ? 'فعال' : 'غیرفعال'}</span>
                </span>
            </li>
        `).join('') + '</ul>';
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
                               onchange="toggleHabit(${habit.id})">
                        <span><strong>${habit.name}</strong></span>
                    </label>
                    <div class="progress" style="margin-top: 8px;">
                        <div class="determinate ${habit.status_color}" style="width: ${habit.success_rate}%"></div>
                    </div>
                    <p class="grey-text">
                        نرخ: <strong class="${habit.status_color}-text">${habit.success_rate}%</strong> (${habit.status}) |
                        ${habit.total_completed} از ${habit.total_days} روز
                    </p>
                </div>
            </li>
        `).join('') + '</ul>';
        
        console.log('✅ Habits loaded:', habits.length);
    }
}

async function toggleHabit(habitId) {
    if (hapticEnabled && tg.HapticFeedback) tg.HapticFeedback.impactOccurred('medium');
    
    const result = await apiCall('habits.php', { action: 'toggle', habit_id: habitId });
    
    if (result.success) {
        if (typeof M !== 'undefined') {
            M.toast({ html: result.message || 'ذخیره شد', classes: 'green rounded' });
        }
        loadHabits();
        loadDashboard();
    }
}

// ───────────────────────────────────────────────────────────────
// Reminders
// ───────────────────────────────────────────────────────────────
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
        
        console.log('✅ Reminders loaded:', reminders.length);
    }
}

// ───────────────────────────────────────────────────────────────
// Notes
// ───────────────────────────────────────────────────────────────
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
        
        console.log('✅ Notes loaded:', notes.length);
    }
}

// ───────────────────────────────────────────────────────────────
// Dark Mode
// ───────────────────────────────────────────────────────────────
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

// ───────────────────────────────────────────────────────────────
// App Init
// ───────────────────────────────────────────────────────────────
window.addEventListener('load', function() {
    console.log('🚀 App init...');
    
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
        }, 1500);
    }
});

// Export
window.showPage = showPage;
window.toggleHabit = toggleHabit;