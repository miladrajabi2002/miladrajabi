// ═══════════════════════════════════════════════════════════════
// Enhanced Telegram WebApp
// ═══════════════════════════════════════════════════════════════

const tg = window.Telegram.WebApp;
const API_URL = '../webapp/api/';

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
    
    // Dark mode
    if (tg.colorScheme === 'dark') {
        document.body.classList.add('dark-mode');
        const toggle = document.getElementById('dark-mode-toggle');
        if (toggle) toggle.checked = true;
    }
    
    // User data
    const user = tg.initDataUnsafe?.user;
    if (user) {
        userId = user.id;
        const userName = user.first_name || 'کاربر';
        
        document.getElementById('user-name').textContent = userName;
        document.getElementById('welcome-user').textContent = userName;
        
        const avatarUrl = user.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128`;
        document.getElementById('user-avatar').src = avatarUrl;
    } else {
        userId = 123456; // For testing
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
    if (el) {
        el.textContent = `${dayName}، ${time}`;
    }
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
        const response = await fetch(API_URL + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, ...data })
        });
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        M.toast({ html: 'خطا در ارتباط با سرور', classes: 'red rounded' });
        return { success: false };
    }
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
    
    if (result.success) {
        const { stats, income_chart, habits_chart, recent_activities } = result.data;
        
        // Update stats
        document.getElementById('stat-income').textContent = formatMoney(stats.monthly_income);
        document.getElementById('stat-reminders').textContent = stats.today_reminders;
        document.getElementById('stat-habits').textContent = `${stats.completed_habits}/${stats.total_habits}`;
        document.getElementById('stat-notes').textContent = stats.total_notes;
        
        // Charts
        renderIncomeChart(income_chart);
        renderHabitsChart(habits_chart);
        
        // Recent activities
        renderActivities(recent_activities);
    }
}

function renderIncomeChart(data) {
    const ctx = document.getElementById('incomeChart').getContext('2d');
    
    if (incomeChart) incomeChart.destroy();
    
    incomeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.month),
            datasets: [{
                label: 'درآمد (تومان)',
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
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => value + 'M'
                    }
                }
            }
        }
    });
}

function renderHabitsChart(data) {
    const ctx = document.getElementById('habitsChart').getContext('2d');
    
    if (habitsChart) habitsChart.destroy();
    
    habitsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.day),
            datasets: [{
                label: 'عادت انجام شده',
                data: data.map(d => d.count),
                backgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

function renderActivities(activities) {
    const container = document.getElementById('recent-activities');
    
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
// Incomes
// ───────────────────────────────────────────────────────────────
async function loadIncomes() {
    const result = await apiCall('incomes.php');
    
    if (result.success) {
        const { incomes, stats } = result.data;
        
        document.getElementById('income-total').textContent = stats.total_active;
        document.getElementById('income-monthly').textContent = formatMoney(stats.monthly_total);
        document.getElementById('income-inactive').textContent = stats.total_inactive;
        
        const container = document.getElementById('incomes-list');
        
        if (incomes.length === 0) {
            container.innerHTML = '<p class="center grey-text">درآمدی ثبت نشده</p>';
            return;
        }
        
        container.innerHTML = '<ul class="collection">' + incomes.map(inc => `
            <li class="collection-item">
                <div>
                    <span class="title">${inc.client_name}</span>
                    ${inc.client_username ? `<a href="https://t.me/${inc.client_username.replace('@', '')}" target="_blank" class="grey-text">@${inc.client_username.replace('@', '')}</a>` : ''}
                    <p class="grey-text">${inc.service_type}</p>
                    <p class="grey-text">مبلغ: ${formatMoney(inc.monthly_amount)} | ${inc.months} ماه | کل: ${formatMoney(inc.total_earned)}</p>
                    ${inc.payment_day ? `<p class="grey-text">روز پرداخت: ${inc.payment_day} هر ماه</p>` : ''}
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
    
    if (result.success) {
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
                        <span>${habit.name}</span>
                    </label>
                    <div class="progress" style="margin-top: 8px;">
                        <div class="determinate" style="width: ${habit.progress}%"></div>
                    </div>
                    <p class="grey-text">پیشرفت: ${habit.total_completed}/${habit.target_days} روز (${habit.progress}%)</p>
                </div>
            </li>
        `).join('') + '</ul>';
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
// Reminders
// ───────────────────────────────────────────────────────────────
async function loadReminders() {
    const result = await apiCall('reminders.php');
    
    if (result.success) {
        const { reminders } = result.data;
        const container = document.getElementById('reminders-list');
        
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

// ───────────────────────────────────────────────────────────────
// Notes
// ───────────────────────────────────────────────────────────────
async function loadNotes() {
    const result = await apiCall('notes.php');
    
    if (result.success) {
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
                    <p class="grey-text" style="font-size: 0.8rem; margin-top: 8px;">${note.created_at_fa}</p>
                </div>
            </div>
        `).join('');
    }
}

// ───────────────────────────────────────────────────────────────
// Utilities
// ───────────────────────────────────────────────────────────────
function formatMoney(amount) {
    if (amount >= 1000000) {
        return (amount / 1000000).toFixed(1) + 'M';
    }
    return new Intl.NumberFormat('fa-IR').format(amount);
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
            if (hapticEnabled) tg.HapticFeedback.impactOccurred('medium');
            M.toast({ html: this.checked ? 'حالت تاریک فعال شد' : 'حالت روشن فعال شد', classes: 'rounded' });
        });
    }
    
    if (hapticToggle) {
        hapticToggle.addEventListener('change', function() {
            hapticEnabled = this.checked;
            M.toast({ html: this.checked ? 'لرزش لمسی فعال شد' : 'لرزش لمسی غیرفعال شد', classes: 'rounded' });
        });
    }
});

// ───────────────────────────────────────────────────────────────
// App Initialization
// ───────────────────────────────────────────────────────────────
window.addEventListener('load', function() {
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

// Export functions
window.showPage = showPage;
window.toggleHabit = toggleHabit;