// ═══════════════════════════════════════════════════════════════
// Telegram WebApp JavaScript
// ═══════════════════════════════════════════════════════════════

let tg = window.Telegram.WebApp;

// ───────────────────────────────────────────────────────────────
// Initialize Telegram WebApp
// ───────────────────────────────────────────────────────────────
function initTelegramWebApp() {
    tg.ready();
    tg.expand();
    
    // Set theme
    if (tg.colorScheme === 'dark') {
        document.body.classList.add('dark-mode');
        document.getElementById('dark-mode-toggle').checked = true;
    }
    
    // Get user data
    const user = tg.initDataUnsafe?.user;
    if (user) {
        document.getElementById('user-name').textContent = user.first_name;
        document.getElementById('welcome-name').textContent = user.first_name;
        
        if (user.photo_url) {
            document.getElementById('user-avatar').src = user.photo_url;
        } else {
            document.getElementById('user-avatar').src = `https://ui-avatars.com/api/?name=${user.first_name}&background=667eea&color=fff`;
        }
    }
    
    // Set current date
    setCurrentDate();
}

// ───────────────────────────────────────────────────────────────
// Initialize Materialize Components
// ───────────────────────────────────────────────────────────────
function initMaterialize() {
    // Sidenav
    var elems = document.querySelectorAll('.sidenav');
    M.Sidenav.init(elems);
    
    // Floating Action Button
    var fab = document.querySelectorAll('.fixed-action-btn');
    M.FloatingActionButton.init(fab, {
        direction: 'top',
        hoverEnabled: false
    });
    
    // Tooltips
    var tooltips = document.querySelectorAll('.tooltipped');
    M.Tooltip.init(tooltips);
}

// ───────────────────────────────────────────────────────────────
// Page Navigation
// ───────────────────────────────────────────────────────────────
function showPage(pageName) {
    // Hide all pages
    const pages = document.querySelectorAll('.page');
    pages.forEach(page => page.classList.remove('active'));
    
    // Show selected page
    const selectedPage = document.getElementById(pageName + '-page');
    if (selectedPage) {
        selectedPage.classList.add('active');
    }
    
    // Update bottom nav
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    // Update header title
    const titles = {
        'dashboard': 'داشبورد',
        'incomes': 'درآمدها',
        'reminders': 'یادآورها',
        'notes': 'یادداشت‌ها',
        'habits': 'عادت‌ها',
        'settings': 'تنظیمات'
    };
    document.getElementById('page-title').textContent = titles[pageName] || 'داشبورد';
    
    // Close sidenav on mobile
    var sidenav = document.querySelector('.sidenav');
    var instance = M.Sidenav.getInstance(sidenav);
    if (instance) instance.close();
    
    // Load page data
    loadPageData(pageName);
    
    // Send event to Telegram
    tg.HapticFeedback.impactOccurred('light');
}

// ───────────────────────────────────────────────────────────────
// Load Page Data
// ───────────────────────────────────────────────────────────────
function loadPageData(pageName) {
    switch(pageName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'incomes':
            loadIncomes();
            break;
        case 'reminders':
            loadReminders();
            break;
        case 'notes':
            loadNotes();
            break;
        case 'habits':
            loadHabits();
            break;
    }
}

// ───────────────────────────────────────────────────────────────
// Load Dashboard Data
// ───────────────────────────────────────────────────────────────
function loadDashboard() {
    // Simulate API call
    setTimeout(() => {
        // Update stats
        document.getElementById('total-income').textContent = '10.5M';
        document.getElementById('total-reminders').textContent = '5';
        
        // Show toast
        M.toast({html: 'داشبورد بروزرسانی شد', classes: 'rounded'});
    }, 500);
}

// ───────────────────────────────────────────────────────────────
// Load Incomes
// ───────────────────────────────────────────────────────────────
function loadIncomes() {
    const container = document.getElementById('incomes-list');
    container.innerHTML = '';
    
    // Simulate data
    const incomes = [
        { name: 'مشتری A', amount: '5,000,000', service: 'پشتیبانی سرور', date: '15 آذر' },
        { name: 'مشتری B', amount: '3,000,000', service: 'هاست', date: '10 آذر' },
        { name: 'مشتری C', amount: '2,500,000', service: 'VPS', date: '5 آذر' }
    ];
    
    incomes.forEach(income => {
        const item = document.createElement('div');
        item.className = 'collection-item';
        item.innerHTML = `
            <div>
                <span class="title">${income.name}</span>
                <p class="grey-text">${income.service}</p>
                <p class="grey-text">تاریخ پرداخت: ${income.date}</p>
            </div>
            <span class="secondary-content">
                <strong class="gradient-text">${income.amount} ت</strong>
            </span>
        `;
        container.appendChild(item);
    });
}

// ───────────────────────────────────────────────────────────────
// Load Reminders
// ───────────────────────────────────────────────────────────────
function loadReminders() {
    const container = document.getElementById('reminders-list');
    container.innerHTML = '';
    
    const reminders = [
        { title: 'جلسه با تیم', time: '10:00', type: 'meeting' },
        { title: 'تماس با مشتری', time: '14:30', type: 'call' },
        { title: 'پرداخت قبض برق', time: '18:00', type: 'payment' }
    ];
    
    const icons = {
        'meeting': 'groups',
        'call': 'phone',
        'payment': 'payment'
    };
    
    reminders.forEach(reminder => {
        const item = document.createElement('div');
        item.className = 'collection-item avatar';
        item.innerHTML = `
            <i class="material-icons circle blue">${icons[reminder.type]}</i>
            <span class="title">${reminder.title}</span>
            <p class="grey-text">${reminder.time}</p>
        `;
        container.appendChild(item);
    });
}

// ───────────────────────────────────────────────────────────────
// Load Notes
// ───────────────────────────────────────────────────────────────
function loadNotes() {
    const container = document.getElementById('notes-list');
    container.innerHTML = '';
    
    const notes = [
        { title: 'ایده پروژه جدید', content: 'ساخت یک اپلیکیشن موبایل برای...', date: 'امروز' },
        { title: 'لیست خرید', content: 'شیر، نان، تخم مرغ، ماست', date: 'دیروز' }
    ];
    
    notes.forEach(note => {
        const card = document.createElement('div');
        card.className = 'card z-depth-1';
        card.innerHTML = `
            <div class="card-content">
                <span class="card-title">${note.title}</span>
                <p class="grey-text">${note.content}</p>
                <p class="grey-text" style="font-size: 0.8rem; margin-top: 8px;">${note.date}</p>
            </div>
        `;
        container.appendChild(card);
    });
}

// ───────────────────────────────────────────────────────────────
// Load Habits
// ───────────────────────────────────────────────────────────────
function loadHabits() {
    const container = document.getElementById('habits-list');
    container.innerHTML = '';
    
    const habits = [
        { name: 'ورزش صبحگاهی', done: true },
        { name: 'مطالعه کتاب', done: false },
        { name: 'نوشیدن آب', done: true },
        { name: 'مدیتیشن', done: false }
    ];
    
    const list = document.createElement('ul');
    list.className = 'collection';
    
    habits.forEach(habit => {
        const item = document.createElement('li');
        item.className = 'collection-item';
        item.innerHTML = `
            <div>
                <label>
                    <input type="checkbox" class="filled-in" ${habit.done ? 'checked' : ''} />
                    <span style="font-size: 0.95rem;">${habit.name}</span>
                </label>
            </div>
        `;
        list.appendChild(item);
    });
    
    container.appendChild(list);
}

// ───────────────────────────────────────────────────────────────
// Dark Mode Toggle
// ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
            tg.HapticFeedback.impactOccurred('medium');
            M.toast({html: this.checked ? 'حالت تاریک فعال شد' : 'حالت روشن فعال شد', classes: 'rounded'});
        });
    }
});

// ───────────────────────────────────────────────────────────────
// Set Current Date
// ───────────────────────────────────────────────────────────────
function setCurrentDate() {
    const days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
    const date = new Date();
    const dayName = days[date.getDay()];
    document.getElementById('current-date').textContent = `امروز ${dayName}`;
}

// ───────────────────────────────────────────────────────────────
// Initialize App
// ───────────────────────────────────────────────────────────────
window.addEventListener('load', function() {
    initTelegramWebApp();
    initMaterialize();
    loadDashboard();
    
    // Hide preloader
    setTimeout(() => {
        document.getElementById('preloader').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('preloader').style.display = 'none';
            document.getElementById('app').style.display = 'block';
        }, 500);
    }, 1500);
});

// ───────────────────────────────────────────────────────────────
// Handle Telegram Back Button
// ───────────────────────────────────────────────────────────────
tg.BackButton.onClick(function() {
    tg.close();
});

// ───────────────────────────────────────────────────────────────
// Export functions for inline use
// ───────────────────────────────────────────────────────────────
window.showPage = showPage;