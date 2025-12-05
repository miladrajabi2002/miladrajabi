<?php
// ═══════════════════════════════════════════════════════════════
// ماژول مدیریت درآمدهای ثابت
// مدیریت منابع درآمد ماهانه، گزارش‌گیری و تحلیل
// ═══════════════════════════════════════════════════════════════

// ───────────────────────────────────────────────────────────────
// نمایش منوی اصلی درآمدها با گزارش‌های خودکار
// ───────────────────────────────────────────────────────────────
function showIncomesMenu($chat_id, $user_id, $message_id = null)
{
   global $pdo;
   
   // محاسبه گزارش‌ها
   $report = generateIncomeReport();
   
   $text = "💰 <b>گزارش درآمدها</b>\n\n";
   $text .= $report['summary'];
   
   $keyboard = [
      'inline_keyboard' => [
         [['text' => '➕ افزودن منبع درآمد', 'callback_data' => 'income_add']], 
         [
            ['text' => '📋 لیست کامل', 'callback_data' => 'income_list_all'],
            ['text' => '📊 گزارش تفصیلی', 'callback_data' => 'income_report_detailed']
         ],
         [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'back_main']]
      ]
   ];
   
   if ($message_id) {
      editMessage($chat_id, $message_id, $text, $keyboard);
   } else {
      sendMessage($chat_id, $text, $keyboard);
   }
}

// ───────────────────────────────────────────────────────────────
// تولید گزارش جامع درآمد
// ───────────────────────────────────────────────────────────────
function generateIncomeReport()
{
   global $pdo;
   
   $today = date('Y-m-d');
   $first_day_of_month = date('Y-m-01');
   $first_day_of_year = date('Y-01-01');
   
   // درآمد ماه جاری
   $stmt = $pdo->prepare("SELECT SUM(monthly_amount) as total, COUNT(*) as count 
      FROM incomes WHERE is_active = 1");
   $stmt->execute();
   $current = $stmt->fetch();
   $monthly_income = $current['total'] ?? 0;
   $active_count = $current['count'] ?? 0;
   
   // محاسبه درآمد از ابتدای سال
   $stmt = $pdo->prepare("SELECT 
      SUM(CASE 
         WHEN start_date <= ? THEN 
            monthly_amount * (PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(?, '%Y%m')) + 1)
         ELSE 
            monthly_amount * (PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(start_date, '%Y%m')) + 1)
      END) as year_total
      FROM incomes 
      WHERE is_active = 1 AND start_date <= ?");
   $stmt->execute([$first_day_of_year, $today, $first_day_of_year, $today, $today]);
   $year_data = $stmt->fetch();
   $year_income = $year_data['year_total'] ?? 0;
   
   // پیش‌بینی کل سال
   $year_forecast = $monthly_income * 12;
   
   // میانگین ماهانه
   $current_month = (int)date('m');
   $avg_monthly = $current_month > 0 ? ($year_income / $current_month) : $monthly_income;
   
   // روند رشد (مقایسه با ماه قبل)
   $last_month = date('Y-m-01', strtotime('-1 month'));
   $stmt = $pdo->prepare("SELECT SUM(monthly_amount) as total 
      FROM incomes 
      WHERE is_active = 1 AND start_date <= ?");
   $stmt->execute([$last_month]);
   $last_month_data = $stmt->fetch();
   $last_month_income = $last_month_data['total'] ?? 0;
   
   $growth = 0;
   $growth_text = "";
   if ($last_month_income > 0) {
      $growth = (($monthly_income - $last_month_income) / $last_month_income) * 100;
      $growth_icon = $growth > 0 ? '✅' : ($growth < 0 ? '⚠️' : '➖');
      $growth_text = sprintf("%.1f%%", abs($growth));
      $growth_text = ($growth > 0 ? '+' : ($growth < 0 ? '-' : '')) . $growth_text . " " . $growth_icon;
   }
   
   // بهترین مشتریان (3 تا)
   $stmt = $pdo->prepare("SELECT client_name, monthly_amount 
      FROM incomes WHERE is_active = 1 
      ORDER BY monthly_amount DESC LIMIT 3");
   $stmt->execute();
   $top_clients = $stmt->fetchAll();
   
   // تولید متن گزارش
   $persian_month = jdate('F Y');
   
   $summary = "📊 <b>ماه جاری ($persian_month):</b>\n";
   $summary .= "├ جمع درآمد: " . number_format($monthly_income) . " تومان\n";
   $summary .= "├ تعداد مشتریان فعال: $active_count نفر\n";
   $summary .= "└ پیش‌بینی ماه بعد: " . number_format($monthly_income) . " تومان\n\n";
   
   $summary .= "📈 <b>گزارش سالانه (" . jdate('Y') . "):</b>\n";
   $summary .= "├ درآمد از ابتدای سال: " . number_format($year_income) . " تومان\n";
   $summary .= "├ پیش‌بینی کل سال: " . number_format($year_forecast) . " تومان\n";
   $summary .= "└ میانگین ماهانه: " . number_format($avg_monthly) . " تومان\n\n";
   
   if ($growth_text) {
      $summary .= "📊 <b>روند رشد:</b>\n";
      $summary .= "└ نسبت به ماه قبل: $growth_text\n\n";
   }
   
   if (!empty($top_clients)) {
      $summary .= "🏆 <b>بهترین مشتریان:</b>\n";
      $i = 1;
      foreach ($top_clients as $client) {
         $icon = ['1️⃣', '2️⃣', '3️⃣'][$i - 1];
         $summary .= "$icon " . $client['client_name'] . " - " . number_format($client['monthly_amount']) . " تومان\n";
         $i++;
      }
   }
   
   return [
      'summary' => $summary,
      'monthly_income' => $monthly_income,
      'year_income' => $year_income,
      'growth' => $growth
   ];
}

// ───────────────────────────────────────────────────────────────
// نمایش لیست تمام منابع درآمد
// ───────────────────────────────────────────────────────────────
function showIncomesList($chat_id, $user_id, $message_id, $filter = 'all', $sort = 'amount')
{
   global $pdo;
   
   $where = "";
   if ($filter == 'active') {
      $where = "WHERE is_active = 1";
   } elseif ($filter == 'inactive') {
      $where = "WHERE is_active = 0";
   }
   
   $order = $sort == 'date' ? "ORDER BY start_date DESC" : "ORDER BY monthly_amount DESC";
   
   $stmt = $pdo->prepare("SELECT * FROM incomes $where $order");
   $stmt->execute();
   $incomes = $stmt->fetchAll();
   
   if (empty($incomes)) {
      $text = "📋 <b>لیست منابع درآمد</b>\n\n";
      $text .= "❌ هیچ منبع درآمدی ثبت نشده است.";
      
      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن منبع جدید', 'callback_data' => 'income_add']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'incomes']]
         ]
      ];
      
      editMessage($chat_id, $message_id, $text, $keyboard);
      return;
   }
   
   // گروه‌بندی فعال/غیرفعال
   $active = array_filter($incomes, fn($i) => $i['is_active'] == 1);
   $inactive = array_filter($incomes, fn($i) => $i['is_active'] == 0);
   
   $text = "📋 <b>لیست منابع درآمد</b>\n\n";
   
   if (!empty($active)) {
      $text .= "✅ <b>فعال (" . count($active) . "):</b>\n";
      foreach ($active as $idx => $income) {
         $months = calculateMonthsDiff($income['start_date'], date('Y-m-d'));
         $total = $income['monthly_amount'] * $months;
         
         $text .= "\n" . ($idx + 1) . "️⃣ <b>" . $income['client_name'] . "</b>\n";
         $text .= "   🛠 " . $income['service_type'] . "\n";
         $text .= "   💵 " . number_format($income['monthly_amount']) . " تومان/ماه\n";
         $text .= "   📅 از: " . jdate('Y/m/d', strtotime($income['start_date'])) . " ($months ماه)\n";
         $text .= "   💰 کل درآمد: " . number_format($total) . " تومان\n";
      }
   }
   
   if (!empty($inactive)) {
      $text .= "\n\n❌ <b>غیرفعال (" . count($inactive) . "):</b>\n";
      foreach ($inactive as $idx => $income) {
         $months = calculateMonthsDiff($income['start_date'], date('Y-m-d'));
         $total = $income['monthly_amount'] * $months;
         
         $text .= "\n" . ($idx + 1) . "️⃣ <b>" . $income['client_name'] . "</b>\n";
         $text .= "   💵 " . number_format($income['monthly_amount']) . " تومان/ماه\n";
         $text .= "   📅 از: " . jdate('Y/m/d', strtotime($income['start_date'])) . "\n";
      }
   }
   
   // ساخت دکمه‌های منابع
   $buttons = [];
   foreach ($incomes as $income) {
      $icon = $income['is_active'] ? '✅' : '❌';
      $buttons[] = [['text' => $icon . ' ' . $income['client_name'], 'callback_data' => 'income_view_' . $income['id']]];
   }
   
   // دکمه‌های فیلتر و مرتب‌سازی
   $filter_buttons = [
      ['text' => '✅ فقط فعال‌ها', 'callback_data' => 'income_filter_active'],
      ['text' => '❌ فقط غیرفعال', 'callback_data' => 'income_filter_inactive']
   ];
   
   $sort_buttons = [
      ['text' => '💰 مرتب براساس مبلغ', 'callback_data' => 'income_sort_amount'],
      ['text' => '📅 مرتب براساس تاریخ', 'callback_data' => 'income_sort_date']
   ];
   
   if ($filter != 'all') {
      $filter_buttons[] = ['text' => '🔄 نمایش همه', 'callback_data' => 'income_list_all'];
   }
   
   $keyboard = [
      'inline_keyboard' => array_merge(
         [$filter_buttons],
         [$sort_buttons],
         $buttons,
         [[['text' => '🔙 بازگشت', 'callback_data' => 'incomes']]]
      )
   ];
   
   editMessage($chat_id, $message_id, $text, $keyboard);
}

// ───────────────────────────────────────────────────────────────
// نمایش جزئیات یک منبع درآمد
// ───────────────────────────────────────────────────────────────
function showIncomeDetails($chat_id, $user_id, $message_id, $income_id)
{
   global $pdo;
   
   $stmt = $pdo->prepare("SELECT * FROM incomes WHERE id = ?");
   $stmt->execute([$income_id]);
   $income = $stmt->fetch();
   
   if (!$income) {
      sendMessage($chat_id, "❌ منبع درآمد یافت نشد.");
      return;
   }
   
   $months = calculateMonthsDiff($income['start_date'], date('Y-m-d'));
   $total = $income['monthly_amount'] * $months;
   $status = $income['is_active'] ? '✅ فعال' : '❌ غیرفعال';
   
   $text = "🔍 <b>جزئیات منبع درآمد</b>\n\n";
   $text .= "👤 <b>مشتری:</b> " . $income['client_name'] . "\n";
   $text .= "🛠 <b>خدمات:</b> " . $income['service_type'] . "\n";
   $text .= "💵 <b>مبلغ ماهانه:</b> " . number_format($income['monthly_amount']) . " تومان\n";
   $text .= "📅 <b>شروع همکاری:</b> " . jdate('Y/m/d', strtotime($income['start_date'])) . "\n";
   $text .= "⏱ <b>مدت همکاری:</b> $months ماه\n";
   $text .= "💰 <b>کل درآمد دریافتی:</b> " . number_format($total) . " تومان\n";
   $text .= "📊 <b>وضعیت:</b> $status\n";
   
   if ($income['description']) {
      $text .= "\n📝 <b>توضیحات:</b>\n" . $income['description'];
   }
   
   $toggle_text = $income['is_active'] ? '❌ غیرفعال کن' : '✅ فعال کن';
   $toggle_action = $income['is_active'] ? 'income_deactivate_' : 'income_activate_';
   
   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✏️ ویرایش', 'callback_data' => 'income_edit_menu_' . $income_id],
            ['text' => $toggle_text, 'callback_data' => $toggle_action . $income_id]
         ],
         [['text' => '🗑 حذف', 'callback_data' => 'income_delete_confirm_' . $income_id]],
         [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'income_list_all']]
      ]
   ];
   
   editMessage($chat_id, $message_id, $text, $keyboard);
}

// ───────────────────────────────────────────────────────────────
// نمایش گزارش تفصیلی
// ───────────────────────────────────────────────────────────────
function showDetailedReport($chat_id, $user_id, $message_id, $month_offset = 0)
{
   global $pdo;
   
   $target_date = date('Y-m-01', strtotime("$month_offset month"));
   $persian_month = jdate('F Y', strtotime($target_date));
   
   // درآمد ماه هدف
   $stmt = $pdo->prepare("SELECT client_name, monthly_amount 
      FROM incomes 
      WHERE is_active = 1 AND start_date <= ?
      ORDER BY monthly_amount DESC");
   $stmt->execute([$target_date]);
   $sources = $stmt->fetchAll();
   
   $total = array_sum(array_column($sources, 'monthly_amount'));
   
   $text = "📊 <b>گزارش تفصیلی درآمد</b>\n\n";
   $text .= "📅 <b>$persian_month:</b>\n";
   $text .= "┌─────────────────────────────┐\n";
   
   foreach ($sources as $source) {
      $text .= "│ " . $source['client_name'] . " - " . number_format($source['monthly_amount']) . " ت\n";
   }
   
   $text .= "├─────────────────────────────┤\n";
   $text .= "│ جمع کل: " . number_format($total) . " تومان\n";
   $text .= "└─────────────────────────────┘\n\n";
   
   // روند 6 ماه اخیر
   $text .= "📈 <b>روند 6 ماه اخیر:</b>\n";
   for ($i = 5; $i >= 0; $i--) {
      $month_date = date('Y-m-01', strtotime("-$i month"));
      $month_name = jdate('F', strtotime($month_date));
      
      $stmt = $pdo->prepare("SELECT SUM(monthly_amount) as total 
         FROM incomes 
         WHERE is_active = 1 AND start_date <= ?");
      $stmt->execute([$month_date]);
      $month_total = $stmt->fetchColumn() ?? 0;
      
      // محاسبه رشد
      $prev_month = date('Y-m-01', strtotime("-" . ($i + 1) . " month"));
      $stmt->execute([$prev_month]);
      $prev_total = $stmt->fetchColumn() ?? 0;
      
      $growth_text = "";
      if ($prev_total > 0 && $month_total != $prev_total) {
         $growth = (($month_total - $prev_total) / $prev_total) * 100;
         $icon = $growth > 0 ? '✅' : '⚠️';
         $growth_text = sprintf(" (%+.1f%%) %s", $growth, $icon);
      }
      
      $text .= "$month_name: " . number_format($month_total) . " ت$growth_text\n";
   }
   
   // پیش‌بینی 3 ماه آینده
   $text .= "\n🎯 <b>پیش‌بینی 3 ماه آینده:</b>\n";
   for ($i = 1; $i <= 3; $i++) {
      $future_date = date('Y-m-01', strtotime("+$i month"));
      $future_month = jdate('F', strtotime($future_date));
      $text .= "$future_month: " . number_format($total) . " ت\n";
   }
   
   // تحلیل هزینه
   $suggested_expense = $total * 0.7;
   $text .= "\n⚠️ <b>تحلیل هزینه:</b>\n";
   $text .= "└ با درآمد " . number_format($total / 1000000, 1) . "M، بودجه پیشنهادی هزینه: حداکثر " . number_format($suggested_expense / 1000000, 1) . "M تومان";
   
   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '⬅️ ماه قبل', 'callback_data' => 'income_report_month_' . ($month_offset - 1)],
            ['text' => 'ماه بعد ➡️', 'callback_data' => 'income_report_month_' . ($month_offset + 1)]
         ],
         [['text' => '🔙 بازگشت', 'callback_data' => 'incomes']]
      ]
   ];
   
   editMessage($chat_id, $message_id, $text, $keyboard);
}

// ───────────────────────────────────────────────────────────────
// شروع فرآیند افزودن منبع درآمد جدید
// ───────────────────────────────────────────────────────────────
function startAddIncome($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'income_add_name']);
   
   $text = "➕ <b>افزودن منبع درآمد جدید</b>\n\n";
   $text .= "مرحله 1️⃣: نام مشتری/منبع درآمد را وارد کنید:";
   
   $keyboard = [
      'inline_keyboard' => [
         [['text' => '❌ انصراف', 'callback_data' => 'incomes']]
      ]
   ];
   
   editMessage($chat_id, $message_id, $text, $keyboard);
}

// ───────────────────────────────────────────────────────────────
// پردازش فرم افزودن/ویرایش منبع درآمد
// ───────────────────────────────────────────────────────────────
function processIncomeForm($chat_id, $user_id, $text, $step)
{
   global $pdo;
   $user = getUser($user_id);
   $temp_data = json_decode($user['temp_reminder'] ?? '{}', true);
   
   switch ($step) {
      case 'income_add_name':
         $temp_data['client_name'] = trim($text);
         updateUser($user_id, [
            'temp_reminder' => json_encode($temp_data),
            'step' => 'income_add_service'
         ]);
         sendMessage($chat_id, "مرحله 2️⃣: نوع خدمات را وارد کنید:\n(مثال: پشتیبانی سرور، هاست، VPS، ...)");
         break;
         
      case 'income_add_service':
         $temp_data['service_type'] = trim($text);
         updateUser($user_id, [
            'temp_reminder' => json_encode($temp_data),
            'step' => 'income_add_amount'
         ]);
         sendMessage($chat_id, "مرحله 3️⃣: مبلغ ماهانه را وارد کنید (به تومان):\n(مثال: 5000000)");
         break;
         
      case 'income_add_amount':
         $amount = preg_replace('/[^0-9.]/', '', $text);
         if (!is_numeric($amount) || $amount <= 0) {
            sendMessage($chat_id, "❌ لطفاً یک عدد معتبر وارد کنید.");
            return;
         }
         $temp_data['monthly_amount'] = $amount;
         updateUser($user_id, [
            'temp_reminder' => json_encode($temp_data),
            'step' => 'income_add_date'
         ]);
         sendMessage($chat_id, "مرحله 4️⃣: تاریخ شروع همکاری را وارد کنید:\n(مثال: 1404/09/05 یا 2025-12-05)");
         break;
         
      case 'income_add_date':
         $date = parseDate($text);
         if (!$date) {
            sendMessage($chat_id, "❌ فرمت تاریخ نامعتبر است. لطفاً دوباره وارد کنید.");
            return;
         }
         $temp_data['start_date'] = $date;
         updateUser($user_id, [
            'temp_reminder' => json_encode($temp_data),
            'step' => 'income_add_description'
         ]);
         sendMessage($chat_id, "مرحله 5️⃣: توضیحات (اختیاری):\n(برای رد کردن، 'رد' یا '-' بنویسید)");
         break;
         
      case 'income_add_description':
         if ($text != 'رد' && $text != '-') {
            $temp_data['description'] = trim($text);
         }
         showIncomePreview($chat_id, $user_id, $temp_data);
         break;
         
      case 'income_edit_name':
      case 'income_edit_service':
      case 'income_edit_amount':
      case 'income_edit_description':
         $income_id = $temp_data['editing_id'];
         $field = str_replace('income_edit_', '', $step);
         
         $value = trim($text);
         if ($field == 'amount') {
            $value = preg_replace('/[^0-9.]/', '', $text);
            if (!is_numeric($value) || $value <= 0) {
               sendMessage($chat_id, "❌ لطفاً یک عدد معتبر وارد کنید.");
               return;
            }
         }
         
         $field_map = [
            'name' => 'client_name',
            'service' => 'service_type',
            'amount' => 'monthly_amount',
            'description' => 'description'
         ];
         
         $db_field = $field_map[$field];
         $stmt = $pdo->prepare("UPDATE incomes SET $db_field = ? WHERE id = ?");
         $stmt->execute([$value, $income_id]);
         
         updateUser($user_id, ['step' => 'completed', 'temp_reminder' => null]);
         sendMessage($chat_id, "✅ اطلاعات بروزرسانی شد.");
         
         // نمایش جزئیات
         showIncomeDetails($chat_id, $user_id, 0, $income_id);
         break;
   }
}

// ───────────────────────────────────────────────────────────────
// نمایش پیش‌نمایش منبع درآمد قبل از ثبت نهایی
// ───────────────────────────────────────────────────────────────
function showIncomePreview($chat_id, $user_id, $data)
{
   updateUser($user_id, ['step' => 'completed']);
   
   $text = "✅ <b>پیش‌نمایش منبع درآمد جدید:</b>\n\n";
   $text .= "👤 <b>مشتری:</b> " . $data['client_name'] . "\n";
   $text .= "🛠 <b>خدمات:</b> " . $data['service_type'] . "\n";
   $text .= "💵 <b>مبلغ:</b> " . number_format($data['monthly_amount']) . " تومان/ماه\n";
   $text .= "📅 <b>شروع:</b> " . jdate('Y/m/d', strtotime($data['start_date'])) . "\n";
   
   if (!empty($data['description'])) {
      $text .= "📝 <b>توضیحات:</b> " . $data['description'] . "\n";
   }
   
   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ ثبت نهایی', 'callback_data' => 'income_save'],
            ['text' => '❌ انصراف', 'callback_data' => 'incomes']
         ]
      ]
   ];
   
   sendMessage($chat_id, $text, $keyboard);
}

// ───────────────────────────────────────────────────────────────
// ذخیره منبع درآمد جدید
// ───────────────────────────────────────────────────────────────
function saveIncome($chat_id, $user_id, $message_id)
{
   global $pdo;
   
   $user = getUser($user_id);
   $data = json_decode($user['temp_reminder'], true);
   
   $stmt = $pdo->prepare("INSERT INTO incomes 
      (client_name, service_type, monthly_amount, start_date, description, is_active) 
      VALUES (?, ?, ?, ?, ?, 1)");
   
   $stmt->execute([
      $data['client_name'],
      $data['service_type'],
      $data['monthly_amount'],
      $data['start_date'],
      $data['description'] ?? null
   ]);
   
   updateUser($user_id, ['temp_reminder' => null]);
   
   deleteMessage($chat_id, $message_id);
   sendMessage($chat_id, "✅ منبع درآمد با موفقیت ثبت شد!");
   showIncomesMenu($chat_id, $user_id);
}

// ───────────────────────────────────────────────────────────────
// نمایش منوی ویرایش
// ───────────────────────────────────────────────────────────────
function showEditMenu($chat_id, $user_id, $message_id, $income_id)
{
   $text = "✏️ <b>ویرایش منبع درآمد</b>\n\n";
   $text .= "چه موردی را می‌خواهید ویرایش کنید؟";
   
   $keyboard = [
      'inline_keyboard' => [
         [['text' => '👤 تغییر نام مشتری', 'callback_data' => 'income_edit_name_' . $income_id]],
         [['text' => '🛠 تغییر نوع خدمات', 'callback_data' => 'income_edit_service_' . $income_id]],
         [['text' => '💵 تغییر مبلغ ماهانه', 'callback_data' => 'income_edit_amount_' . $income_id]],
         [['text' => '📝 تغییر توضیحات', 'callback_data' => 'income_edit_description_' . $income_id]],
         [['text' => '🔙 بازگشت', 'callback_data' => 'income_view_' . $income_id]]
      ]
   ];
   
   editMessage($chat_id, $message_id, $text, $keyboard);
}

// ───────────────────────────────────────────────────────────────
// فعال/غیرفعال کردن منبع درآمد
// ───────────────────────────────────────────────────────────────
function toggleIncomeStatus($chat_id, $user_id, $message_id, $income_id, $activate = true)
{
   global $pdo;
   
   $status = $activate ? 1 : 0;
   $stmt = $pdo->prepare("UPDATE incomes SET is_active = ? WHERE id = ?");
   $stmt->execute([$status, $income_id]);
   
   $message = $activate ? "✅ منبع درآمد فعال شد." : "❌ منبع درآمد غیرفعال شد.";
   answerCallbackQuery($message);
   
   showIncomeDetails($chat_id, $user_id, $message_id, $income_id);
}

// ───────────────────────────────────────────────────────────────
// حذف منبع درآمد
// ───────────────────────────────────────────────────────────────
function deleteIncome($chat_id, $user_id, $message_id, $income_id, $confirmed = false)
{
   global $pdo;
   
   if (!$confirmed) {
      $text = "⚠️ <b>حذف منبع درآمد</b>\n\n";
      $text .= "آیا مطمئن هستید که می‌خواهید این منبع درآمد را حذف کنید؟";
      
      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '✅ بله، حذف شود', 'callback_data' => 'income_delete_yes_' . $income_id],
               ['text' => '❌ خیر', 'callback_data' => 'income_view_' . $income_id]
            ]
         ]
      ];
      
      editMessage($chat_id, $message_id, $text, $keyboard);
      return;
   }
   
   $stmt = $pdo->prepare("DELETE FROM incomes WHERE id = ?");
   $stmt->execute([$income_id]);
   
   deleteMessage($chat_id, $message_id);
   sendMessage($chat_id, "✅ منبع درآمد حذف شد.");
   showIncomesMenu($chat_id, $user_id);
}

// ───────────────────────────────────────────────────────────────
// محاسبه تفاوت ماه‌ها
// ───────────────────────────────────────────────────────────────
function calculateMonthsDiff($start_date, $end_date)
{
   $start = new DateTime($start_date);
   $end = new DateTime($end_date);
   $interval = $start->diff($end);
   return ($interval->y * 12) + $interval->m + 1;
}

// ───────────────────────────────────────────────────────────────
// مدیریت callback های ماژول درآمد
// ───────────────────────────────────────────────────────────────
function handleIncomeCallback($chat_id, $user_id, $data, $message_id)
{
   if ($data == 'income_add') {
      startAddIncome($chat_id, $user_id, $message_id);
   } elseif ($data == 'income_list_all') {
      showIncomesList($chat_id, $user_id, $message_id, 'all', 'amount');
   } elseif ($data == 'income_filter_active') {
      showIncomesList($chat_id, $user_id, $message_id, 'active', 'amount');
   } elseif ($data == 'income_filter_inactive') {
      showIncomesList($chat_id, $user_id, $message_id, 'inactive', 'amount');
   } elseif ($data == 'income_sort_amount') {
      showIncomesList($chat_id, $user_id, $message_id, 'all', 'amount');
   } elseif ($data == 'income_sort_date') {
      showIncomesList($chat_id, $user_id, $message_id, 'all', 'date');
   } elseif ($data == 'income_report_detailed') {
      showDetailedReport($chat_id, $user_id, $message_id, 0);
   } elseif (strpos($data, 'income_report_month_') === 0) {
      $offset = (int)str_replace('income_report_month_', '', $data);
      showDetailedReport($chat_id, $user_id, $message_id, $offset);
   } elseif (strpos($data, 'income_view_') === 0) {
      $income_id = str_replace('income_view_', '', $data);
      showIncomeDetails($chat_id, $user_id, $message_id, $income_id);
   } elseif (strpos($data, 'income_edit_menu_') === 0) {
      $income_id = str_replace('income_edit_menu_', '', $data);
      showEditMenu($chat_id, $user_id, $message_id, $income_id);
   } elseif (strpos($data, 'income_edit_name_') === 0) {
      $income_id = str_replace('income_edit_name_', '', $data);
      updateUser($user_id, [
         'step' => 'income_edit_name',
         'temp_reminder' => json_encode(['editing_id' => $income_id])
      ]);
      editMessage($chat_id, $message_id, "نام جدید مشتری را وارد کنید:", null);
   } elseif (strpos($data, 'income_edit_service_') === 0) {
      $income_id = str_replace('income_edit_service_', '', $data);
      updateUser($user_id, [
         'step' => 'income_edit_service',
         'temp_reminder' => json_encode(['editing_id' => $income_id])
      ]);
      editMessage($chat_id, $message_id, "نوع خدمات جدید را وارد کنید:", null);
   } elseif (strpos($data, 'income_edit_amount_') === 0) {
      $income_id = str_replace('income_edit_amount_', '', $data);
      updateUser($user_id, [
         'step' => 'income_edit_amount',
         'temp_reminder' => json_encode(['editing_id' => $income_id])
      ]);
      editMessage($chat_id, $message_id, "مبلغ ماهانه جدید را وارد کنید:", null);
   } elseif (strpos($data, 'income_edit_description_') === 0) {
      $income_id = str_replace('income_edit_description_', '', $data);
      updateUser($user_id, [
         'step' => 'income_edit_description',
         'temp_reminder' => json_encode(['editing_id' => $income_id])
      ]);
      editMessage($chat_id, $message_id, "توضیحات جدید را وارد کنید:", null);
   } elseif (strpos($data, 'income_activate_') === 0) {
      $income_id = str_replace('income_activate_', '', $data);
      toggleIncomeStatus($chat_id, $user_id, $message_id, $income_id, true);
   } elseif (strpos($data, 'income_deactivate_') === 0) {
      $income_id = str_replace('income_deactivate_', '', $data);
      toggleIncomeStatus($chat_id, $user_id, $message_id, $income_id, false);
   } elseif (strpos($data, 'income_delete_confirm_') === 0) {
      $income_id = str_replace('income_delete_confirm_', '', $data);
      deleteIncome($chat_id, $user_id, $message_id, $income_id, false);
   } elseif (strpos($data, 'income_delete_yes_') === 0) {
      $income_id = str_replace('income_delete_yes_', '', $data);
      deleteIncome($chat_id, $user_id, $message_id, $income_id, true);
   } elseif ($data == 'income_save') {
      saveIncome($chat_id, $user_id, $message_id);
   }
}
