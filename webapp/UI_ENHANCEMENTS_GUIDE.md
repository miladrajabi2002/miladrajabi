# 🎨 راهنمای استفاده از بهبودهای UI/UX

این فایل‌ها بهبودهای UI/UX را **بدون تغییر کدهای فعلی** به پروژه شما اضافه می‌کنند.

---

## 📦 فایل‌های اضافه شده

1. **`webapp/css/ui-enhancements.css`** - استایل‌های جدید
2. **`webapp/js/ui-helpers.js`** - توابع کمکی JavaScript
3. **`webapp/UI_ENHANCEMENTS_GUIDE.md`** - این فایل راهنما

---

## 🚀 نصب و راه‌اندازی

### گام 1: اضافه کردن فایل‌ل‌ها به HTML

به فایل `index.html` بروید و قبل از تگ `</head>` این خط را اضافه کنید:

```html
<!-- UI Enhancements CSS -->
<link rel="stylesheet" href="./css/ui-enhancements.css">
```

و قبل از تگ `</body>` این خط را اضافه کنید:

```html
<!-- UI Helpers JavaScript -->
<script src="./js/ui-helpers.js"></script>
```

### گام 2: آماده استفاده!

حالا می‌توانید از تمام قابلیت‌ها استفاده کنید.

---

## 🎉 قابلیت‌ها و نحوه استفاده

### 1️⃣ Toast Notifications

#### نحوه استفاده:

```javascript
// پیغام موفقیت
showToast('عملیات با موفقیت انجام شد', 'success');

// پیغام خطا
showToast('خطا رخ داد', 'error');

// پیغام هشدار
showToast('توجه داشته باشید', 'warning');

// پیغام اطلاعاتی
showToast('اطلاعات به‌روزرسانی شد', 'info');

// با مدت زمان سفارشی (5 ثانیه)
showToast('این پیؾام 5 ثانیه نمایش داده می‌شود', 'info', 5000);
```

#### مثال کاربردی در کد فعلی:

```javascript
// در تابع toggleHabit
async function toggleHabit(habitId) {
    const result = await apiCall('habits.php', { action: 'toggle', habit_id: habitId });
    
    if (result.success) {
        showToast(result.message || 'ذخیره شد', 'success');
        loadHabits();
    } else {
        showToast('خطا در ذخیره', 'error');
    }
}
```

---

### 2️⃣ Skeleton Loading

#### نحوه استفاده:

```javascript
// نمایش skeleton حین بارگذاری
const container = document.getElementById('habits-list');
showSkeleton(container, 3, 'card'); // 3 کارت

// بعد از دریافت داده‌ها
const htmlContent = generateHabitsList(data);
hideSkeleton(container, htmlContent);
```

#### انواع Skeleton:

```javascript
// Card skeleton
showSkeleton(container, 3, 'card');

// List skeleton (با آواتار)
showSkeleton(container, 5, 'list');

// Text skeleton
showSkeleton(container, 4, 'text');
```

#### مثال کاربردی:

```javascript
async function loadHabits() {
    const container = document.getElementById('habits-list');
    
    // نمایش skeleton
    showSkeleton(container, 3, 'card');
    
    const result = await apiCall('habits.php', { action: 'list' });
    
    if (result.success) {
        const html = generateHabitsHTML(result.data.habits);
        hideSkeleton(container, html);
    }
}
```

---

### 3️⃣ Modern Stat Cards

#### نحوه استفاده:

```javascript
const statHTML = UIHelpers.createModernStatCard({
    icon: 'trending_up',
    iconColor: 'green',
    label: 'درآمد ماه',
    value: '25 میلیون',
    change: '12% نسبت به ماه قبل',
    changeType: 'positive'
});

container.innerHTML = statHTML;
```

#### رنگ‌های موجود:
- `''` (پیش‌فرض - آبی)
- `'green'` (سبز)
- `'orange'` (نارنجی)
- `'purple'` (بنفش)

#### مثال کامل:

```javascript
// کارت درآمد
const incomeCard = UIHelpers.createModernStatCard({
    icon: 'account_balance_wallet',
    iconColor: 'green',
    label: 'درآمد ماهانه',
    value: '50 میلیون',
    change: '+15% نسبت به ماه قبل',
    changeType: 'positive'
});

// کارت عادت‌ها
const habitsCard = UIHelpers.createModernStatCard({
    icon: 'check_circle',
    iconColor: 'purple',
    label: 'عادت‌های انجام شده',
    value: '8/10',
    change: 'عملکرد عالی',
    changeType: 'positive'
});
```

---

### 4️⃣ Circular Progress

#### نحوه استفاده:

```javascript
// Progress ساده
const progressHTML = UIHelpers.createCircularProgress(75);
container.innerHTML = progressHTML;

// با رنگ سفارشی
const progressHTML = UIHelpers.createCircularProgress(85, 'success');

// با اندازه سفارشی
const progressHTML = UIHelpers.createCircularProgress(90, 'warning', 'large');
```

#### رنگ‌ها:
- `''` (پیش‌فرض - آبی)
- `'success'` (سبز)
- `'warning'` (نارنجی)
- `'danger'` (قرمز)

#### اندازه‌ها:
- `'small'` (48px)
- `'medium'` (64px - پیش‌فرض)
- `'large'` (80px)

#### مثال کاربردی:

```javascript
// در لیست عادت‌ها
habits.forEach(habit => {
    const progressHTML = UIHelpers.createCircularProgress(
        habit.success_rate,
        habit.success_rate >= 70 ? 'success' : habit.success_rate >= 40 ? 'warning' : 'danger',
        'small'
    );
    
    // اضافه به HTML
});
```

---

### 5️⃣ Progress Bar

#### نحوه استفاده:

```javascript
const progressBar = UIHelpers.createProgressBar(65, 'success');
container.innerHTML = progressBar;
```

---

### 6️⃣ Empty State

#### نحوه استفاده:

```javascript
const container = document.getElementById('habits-list');

showEmptyState(container, {
    icon: 'inbox',
    title: 'هیچ عادتی وجود ندارد',
    description: 'برای شروع یک عادت جدید اضافه کنید',
    buttonText: 'اضافه عادت',
    buttonAction: 'openAddHabitModal()'
});
```

#### مثال کاربردی:

```javascript
async function loadHabits() {
    const container = document.getElementById('habits-list');
    const result = await apiCall('habits.php', { action: 'list' });
    
    if (result.success) {
        const habits = result.data.habits;
        
        if (habits.length === 0) {
            showEmptyState(container, {
                icon: 'fitness_center',
                title: 'هیچ عادتی ندارید',
                description: 'عادت اول خود را ایجاد کنید'
            });
        } else {
            // نمایش عادت‌ها
        }
    }
}
```

---

### 7️⃣ Loading Overlay

#### نحوه استفاده:

```javascript
// نمایش loading
showLoading();

// انجام عملیات
await someAsyncOperation();

// مخفی کردن loading
hideLoading();
```

#### مثال کاربردی:

```javascript
async function saveData() {
    showLoading();
    
    try {
        const result = await apiCall('save.php', data);
        hideLoading();
        
        if (result.success) {
            showToast('ذخیره شد', 'success');
        }
    } catch (error) {
        hideLoading();
        showToast('خطا رخ داد', 'error');
    }
}
```

---

### 8️⃣ Animations

#### افزودن کلاس `enhanced` به المان‌ها:

```javascript
// برای کارت‌ها
UIHelpers.enhanceElements('.card');

// برای دکمه‌ها
UIHelpers.enhanceElements('.btn');
```

#### افزودن انیمیشن به صورت دینامیک:

```javascript
const element = document.getElementById('myElement');
UIHelpers.addAnimation(element, 'animate-slideUp');
```

---

## 📝 مثال‌های کامل

### مثال 1: بارگذاری عادت‌ها با تمام بهبودها

```javascript
async function loadHabits() {
    const container = document.getElementById('habits-list');
    
    // 1. نمایش Skeleton
    showSkeleton(container, 3, 'card');
    
    try {
        const result = await apiCall('habits.php', { action: 'list' });
        
        if (result.success) {
            const habits = result.data.habits;
            
            // 2. چک کردن وجود داده
            if (habits.length === 0) {
                showEmptyState(container, {
                    icon: 'fitness_center',
                    title: 'هیچ عادتی ندارید',
                    description: 'عادت اول خود را ایجاد کنید'
                });
                return;
            }
            
            // 3. ایجاد HTML با Progress
            const html = habits.map(habit => {
                const progress = UIHelpers.createCircularProgress(
                    habit.success_rate,
                    habit.success_rate >= 70 ? 'success' : 'warning',
                    'small'
                );
                
                return `
                    <div class="card enhanced">
                        <div class="card-content">
                            <h6>${habit.name}</h6>
                            ${progress}
                        </div>
                    </div>
                `;
            }).join('');
            
            // 4. نمایش محتوا
            hideSkeleton(container, html);
            
            // 5. نمایش Toast
            showToast(`${habits.length} عادت بارگذاری شد`, 'success', 2000);
        }
    } catch (error) {
        showToast('خطا در بارگذاری', 'error');
    }
}
```

### مثال 2: داشبورد با Stat Cards مدرن

```javascript
async function loadDashboard() {
    const statsContainer = document.getElementById('stats-container');
    
    showLoading();
    
    const result = await apiCall('dashboard.php');
    
    hideLoading();
    
    if (result.success) {
        const stats = result.data.stats;
        
        // ایجاد Stat Cards
        const incomeCard = UIHelpers.createModernStatCard({
            icon: 'account_balance_wallet',
            iconColor: 'green',
            label: 'درآمد ماهانه',
            value: formatMoney(stats.monthly_income),
            change: '+12% نسبت به ماه قبل',
            changeType: 'positive'
        });
        
        const habitsCard = UIHelpers.createModernStatCard({
            icon: 'check_circle',
            iconColor: 'purple',
            label: 'عادت‌های امروز',
            value: `${stats.completed_habits}/${stats.total_habits}`,
            change: 'عملکرد عالی',
            changeType: 'positive'
        });
        
        statsContainer.innerHTML = incomeCard + habitsCard;
        
        showToast('داشبورد به‌روزرسانی شد', 'success', 2000);
    }
}
```

---

## ✅ چک لیست پیاده‌سازی

- [ ] اضافه کردن `ui-enhancements.css` به HTML
- [ ] اضافه کردن `ui-helpers.js` به HTML
- [ ] جایگزینی Toast به جای M.toast
- [ ] اضافه Skeleton به توابع بارگذاری
- [ ] استفاده از Modern Stat Cards در داشبورد
- [ ] اضافه Circular Progress به عادت‌ها
- [ ] نمایش Empty State برای لیست‌های خالی

---

## 🐞 عیب‌یابی

اگر Toast نمایش داده نمی‌شود:

1. چک کنید که `ui-helpers.js` بعد از Materialize لود شده باشد
2. Console را برای خطا چک کنید
3. مطمئن شوید `ui-enhancements.css` لود شده

---

## 🎉 نتیجه

حالا شما دارای یک سیستم کامل UI/UX بهبود یافته هستید که:

✅ به صورت مدولار قابل استفاده است  
✅ کدهای فعلی را تغییر نمی‌دهد  
✅ آسان و سریع قابل استفاده است  
✅ از Dark Mode پشتیبانی می‌کند  
✅ کاملاً Responsive است  

**موفق باشید! 🚀**