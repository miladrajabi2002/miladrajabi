// ══════════════════════════════════════════════════════════════
// Telegram WebApp - Final Fixed Version
// ══════════════════════════════════════════════════════════════

const tg = window.Telegram?.WebApp || {};
const API_URL = './api/';
const ALLOWED_USER_ID = 1253939828;

let userId = null;
let userName = 'میلاد';
let userPhoto = null;
let hapticEnabled = true;
let incomeChart = null;
let habitsChart = null;
let incomeDetailChart = null;

// ────────────────────────────────────────────────────────────────
// Jalaali Date
// ────────────────────────────────────────────────────────────────
function getJalaaliDate() {
    const now = new Date();
    const days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
    const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    
    // تقریب ساده تاریخ جلالی (برای نمایش)
    const dayName = days[now.getDay()];
    const gYear = now.getFullYear();
    const gMonth = now.getMonth() + 1;
    const gDay = now.getDate();
    
    // تبدیل تقریبی میلادی به شمسی
    const jYear = gYear - 621;
    let jMonth = gMonth - 3;
    let jDay = gDay;
    
    if (jMonth <= 0) {
        jMonth += 12;
    }
    
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    function toPersian(n) {
        return String(n).split('').map(c => 
            c >= '0' && c <= '9' ? persianDigits[parseInt(c)] : c
        ).join('');
    }
    
    return `${dayName}، ${toPersian(jDay)} ${months[jMonth - 1]} ${toPersian(jYear)}`;
}

// ────────────────────────────────────────────────────────────────
// Initialize
// ────────────────────────────────────────────────────────────────
function initTelegramWebApp() {
    if (tg.ready) tg.ready();
    if (tg.expand) tg.expand();
    
    const user = tg.initDataUnsafe?.user;
    if (user) {
        userId = user.id;
        userName = user.first_name || 'میلاد';
        userPhoto = user.photo_url;
        
        console.log('👤 User:', userName, '| ID:', userId);
        
        if (userId !== ALLOWED_USER_ID) {
            showAccessDenied();
            return;
        }
    } else {
        userId = ALLOWED_USER_ID;
        console.log('⚠️ Testing mode');
    }
    
    updateUserInfo();
    
    if (tg.colorScheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
    
    updateDateTime();
    setInterval(updateDateTime, 10000); // هر 10 ثانیه
}

function updateUserInfo() {
    // نام کاربر
    const userNameEl = document.getElementById('user-name');
    const welcomeUserEl = document.getElementById('welcome-user');
    if (userNameEl) userNameEl.textContent = userName;
    if (welcomeUserEl) welcomeUserEl.textContent = userName;
    
    // User ID در تنظیمات
    const userIdEl = document.getElementById('user-id');
    if (userIdEl) userIdEl.textContent = userId || '-';
    
    // عکس پروفایل
    const avatarEl = document.getElementById('user-avatar');
    if (avatarEl) {
        const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=6366f1&color=fff&size=128&bold=true`;
        avatarEl.src = userPhoto || fallbackUrl;
        avatarEl.onerror = () => avatarEl.src = fallbackUrl;
    }
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
    const el = document.getElementById('current-date-time');
    if (el) el.textContent = getJalaaliDate();
}

function initMaterialize() {
    if (typeof M !== 'undefined') {
        const sidenavElems = document.querySelectorAll('.sidenav');
        if (sidenavElems.length > 0) M.Sidenav.init(sidenavElems);
        
        const fabElems = document.querySelectorAll('.fixed-action-btn');
        if (fabElems.length > 0) M.FloatingActionButton.init(fabElems);
    }
}

// ────────────────────────────────────────────────────────────────
// API Calls
// ────────────────────────────────────────────────────────────────
async function apiCall(endpoint, data = {}) {
    try {
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
        console.log('✅', endpoint, '→', result.success ? 'OK' : 'FAIL');
        return result;
        
    } catch (error) {
        console.error('❌', endpoint, '→', error.message);
        return { success: false, error: error.message };
    }
}

// ────────────────────────────────────────────────────────────────
// Format Money
// ────────────────────────────────────────────────────────────────
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

// ────────────────────────────────────────────────────────────────
// Page Navigation
// ────────────────────────────────────────────────────────────────
function showPage(pageName) {
    console.log('📄', pageName);
    
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(pageName + '-page');
    if (targetPage) targetPage.classList.add('active');
    
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    
    const titles = {
        dashboard: 'داشبورد',
        incomes: 'درآمدها',
        'income-detail': 'جزئیات درآمد',
        reminders: 'یادآورها',
        notes: 'یادداشت‌ها',
        habits: 'عادت‌ها',
        settings: 'تنظیمات'
    };
    
    const titleEl = document.getElementById('page-title');
    if (titleEl) titleEl.textContent = titles[pageName] || 'داشبورد';
    
    if (typeof M !== 'undefined') {
        const sidenavElem = document.querySelector('.sidenav');
        if (sidenavElem) {
            const instance = M.Sidenav.getInstance(sidenavElem);
            if (instance) instance.close();
        }
    }
    
    loadPageData(pageName);
    
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

// ────────────────────────────────────────────────────────────────
// Dashboard
// ────────────────────────────────────────────────────────────────
async function loadDashboard() {
    console.log('📊 Loading dashboard...');
    
    // نمایش loading
    const habitsEl = document.getElementById('stat-habits');
    if (habitsEl) habitsEl.textContent = '...';
    
    const result = await apiCall('dashboard.php');
    
    if (result.success && result.data) {
        const { stats, income_chart, habits_chart, recent_activities } = result.data;
        
        // آمار اصلی
        const incomeEl = document.getElementById('stat-income');
        const remindersEl = document.getElementById('stat-reminders');
        const notesEl = document.getElementById('stat-notes');
        
        if (incomeEl) incomeEl.textContent = formatMoney(stats.monthly_income);
        if (remindersEl) remindersEl.textContent = stats.today_reminders || 0;
        if (notesEl) notesEl.textContent = stats.total_notes || 0;
        
        // عادت‌های امروز
        if (habitsEl) {
            if (stats.total_habits > 0) {
                habitsEl.textContent = `${stats.completed_habits || 0}/${stats.total_habits}`;
            } else {
                habitsEl.textContent = 'ندارید';
            }
        }
        
        // نمودارها
        if (income_chart && income_chart.length > 0) {
            renderIncomeChart(income_chart);
        }
        
        if (habits_chart && habits_chart.length > 0) {
            renderHabitsChart(habits_chart);
        }
        
        // فعالیت‌ها
        const activitiesContainer = document.getElementById('recent-activities');
        if (activitiesContainer) {
            if (recent_activities && recent_activities.length > 0) {
                activitiesContainer.innerHTML = recent_activities.map(act => `
                    <li class="collection-item avatar">
                        <i class="material-icons circle ${act.color}">${act.icon}</i>
                        <span class="title">${act.title}</span>
                        <p class="grey-text">${act.time}</p>
                    </li>
                `).join('');
            } else {
                activitiesContainer.innerHTML = '<li class="collection-item center grey-text">فعالیتی ثبت نشده</li>';
            }
        }
        
        console.log('✅ Dashboard OK');
    } else {
        if (habitsEl) habitsEl.textContent = 'خطا';
        console.error('❌ Dashboard failed');
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
}

// ────────────────────────────────────────────────────────────────
// Incomes
// ────────────────────────────────────────────────────────────────
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
        
        container.innerHTML = incomes.map(inc => `
            <div class="card hoverable" style="margin-bottom: 16px; cursor: pointer;" onclick="showIncomeDetail(${inc.id})">
                <div class="card-content">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                        <div>
                            <span class="card-title" style="font-size: 1.2rem; font-weight: bold; margin: 0;">${inc.client_name}</span>
                            ${inc.client_username ? `<p style="margin: 4px 0;"><a href="https://t.me/${inc.client_username.replace('@', '')}" target="_blank" class="blue-text" onclick="event.stopPropagation()">@${inc.client_username.replace('@', '')}</a></p>` : ''}
                        </div>
                        <span class="badge ${inc.is_active ? 'green' : 'grey'} white-text" style="padding: 4px 12px;">${inc.is_active ? 'فعال' : 'غیرفعال'}</span>
                    </div>
                    
                    <p class="grey-text" style="margin: 8px 0;">
                        <i class="material-icons tiny" style="vertical-align: middle;">business_center</i>
                        ${inc.service_type}
                    </p>
                    
                    <div style="background: #f5f5f5; padding: 12px; border-radius: 8px; margin-top: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>مبلغ ماهانه:</span>
                            <strong class="green-text">${formatMoney(inc.monthly_amount)}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>مدت فعالیت:</span>
                            <strong>${inc.months} ماه</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>کل دریافتی:</span>
                            <strong class="blue-text">${formatMoney(inc.total_earned)}</strong>
                        </div>
                    </div>
                    
                    ${inc.days_until_payment ? `
                        <p class="orange-text" style="margin-top: 12px; text-align: center;">
                            <i class="material-icons tiny" style="vertical-align: middle;">alarm</i>
                            ${inc.days_until_payment} روز تا پرداخت بعدی
                        </p>
                    ` : ''}
                    
                    <p class="center grey-text" style="margin-top: 12px; font-size: 0.9rem;">
                        <i class="material-icons tiny" style="vertical-align: middle;">touch_app</i>
                        کلیک برای جزئیات بیشتر
                    </p>
                </div>
            </div>
        `).join('');
        
        console.log('✅ Incomes loaded:', incomes.length);
    }
}

// ────────────────────────────────────────────────────────────────
// Income Detail
// ────────────────────────────────────────────────────────────────
async function showIncomeDetail(incomeId) {
    if (hapticEnabled && tg.HapticFeedback) tg.HapticFeedback.impactOccurred('medium');
    
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const detailPage = document.getElementById('income-detail-page');
    if (detailPage) detailPage.classList.add('active');
    
    const titleEl = document.getElementById('page-title');
    if (titleEl) titleEl.textContent = 'جزئیات درآمد';
    
    const container = document.getElementById('income-detail-content');
    if (container) container.innerHTML = '<div class="center"><div class="preloader-wrapper active"><div class="spinner-layer spinner-blue-only"><div class="circle-clipper left"><div class="circle"></div></div><div class="gap-patch"><div class="circle"></div></div><div class="circle-clipper right"><div class="circle"></div></div></div></div></div>';
    
    const result = await apiCall('income_details.php', { income_id: incomeId });
    
    if (result.success && result.data) {
        const { income, stats, monthly_chart } = result.data;
        
        if (!container) return;
        
        container.innerHTML = `
            <div class="card">
                <div class="card-content">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h5 style="margin: 0 0 8px 0;">${income.client_name}</h5>
                            ${income.client_username ? `<p class="blue-text">@${income.client_username.replace('@', '')}</p>` : ''}
                        </div>
                        <span class="badge ${income.is_active ? 'green' : 'grey'} white-text">${income.is_active ? 'فعال' : 'غیرفعال'}</span>
                    </div>
                    
                    <p class="grey-text" style="margin-top: 16px;">${income.service_type}</p>
                    
                    <div style="margin-top: 24px;">
                        <h6>اطلاعات کلی</h6>
                        <table class="striped">
                            <tbody>
                                <tr>
                                    <td>مبلغ ماهانه</td>
                                    <td class="left-align"><strong class="green-text">${formatMoney(income.monthly_amount)}</strong></td>
                                </tr>
                                <tr>
                                    <td>روز پرداخت</td>
                                    <td class="left-align">${income.payment_day ? income.payment_day + ' هر ماه' : '-'}</td>
                                </tr>
                                <tr>
                                    <td>تاریخ شروع</td>
                                    <td class="left-align">${income.start_date_fa}</td>
                                </tr>
                                ${income.bot_url ? `
                                <tr>
                                    <td>ربات</td>
                                    <td class="left-align"><a href="${income.bot_url}" target="_blank" class="blue-text">مشاهده</a></td>
                                </tr>
                                ` : ''}
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <h6>آمار عملکرد</h6>
                        <div class="row" style="margin-top: 16px;">
                            <div class="col s6">
                                <div style="text-align: center; padding: 16px; background: #e3f2fd; border-radius: 8px;">
                                    <p class="grey-text" style="margin: 0; font-size: 0.9rem;">مدت فعالیت</p>
                                    <h5 style="margin: 8px 0 0 0; color: #1976d2;">${stats.months_active} ماه</h5>
                                </div>
                            </div>
                            <div class="col s6">
                                <div style="text-align: center; padding: 16px; background: #e8f5e9; border-radius: 8px;">
                                    <p class="grey-text" style="margin: 0; font-size: 0.9rem;">کل دریافتی</p>
                                    <h5 style="margin: 8px 0 0 0; color: #388e3c;">${formatMoney(stats.total_earned)}</h5>
                                </div>
                            </div>
                        </div>
                        
                        ${stats.days_until_payment > 0 ? `
                        <div style="text-align: center; padding: 16px; background: #fff3e0; border-radius: 8px; margin-top: 16px;">
                            <p class="grey-text" style="margin: 0; font-size: 0.9rem;">روزهای باقیمانده تا پرداخت</p>
                            <h5 style="margin: 8px 0 0 0; color: #f57c00;">${stats.days_until_payment} روز</h5>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-top: 16px;">
                <div class="card-content">
                    <h6>نمودار درآمد ۱۲ ماه اخیر</h6>
                    <div style="height: 250px; margin-top: 16px;">
                        <canvas id="incomeDetailChart"></canvas>
                    </div>
                </div>
            </div>
            
            <button class="btn waves-effect waves-light blue" onclick="showPage('incomes')" style="width: 100%; margin-top: 16px;">
                <i class="material-icons left">arrow_forward</i>
                بازگشت به لیست
            </button>
        `;
        
        renderIncomeDetailChart(monthly_chart);
        console.log('✅ Income detail loaded');
    } else {
        if (container) container.innerHTML = '<div class="card"><div class="card-content center"><p class="red-text">خطا در بارگذاری جزئیات</p><button class="btn blue" onclick="showPage(\'incomes\')">بازگشت</button></div></div>';
    }
}

function renderIncomeDetailChart(data) {
    const ctx = document.getElementById('incomeDetailChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    if (incomeDetailChart) incomeDetailChart.destroy();
    
    incomeDetailChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.map(d => d.month),
            datasets: [{
                label: 'درآمد',
                data: data.map(d => {
                    const num = typeof d.amount === 'string' ? parseFloat(d.amount) : d.amount;
                    return Math.ceil(num / 1000000);
                }),
                borderColor: '#2196f3',
                backgroundColor: 'rgba(33, 150, 243, 0.1)',
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
}

// ────────────────────────────────────────────────────────────────
// Habits
// ────────────────────────────────────────────────────────────────
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

// ────────────────────────────────────────────────────────────────
// Other Pages
// ────────────────────────────────────────────────────────────────
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

// ────────────────────────────────────────────────────────────────
// Settings
// ────────────────────────────────────────────────────────────────
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

// ────────────────────────────────────────────────────────────────
// App Init
// ────────────────────────────────────────────────────────────────
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
        }, 1000); // کاهش زمان splash
    }
});

// Export
window.showPage = showPage;
window.toggleHabit = toggleHabit;
window.showIncomeDetail = showIncomeDetail;