// ═══════════════════════════════════════════════════════════════
// Modern Blue Theme WebApp - Enhanced Edition
// ═══════════════════════════════════════════════════════════════

const tg = window.Telegram.WebApp;
const API_URL = './api/';

let userId = null;
let hapticEnabled = true;
let incomeChart = null;
let habitsChart = null;

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
        const avatarUrl = user.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=1976D2&color=fff&size=128`;
        
        document.getElementById('welcome-user').textContent = userName;
        document.getElementById('user-avatar-main').src = avatarUrl;
        document.getElementById('user-avatar-settings').src = avatarUrl;
        document.getElementById('user-name-settings').textContent = userName;
        document.getElementById('user-id-settings').textContent = `ID: ${userId}`;
    } else {
        userId = 123456;
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
}

function updateDateTime() {
    const now = new Date();
    const days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
    const time = now.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    
    const el = document.getElementById('current-date-time');
    if (el) el.textContent = `${days[now.getDay()]}، ${time}`;
}

// ───────────────────────────────────────────────────────────────
// API
// ───────────────────────────────────────────────────────────────
async function apiCall(endpoint, data = {}) {
    try {
        const response = await fetch(API_URL + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, ...data })
        });
        return await response.json();
    } catch (error) {
        console.error('❌ API Error:', error);
        M.toast({ html: 'خطا در ارتباط', classes: 'red rounded' });
        return { success: false, demo: true };
    }
}

// ───────────────────────────────────────────────────────────────
// Page Navigation
// ───────────────────────────────────────────────────────────────
function showPage(pageName) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(pageName + '-page').classList.add('active');
    
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    const activeNav = document.querySelector(`[data-page="${pageName}"]`);
    if (activeNav) activeNav.classList.add('active');
    
    const titles = {
        dashboard: 'داشبورد',
        incomes: 'درآمدها',
        reminders: 'یادآورها',
        notes: 'یادداشت‌ها',
        habits: 'عادت‌ها',
        settings: 'تنظیمات'
    };
    document.getElementById('page-title').textContent = titles[pageName];
    
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
        const { stats, income_chart, habits_chart } = result.data;
        
        // Stats
        document.getElementById('stat-income').textContent = formatMoney(stats.monthly_income);
        
        // Habits success rate
        const rate = stats.total_habits > 0 ? Math.round((stats.completed_habits / stats.total_habits) * 100) : 0;
        document.getElementById('stat-habits-rate').textContent = `${toPersianNum(rate)}%`;
        
        // Summary
        document.getElementById('summary-reminders').textContent = toPersianNum(stats.today_reminders);
        document.getElementById('summary-notes').textContent = toPersianNum(stats.total_notes);
        document.getElementById('habits-badge').textContent = `${toPersianNum(stats.completed_habits)}/${toPersianNum(stats.total_habits)}`;
        
        // Habits today
        await loadHabitsToday();
        
        // Charts
        renderIncomeChart(income_chart);
        renderHabitsChart(habits_chart);
    } else {
        loadDemoData();
    }
}

async function loadHabitsToday() {
    const result = await apiCall('habits.php', { action: 'list' });
    
    if (result.success && result.data) {
        const { habits } = result.data;
        const container = document.getElementById('habits-today-list');
        
        if (habits.length === 0) {
            container.innerHTML = '<div class="center grey-text">عادتی ثبت نشده</div>';
            return;
        }
        
        container.innerHTML = habits.map(habit => `
            <div class="habit-item" onclick="toggleHabitQuick(${habit.id}, ${!habit.is_completed_today})">
                <div class="habit-checkbox ${habit.is_completed_today ? 'checked' : ''}">
                    ${habit.is_completed_today ? '<i class="material-icons">check</i>' : ''}
                </div>
                <div class="habit-info">
                    <div class="habit-name">${habit.name}</div>
                    <div class="habit-progress-bar">
                        <div class="habit-progress-fill" style="width: ${habit.progress}%"></div>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

function loadDemoData() {
    document.getElementById('stat-income').textContent = '10.5M';
    document.getElementById('stat-habits-rate').textContent = '۶۲%';
    document.getElementById('summary-reminders').textContent = '۵';
    document.getElementById('summary-notes').textContent = '۱۲';
    
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
                borderColor: '#1976D2',
                backgroundColor: 'rgba(25, 118, 210, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => v + 'M' },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false }
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
                backgroundColor: '#4CAF50',
                borderRadius: 6,
                barThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

// ───────────────────────────────────────────────────────────────
// Incomes
// ───────────────────────────────────────────────────────────────
async function loadIncomes() {
    const result = await apiCall('incomes.php');
    
    if (result.success && result.data) {
        const { incomes, stats } = result.data;
        
        document.getElementById('income-total-amount').textContent = formatMoney(stats.monthly_total);
        document.getElementById('income-sources-count').textContent = `از ${toPersianNum(stats.total_active + stats.total_inactive)} منبع`;
        document.getElementById('income-active').textContent = toPersianNum(stats.total_active);
        document.getElementById('income-inactive').textContent = toPersianNum(stats.total_inactive);
        
        const container = document.getElementById('incomes-list');
        
        if (incomes.length === 0) {
            container.innerHTML = '<p class="center grey-text">درآمدی ثبت نشده</p>';
            return;
        }
        
        container.innerHTML = incomes.map(inc => `
            <div class="income-card">
                <div class="income-header">
                    <div class="income-name">${inc.client_name}</div>
                    <span class="income-badge ${inc.is_active ? 'active' : 'inactive'}">
                        ${inc.is_active ? 'فعال' : 'غیرفعال'}
                    </span>
                </div>
                <p class="grey-text">${inc.service_type}</p>
                <div class="income-details">
                    <span><strong>${formatMoney(inc.monthly_amount)}</strong> / ماه</span>
                    <span>${toPersianNum(inc.months)} ماه = ${formatMoney(inc.total_earned)}</span>
                </div>
            </div>
        `).join('');
    }
}

// ───────────────────────────────────────────────────────────────
// Habits
// ───────────────────────────────────────────────────────────────
async function loadHabits() {
    const result = await apiCall('habits.php', { action: 'list' });
    
    if (result.success && result.data) {
        const { habits } = result.data;
        const completed = habits.filter(h => h.is_completed_today).length;
        const rate = habits.length > 0 ? Math.round((completed / habits.length) * 100) : 0;
        
        document.getElementById('habits-success-rate').textContent = `${toPersianNum(rate)}%`;
        document.getElementById('habits-completed-today').textContent = `${toPersianNum(completed)} از ${toPersianNum(habits.length)} عادت`;
        
        const container = document.getElementById('habits-list');
        
        if (habits.length === 0) {
            container.innerHTML = '<p class="center grey-text">عادتی ثبت نشده</p>';
            return;
        }
        
        container.innerHTML = habits.map(habit => `
            <div class="habit-item" onclick="toggleHabitQuick(${habit.id}, ${!habit.is_completed_today})">
                <div class="habit-checkbox ${habit.is_completed_today ? 'checked' : ''}">
                    ${habit.is_completed_today ? '<i class="material-icons">check</i>' : ''}
                </div>
                <div class="habit-info">
                    <div class="habit-name">${habit.name}</div>
                    <div class="habit-progress">پیشرفت: ${toPersianNum(habit.total_completed)}/${toPersianNum(habit.target_days)} (۶${toPersianNum(habit.progress)}%)</div>
                    <div class="habit-progress-bar">
                        <div class="habit-progress-fill" style="width: ${habit.progress}%"></div>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

async function toggleHabitQuick(habitId, newState) {
    if (hapticEnabled) tg.HapticFeedback.impactOccurred('medium');
    
    const result = await apiCall('habits.php', { action: 'toggle', habit_id: habitId });
    
    if (result.success) {
        loadDashboard();
        loadHabits();
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
        
        if (reminders.length === 0) {
            container.innerHTML = '<p class="center grey-text">یادآوری ندارید</p>';
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

// ───────────────────────────────────────────────────────────────
// Notes
// ───────────────────────────────────────────────────────────────
async function loadNotes() {
    const result = await apiCall('notes.php');
    
    if (result.success && result.data) {
        const { notes } = result.data;
        const container = document.getElementById('notes-list');
        
        if (notes.length === 0) {
            container.innerHTML = '<p class="center grey-text">یادداشتی ندارید</p>';
            return;
        }
        
        container.innerHTML = notes.map(note => `
            <div class="card hoverable">
                <div class="card-content">
                    <p>${note.preview}</p>
                    <p class="grey-text small" style="margin-top: 8px;">${note.created_at_fa}</p>
                </div>
            </div>
        `).join('');
    }
}

// ───────────────────────────────────────────────────────────────
// Utilities
// ───────────────────────────────────────────────────────────────
function formatMoney(amount) {
    if (!amount) return '0';
    if (amount >= 1000000) return toPersianNum((amount / 1000000).toFixed(1)) + 'M';
    return new Intl.NumberFormat('fa-IR').format(amount);
}

function toPersianNum(num) {
    const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return String(num).replace(/\d/g, d => persian[d]);
}

// ───────────────────────────────────────────────────────────────
// Settings
// ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const darkToggle = document.getElementById('dark-mode-toggle');
    const hapticToggle = document.getElementById('haptic-toggle');
    
    if (darkToggle) {
        darkToggle.addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
            if (hapticEnabled) tg.HapticFeedback.impactOccurred('medium');
            M.toast({ html: this.checked ? 'حالت تاریک فعال شد' : 'حالت روشن فعال شد', classes: 'blue rounded' });
        });
    }
    
    if (hapticToggle) {
        hapticToggle.addEventListener('change', function() {
            hapticEnabled = this.checked;
        });
    }
});

// ───────────────────────────────────────────────────────────────
// Init
// ───────────────────────────────────────────────────────────────
window.addEventListener('load', function() {
    initTelegramWebApp();
    loadDashboard();
    
    setTimeout(() => {
        document.getElementById('splash-screen').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('splash-screen').style.display = 'none';
            document.getElementById('app').style.display = 'block';
        }, 500);
    }, 1500);
});

window.showPage = showPage;