// ═══════════════════════════════════════════════════════════════
// Telegram WebApp - Enhanced Version
// ═══════════════════════════════════════════════════════════════

const tg = window.Telegram.WebApp;
const API_URL = './api/';

let userId = null;
let hapticEnabled = true;
let incomeChart = null;
let habitsChart = null;
let incomeDetailChart = null;

// ───────────────────────────────────────────────────────────────
// Initialize
// ───────────────────────────────────────────────────────────────
function initTelegramWebApp() {
    tg.ready();
    tg.expand();
    
    if (tg.colorScheme === 'dark') {
        document.body.classList.add('dark-mode');
        const toggle = document.getElementById('dark-mode-toggle');
        if (toggle) toggle.checked = true;
    }
    
    const user = tg.initDataUnsafe?.user;
    if (user) {
        userId = user.id;
        const userName = user.first_name || 'کاربر';
        
        document.getElementById('user-name').textContent = userName;
        document.getElementById('welcome-user').textContent = userName;
        
        const avatarUrl = user.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128`;
        document.getElementById('user-avatar').src = avatarUrl;
        
        console.log('✅ User loaded:', userId);
    } else {
        userId = 123456;
        console.log('⚠️ Testing mode');
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
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
    M.Sidenav.init(document.querySelectorAll('.sidenav'));
    M.FloatingActionButton.init(document.querySelectorAll('.fixed-action-btn'));
}

// ───────────────────────────────────────────────────────────────
// API Calls
// ───────────────────────────────────────────────────────────────
async function apiCall(endpoint, data = {}) {
    try {
        console.log('🔄 API Call:', endpoint);
        const url = API_URL + endpoint;
        
        const response = await fetch(url, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ user_id: userId, ...data })
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const result = await response.json();
        console.log('✅ Response:', result);
        return result;
        
    } catch (error) {
        console.error('❌ Error:', error);
        M.toast({ html: `خطا: ${error.message}`, classes: 'red rounded' });
        return { success: false, demo: true };
    }
}

// ───────────────────────────────────────────────────────────────
// Format Money (میلیون فارسی)
// ───────────────────────────────────────────────────────────────
function formatMoney(amount) {
    if (!amount) return '۰';
    
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    
    if (amount >= 1000000) {
        const millions = (amount / 1000000).toFixed(1);
        const persianMillions = millions.split('').map(char => 
            char >= '0' && char <= '9' ? persianDigits[parseInt(char)] : char
        ).join('');
        return persianMillions + ' میلیون';
    }
    
    const formatted = new Intl.NumberFormat('fa-IR').format(amount);
    return formatted + ' تومان';
}

// ───────────────────────────────────────────────────────────────
// Page Navigation
// ───────────────────────────────────────────────────────────────
function showPage(pageName) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(pageName + '-page').classList.add('active');
    
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    const titles = {
        dashboard: 'داشبورد',
        incomes: 'درآمدها',
        'income-detail': 'جزئیات درآمد',
        reminders: 'یادآورها',
        notes: 'یادداشت‌ها',
        habits: 'عادت‌ها',
        settings: 'تنظیمات'
    };
    document.getElementById('page-title').textContent = titles[pageName];
    
    M.Sidenav.getInstance(document.querySelector('.sidenav'))?.close();
    loadPageData(pageName);
    
    if (hapticEnabled) tg.HapticFeedback.impactOccurred('light');
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
    const result = await apiCall('dashboard.php');
    
    if (result.success && result.data) {
        const { stats, income_chart, habits_chart, recent_activities } = result.data;
        updateDashboardStats(stats);
        renderIncomeChart(income_chart);
        renderHabitsChart(habits_chart);
        renderActivities(recent_activities);
    } else if (result.demo) {
        loadDemoDashboard();
    }
}

function loadDemoDashboard() {
    console.log('📊 Demo mode');
    updateDashboardStats({
        monthly_income: 10500000,
        today_reminders: 5,
        completed_habits: 3,
        total_habits: 8,
        total_notes: 12
    });
    
    renderIncomeChart([
        { month: 'مرداد', amount: 8000000 },
        { month: 'شهریور', amount: 9000000 },
        { month: 'مهر', amount: 8500000 },
        { month: 'آبان', amount: 10000000 },
        { month: 'آذر', amount: 10500000 },
        { month: 'دی', amount: 10500000 }
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
    
    M.toast({ html: '⚠️ دیتای نمونه - لطفاً config.php را تنظیم کنید', classes: 'orange rounded', displayLength: 4000 });
}

function updateDashboardStats(stats) {
    document.getElementById('stat-income').textContent = formatMoney(stats.monthly_income);
    document.getElementById('stat-reminders').textContent = stats.today_reminders;
    document.getElementById('stat-habits').textContent = `${stats.completed_habits}/${stats.total_habits}`;
    document.getElementById('stat-notes').textContent = stats.total_notes;
}

function renderIncomeChart(data) {
    const ctx = document.getElementById('incomeChart');
    if (!ctx) return;
    
    if (incomeChart) incomeChart.destroy();
    
    incomeChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.map(d => d.month),
            datasets: [{
                label: 'درآمد',
                data: data.map(d => d.amount / 1000000),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                tension: 0.4,
                fill: true
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
}

function renderHabitsChart(data) {
    const ctx = document.getElementById('habitsChart');
    if (!ctx) return;
    
    if (habitsChart) habitsChart.destroy();
    
    habitsChart = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.day),
            datasets: [{
                label: 'عادت',
                data: data.map(d => d.count),
                backgroundColor: '#10b981'
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
}

function renderActivities(activities) {
    const container = document.getElementById('recent-activities');
    if (!container) return;
    
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
}

// ───────────────────────────────────────────────────────────────
// Incomes (بهبود یافته)
// ───────────────────────────────────────────────────────────────
async function loadIncomes() {
    const result = await apiCall('incomes.php');
    
    if (result.success && result.data) {
        const { incomes, stats } = result.data;
        
        // آمار بالای صفحه
        document.getElementById('income-total').textContent = stats.total_active;
        document.getElementById('income-monthly').textContent = formatMoney(stats.monthly_total);
        document.getElementById('income-inactive').textContent = stats.total_inactive;
        
        const container = document.getElementById('incomes-list');
        
        if (incomes.length === 0) {
            container.innerHTML = '<p class="center grey-text">درآمدی ثبت نشده</p>';
            return;
        }
        
        container.innerHTML = '<ul class="collection">' + incomes.map(inc => `
            <li class="collection-item" onclick="showIncomeDetail(${inc.id})" style="cursor: pointer;">
                <div>
                    <span class="title">${inc.client_name}</span>
                    ${inc.client_username ? `<a href="https://t.me/${inc.client_username.replace('@', '')}" target="_blank" class="grey-text" onclick="event.stopPropagation()"> @${inc.client_username.replace('@', '')}</a>` : ''}
                    <p class="grey-text">${inc.service_type}</p>
                    <p class="grey-text">مبلغ ماهانه: <strong>${formatMoney(inc.monthly_amount)}</strong></p>
                    <p class="grey-text">${inc.months} ماه فعال | کل دریافتی: ${formatMoney(inc.total_earned)}</p>
                    ${inc.days_until_payment ? `<p class="orange-text">🔔 ${inc.days_until_payment} روز تا پرداخت بعدی</p>` : ''}
                </div>
                <span class="secondary-content">
                    <span class="badge ${inc.is_active ? 'green' : 'grey'} white-text">${inc.is_active ? 'فعال' : 'غیرفعال'}</span><br>
                    <i class="material-icons grey-text">chevron_left</i>
                </span>
            </li>
        `).join('') + '</ul>';
    } else {
        document.getElementById('incomes-list').innerHTML = '<p class="center orange-text">⚠️ اتصال به API برقرار نیست</p>';
    }
}

// نمایش جزئیات درآمد
async function showIncomeDetail(incomeId) {
    if (hapticEnabled) tg.HapticFeedback.impactOccurred('medium');
    
    // TODO: ساخت صفحه جزئیات (در مرحله بعد)
    M.toast({ html: `جزئیات درآمد #${incomeId}`, classes: 'blue rounded' });
}

// ───────────────────────────────────────────────────────────────
// Habits (بهبود یافته)
// ───────────────────────────────────────────────────────────────
async function loadHabits() {
    const result = await apiCall('habits.php', { action: 'list' });
    
    if (result.success && result.data) {
        const { habits } = result.data;
        const container = document.getElementById('habits-list');
        
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
                        ${habit.total_completed} از ${habit.total_days} روز انجام شده
                    </p>
                </div>
            </li>
        `).join('') + '</ul>';
    } else {
        document.getElementById('habits-list').innerHTML = '<p class="center orange-text">⚠️ اتصال به API برقرار نیست</p>';
    }
}

async function toggleHabit(habitId, checked) {
    if (hapticEnabled) tg.HapticFeedback.impactOccurred('medium');
    
    const result = await apiCall('habits.php', { action: 'toggle', habit_id: habitId });
    
    if (result.success) {
        M.toast({ html: result.message, classes: 'green rounded' });
        loadHabits();
        loadDashboard();
    }
}

// ───────────────────────────────────────────────────────────────
// Other pages (Reminders, Notes)
// ───────────────────────────────────────────────────────────────
async function loadReminders() {
    const result = await apiCall('reminders.php');
    if (result.success && result.data) {
        // Render reminders...
    }
}

async function loadNotes() {
    const result = await apiCall('notes.php');
    if (result.success && result.data) {
        // Render notes...
    }
}

// ───────────────────────────────────────────────────────────────
// Dark Mode
// ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const darkToggle = document.getElementById('dark-mode-toggle');
    if (darkToggle) {
        darkToggle.addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
            if (hapticEnabled) tg.HapticFeedback.impactOccurred('medium');
        });
    }
});

// ───────────────────────────────────────────────────────────────
// App Init
// ───────────────────────────────────────────────────────────────
window.addEventListener('load', function() {
    console.log('🚀 App starting...');
    console.log('📍 API URL:', API_URL);
    
    initTelegramWebApp();
    initMaterialize();
    loadDashboard();
    
    setTimeout(() => {
        document.getElementById('splash-screen').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('splash-screen').style.display = 'none';
            document.getElementById('app').style.display = 'block';
        }, 500);
    }, 2000);
});

window.showPage = showPage;
window.toggleHabit = toggleHabit;
window.showIncomeDetail = showIncomeDetail;