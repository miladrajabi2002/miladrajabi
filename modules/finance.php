<?php
function showFinanceMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN type = 'debt' AND is_paid = 0 THEN amount ELSE 0 END) as total_debt,
        SUM(CASE WHEN type = 'credit' AND is_paid = 0 THEN amount ELSE 0 END) as total_credit,
        COUNT(CASE WHEN type = 'debt' AND is_paid = 0 THEN 1 END) as debt_count,
        COUNT(CASE WHEN type = 'credit' AND is_paid = 0 THEN 1 END) as credit_count
        FROM finances");
   $stmt->execute();
   $financial_summary = $stmt->fetch();

   // آمار چک‌ها
   $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN type = 'received' AND status = 'pending' THEN 1 END) as received_pending,
        COUNT(CASE WHEN type = 'issued' AND status = 'pending' THEN 1 END) as issued_pending,
        SUM(CASE WHEN type = 'received' AND status = 'pending' THEN amount ELSE 0 END) as received_amount,
        SUM(CASE WHEN type = 'issued' AND status = 'pending' THEN amount ELSE 0 END) as issued_amount
        FROM checks");
   $stmt->execute();
   $check_summary = $stmt->fetch();

   $text = "💰 <b>مدیریت مالی</b>\n\n";

   $total_debt = number_format($financial_summary['total_debt'] ?? 0);
   $total_credit = number_format($financial_summary['total_credit'] ?? 0);
   $debt_count = $financial_summary['debt_count'] ?? 0;
   $credit_count = $financial_summary['credit_count'] ?? 0;

   $text .= "📊 <b>بدهی‌ها و طلب‌ها:</b>\n";
   $text .= "🔴 بدهی‌ها: $debt_count مورد | $total_debt تومان\n";
   $text .= "🟢 طلب‌ها: $credit_count مورد | $total_credit تومان\n\n";

   $received_pending = $check_summary['received_pending'] ?? 0;
   $issued_pending = $check_summary['issued_pending'] ?? 0;
   $received_amount = number_format($check_summary['received_amount'] ?? 0);
   $issued_amount = number_format($check_summary['issued_amount'] ?? 0);

   $text .= "📋 <b>چک‌ها:</b>\n";
   $text .= "📥 دریافتی: $received_pending عدد | $received_amount تومان\n";
   $text .= "📤 صادره: $issued_pending عدد | $issued_amount تومان\n";

   $text .= "\n<b>امکانات:</b>\n";
   $text .= "• مدیریت بدهی‌ها و طلب‌ها\n";
   $text .= "• مدیریت چک‌های دریافتی و صادره\n";
   $text .= "• گزارش‌گیری هوشمند\n";
   $text .= "• یادآوری سررسیدها";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => "💳 بدهی‌ها ($debt_count)", 'callback_data' => 'finance_debts'],
            ['text' => "💰 طلب‌ها ($credit_count)", 'callback_data' => 'finance_credits']
         ],
         [
            ['text' => "📋 چک دریافتی ($received_pending)", 'callback_data' => 'finance_checks_received'],
            ['text' => "📄 چک صادره ($issued_pending)", 'callback_data' => 'finance_checks_issued']
         ],
         [
            ['text' => '📊 گزارش کامل', 'callback_data' => 'finance_report']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function handleFinanceCallback($chat_id, $user_id, $data, $message_id)
{
   global $callback_query_id;

   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'debts':
         $page = $parts[2] ?? 1;
         showDebtsList($chat_id, $user_id, $message_id, $page);
         break;

      case 'credits':
         $page = $parts[2] ?? 1;
         showCreditsList($chat_id, $user_id, $message_id, $page);
         break;

      case 'checks':
         $type = $parts[2] ?? 'received';
         $page = $parts[3] ?? 1;
         showChecksList($chat_id, $user_id, $message_id, $type, $page);
         break;

      case 'report':
         showFinancialReport($chat_id, $user_id, $message_id);
         break;

      case 'add':
         $type = $parts[2] ?? 'debt';
         // اگه برای چک هست، اصلاح کن
         if ($type === 'check') {
            $type = 'check_' . ($parts[3] ?? 'received');
         }
         startAddFinancialItem($chat_id, $user_id, $message_id, $type);
         break;

      case 'view':
         $type = $parts[2] ?? '';
         $item_id = $parts[3] ?? 0;

         // اگه check هست، type رو درست کن
         if ($type === 'check' && isset($parts[3]) && isset($parts[4])) {
            $check_type = $parts[3]; // received یا issued
            $item_id = $parts[4];     // آیدی
            $type = 'check_' . $check_type; // میشه check_received
         }

         viewFinancialItem($chat_id, $user_id, $message_id, $type, $item_id);
         break;
      case 'pay':
         $type = $parts[2] ?? '';
         $item_id = $parts[3] ?? 0;
         markAsPaid($chat_id, $user_id, $message_id, $type, $item_id);
         break;

      case 'delete':
         $type = $parts[2];
         if ($type == 'check') $type .= '_' . $parts[3];
         $item_id = $parts[4];
         confirmDeleteFinancialItem($chat_id, $user_id, $message_id, $type, $item_id);
         break;

      case 'confirmdelete':
         $type = $parts[2] ?? '';

         // sendMessage(1253939828, "data: $data");

         // برای چک‌ها
         if ($type == 'check') {
            $check_type = $parts[3];
            $item_id = $parts[4];
            $type = 'check_' . $check_type;
         } else {
            $item_id = $parts[3] ?? 0;
         }
         deleteFinancialItem($chat_id, $user_id, $message_id, $type, $item_id);
         break;

      case 'cash':
         if ($parts[2] === 'check') {
            $check_id = $parts[3] ?? 0;
            updateCheckStatus($chat_id, $user_id, $check_id, 'cashed', $message_id);
         }
         break;

      case 'bounce':
         if ($parts[2] === 'check') {
            $check_id = $parts[3] ?? 0;
            updateCheckStatus($chat_id, $user_id, $check_id, 'bounced', $message_id);
         }
         break;

      case 'skip':
         if ($parts[2] === 'due' && $parts[3] === 'date') {
            $user = getUser($user_id);
            if ($user['temp_data']) {
               $temp_data = json_decode($user['temp_data'], true);
               if (strpos($user['step'], 'finance_adding_debt') === 0) {
                  saveDebtCredit($chat_id, $user_id, 'debt', $temp_data['title'], $temp_data['person_name'], $temp_data['amount']);
               } elseif (strpos($user['step'], 'finance_adding_credit') === 0) {
                  saveDebtCredit($chat_id, $user_id, 'credit', $temp_data['title'], $temp_data['person_name'], $temp_data['amount']);
               }
            }
         }
         break;
      case 'export':
         answerCallbackQuery($callback_query_id, "به زودی فعال میشود");
         break;

      default:
         answerCallbackQuery($callback_query_id, "❌ دستور مالی نامعتبر");
         break;
   }
}

function showDebtsList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 6;
   $offset = ($page - 1) * $limit;

   $stmt = $pdo->prepare("SELECT * FROM finances WHERE type = :type ORDER BY is_paid ASC, due_date ASC LIMIT :limit OFFSET :offset");
   $stmt->bindValue(':type', 'debt', PDO::PARAM_STR);
   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $debts = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM finances WHERE type = :type");
   $stmt->bindValue(':type', 'debt', PDO::PARAM_STR);
   $stmt->execute();
   $total = $stmt->fetchColumn();

   if (empty($debts)) {
      $text = "💳 <b>لیست بدهی‌ها</b>\n\n";
      $text .= "✅ شما هیچ بدهی ثبت شده‌ای ندارید!\n\n";
      $text .= "برای افزودن بدهی جدید از دکمه زیر استفاده کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن بدهی', 'callback_data' => 'finance_add_debt']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'finance']]
         ]
      ];
   } else {
      $text = "💳 <b>لیست بدهی‌ها</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total بدهی\n\n";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($debts as $debt) {
         $amount = number_format($debt['amount']);
         $status_icon = $debt['is_paid'] ? '✅' : '🔴';
         $due_info = '';

         if ($debt['due_date'] && !$debt['is_paid']) {
            $days_left = (strtotime($debt['due_date']) - time()) / (24 * 3600);
            if ($days_left < 0) {
               $due_info = ' ⚠️';
            } elseif ($days_left <= 7) {
               $due_info = ' ⏰';
            }
         }

         $title = mb_strlen($debt['title']) > 20 ? mb_substr($debt['title'], 0, 20) . '...' : $debt['title'];
         $button_text = "$status_icon $title • $amount ت$due_info";

         $keyboard['inline_keyboard'][] = [
            [
               'text' => $button_text,
               'callback_data' => 'finance_view_debt_' . $debt['id']
            ]
         ];
      }

      // صفحه‌بندی
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         if ($page > 1) {
            $pagination_row[] = ['text' => '⬅️', 'callback_data' => 'finance_debts_' . ($page - 1)];
         }

         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = ['text' => $page_text, 'callback_data' => 'finance_debts_' . $i];
         }

         if ($page < $total_pages) {
            $pagination_row[] = ['text' => '➡️', 'callback_data' => 'finance_debts_' . ($page + 1)];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ افزودن بدهی', 'callback_data' => 'finance_add_debt']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'finance']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showCreditsList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 6;
   $offset = ($page - 1) * $limit;

   $stmt = $pdo->prepare("SELECT * FROM finances WHERE type = :type ORDER BY is_paid ASC, due_date ASC LIMIT :limit OFFSET :offset");
   $stmt->bindValue(':type', 'credit', PDO::PARAM_STR);
   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $credits = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM finances WHERE type = :type");
   $stmt->bindValue(':type', 'credit', PDO::PARAM_STR);
   $stmt->execute();
   $total = $stmt->fetchColumn();

   if (empty($credits)) {
      $text = "💰 <b>لیست طلب‌ها</b>\n\n";
      $text .= "❌ شما هیچ طلب ثبت شده‌ای ندارید!\n\n";
      $text .= "برای افزودن طلب جدید از دکمه زیر استفاده کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن طلب', 'callback_data' => 'finance_add_credit']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'finance']]
         ]
      ];
   } else {
      $text = "💰 <b>لیست طلب‌ها</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total طلب\n\n";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($credits as $credit) {
         $amount = number_format($credit['amount']);
         $status_icon = $credit['is_paid'] ? '✅' : '🟢';
         $due_info = '';

         if ($credit['due_date'] && !$credit['is_paid']) {
            $days_left = (strtotime($credit['due_date']) - time()) / (24 * 3600);
            if ($days_left < 0) {
               $due_info = ' ⚠️';
            } elseif ($days_left <= 7) {
               $due_info = ' ⏰';
            }
         }

         $title = mb_strlen($credit['title']) > 20 ? mb_substr($credit['title'], 0, 20) . '...' : $credit['title'];
         $button_text = "$status_icon $title • $amount ت$due_info";

         $keyboard['inline_keyboard'][] = [
            [
               'text' => $button_text,
               'callback_data' => 'finance_view_credit_' . $credit['id']
            ]
         ];
      }

      // صفحه‌بندی مشابه بدهی‌ها
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         if ($page > 1) {
            $pagination_row[] = ['text' => '⬅️', 'callback_data' => 'finance_credits_' . ($page - 1)];
         }

         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = ['text' => $page_text, 'callback_data' => 'finance_credits_' . $i];
         }

         if ($page < $total_pages) {
            $pagination_row[] = ['text' => '➡️', 'callback_data' => 'finance_credits_' . ($page + 1)];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ افزودن طلب', 'callback_data' => 'finance_add_credit']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'finance']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showChecksList($chat_id, $user_id, $message_id, $type = 'received', $page = 1)
{
   global $pdo;

   $limit = 6;
   $offset = ($page - 1) * $limit;

   $stmt = $pdo->prepare("SELECT * FROM checks WHERE type = :type ORDER BY status ASC, due_date ASC LIMIT :limit OFFSET :offset");
   $stmt->bindValue(':type', $type, PDO::PARAM_STR);
   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $checks = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM checks WHERE type = :type");
   $stmt->bindValue(':type', $type, PDO::PARAM_STR);
   $stmt->execute();
   $total = $stmt->fetchColumn();

   $type_title = $type === 'received' ? 'دریافتی' : 'صادره';
   $type_icon = $type === 'received' ? '📋' : '📄';

   if (empty($checks)) {
      $text = "$type_icon <b>چک‌های $type_title</b>\n\n";
      $text .= "❌ هیچ چک {$type_title}ای ثبت نشده!\n\n";
      $text .= "برای افزودن چک جدید از دکمه زیر استفاده کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => "➕ افزودن چک $type_title", 'callback_data' => "finance_add_check_$type"]],
            [['text' => '🔙 بازگشت', 'callback_data' => 'finance']]
         ]
      ];
   } else {
      $text = "$type_icon <b>چک‌های $type_title</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total چک\n\n";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($checks as $check) {
         $amount = number_format($check['amount']);
         $due_date = jdate('m/d', strtotime($check['due_date']));

         $status_icons = [
            'pending' => '⏳',
            'cashed' => '✅',
            'bounced' => '❌',
            'cancelled' => '🚫'
         ];
         $status_icon = $status_icons[$check['status']] ?? '❓';

         // بررسی نزدیک بودن به سررسید
         $due_info = '';
         if ($check['status'] === 'pending') {
            $days_left = (strtotime($check['due_date']) - time()) / (24 * 3600);
            if ($days_left < 0) {
               $due_info = ' ⚠️';
            } elseif ($days_left <= 3) {
               $due_info = ' 🔥';
            } elseif ($days_left <= 7) {
               $due_info = ' ⏰';
            }
         }

         $holder = mb_strlen($check['account_holder']) > 15 ? mb_substr($check['account_holder'], 0, 15) . '...' : $check['account_holder'];
         $button_text = "$status_icon $holder • $amount ت • $due_date$due_info";

         $keyboard['inline_keyboard'][] = [
            [
               'text' => $button_text,
               'callback_data' => "finance_view_check_{$type}_" . $check['id']
            ]
         ];
      }

      // صفحه‌بندی
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         if ($page > 1) {
            $pagination_row[] = ['text' => '⬅️', 'callback_data' => "finance_checks_{$type}_" . ($page - 1)];
         }

         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = ['text' => $page_text, 'callback_data' => "finance_checks_{$type}_$i"];
         }

         if ($page < $total_pages) {
            $pagination_row[] = ['text' => '➡️', 'callback_data' => "finance_checks_{$type}_" . ($page + 1)];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => "➕ افزودن چک $type_title", 'callback_data' => "finance_add_check_$type"]
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'finance']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showFinancialReport($chat_id, $user_id, $message_id)
{
   global $pdo;

   // گزارش بدهی‌ها و طلب‌ها
   $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN type = 'debt' AND is_paid = 0 THEN amount ELSE 0 END) as active_debt,
        SUM(CASE WHEN type = 'credit' AND is_paid = 0 THEN amount ELSE 0 END) as active_credit,
        COUNT(CASE WHEN type = 'debt' AND is_paid = 0 AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as urgent_debts,
        COUNT(CASE WHEN type = 'credit' AND is_paid = 0 AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as urgent_credits
        FROM finances");
   $stmt->execute();
   $debt_credit_summary = $stmt->fetch();

   // گزارش چک‌ها
   $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN type = 'received' AND status = 'pending' THEN amount ELSE 0 END) as pending_received_checks,
        SUM(CASE WHEN type = 'issued' AND status = 'pending' THEN amount ELSE 0 END) as pending_issued_checks,
        COUNT(CASE WHEN status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 END) as urgent_checks
        FROM checks");
   $stmt->execute();
   $check_summary = $stmt->fetch();

   // چک‌های نزدیک سررسید
   $stmt = $pdo->prepare("SELECT type, account_holder, amount, due_date, status FROM checks 
        WHERE status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
        ORDER BY due_date ASC LIMIT 5");
   $stmt->execute();
   $upcoming_checks = $stmt->fetchAll();

   $text = "📊 <b>گزارش کامل مالی</b>\n";
   $text .= "تاریخ: " . jdate('Y/m/d H:i') . "\n\n";

   // وضعیت کلی
   $active_debt = number_format($debt_credit_summary['active_debt'] ?? 0);
   $active_credit = number_format($debt_credit_summary['active_credit'] ?? 0);
   $net_position = ($debt_credit_summary['active_credit'] ?? 0) - ($debt_credit_summary['active_debt'] ?? 0);
   $net_formatted = number_format(abs($net_position));

   $text .= "💰 <b>وضعیت کلی:</b>\n";
   $text .= "🔴 کل بدهی‌ها: $active_debt تومان\n";
   $text .= "🟢 کل طلب‌ها: $active_credit تومان\n";

   if ($net_position > 0) {
      $text .= "✅ موقعیت خالص: +$net_formatted تومان (مثبت)\n\n";
   } elseif ($net_position < 0) {
      $text .= "⚠️ موقعیت خالص: -$net_formatted تومان (منفی)\n\n";
   } else {
      $text .= "⚖️ موقعیت خالص: متعادل\n\n";
   }

   // چک‌ها
   $pending_received = number_format($check_summary['pending_received_checks'] ?? 0);
   $pending_issued = number_format($check_summary['pending_issued_checks'] ?? 0);

   $text .= "📋 <b>وضعیت چک‌ها:</b>\n";
   $text .= "📥 چک‌های دریافتی در انتظار: $pending_received تومان\n";
   $text .= "📤 چک‌های صادره در انتظار: $pending_issued تومان\n\n";

   // هشدارهای فوری
   if (!empty($upcoming_checks)) {
      $text .= "⚠️ <b>چک‌های نزدیک سررسید:</b>\n";
      foreach ($upcoming_checks as $check) {
         $due_date = jdate('m/d', strtotime($check['due_date']));
         $amount = number_format($check['amount']);
         $type_icon = $check['type'] === 'received' ? '📥' : '📤';
         $holder = mb_strlen($check['account_holder']) > 20 ? mb_substr($check['account_holder'], 0, 20) . '...' : $check['account_holder'];

         $days_left = ceil((strtotime($check['due_date']) - time()) / (24 * 3600));

         if ($days_left <= 0) {
            $urgency = "امروز";
         } elseif ($days_left == 1) {
            $urgency = "فردا";
         } else {
            $urgency = "$days_left روز دیگر";
         }

         $text .= "$type_icon $holder • $amount ت • $urgency\n";
      }
      $text .= "\n";
   }

   // توصیه‌های هوشمند
   $text .= "💡 <b>توصیه‌ها:</b>\n";

   if ($net_position < -1000000) {
      $text .= "• وضعیت مالی شما نگران‌کننده است. بدهی‌ها را اولویت‌بندی کنید.\n";
   } elseif ($net_position > 1000000) {
      $text .= "• وضعیت مالی شما عالی است! طلب‌ها را پیگیری کنید.\n";
   }

   if (($check_summary['urgent_checks'] ?? 0) > 0) {
      $text .= "• چک‌های فوری را حتماً پیگیری کنید.\n";
   }

   if (($debt_credit_summary['urgent_debts'] ?? 0) > 0) {
      $text .= "• بدهی‌های نزدیک سررسید را تسویه کنید.\n";
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🔄 بروزرسانی گزارش', 'callback_data' => 'finance_report'],
            ['text' => '📤 ارسال فایل', 'callback_data' => 'finance_export_report']
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'finance']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function startAddFinancialItem($chat_id, $user_id, $message_id, $type)
{
   $type_title = '';
   $step = '';

   switch ($type) {
      case 'debt':
         $type_title = 'بدهی';
         $step = 'finance_adding_debt_title';
         break;
      case 'credit':
         $type_title = 'طلب';
         $step = 'finance_adding_credit_title';
         break;
      case 'check_received':
         $type_title = 'چک دریافتی';
         $step = 'finance_adding_check_received_holder';
         break;
      case 'check_issued':
         $type_title = 'چک صادره';
         $step = 'finance_adding_check_issued_holder';
         break;
   }

   updateUser($user_id, ['step' => $step]);

   if (strpos($type, 'check') === 0) {
      $text = "📋 <b>افزودن $type_title</b>\n\n";
      $text .= "نام صاحب حساب/گیرنده چک را وارد کنید:";
   } else {
      $text = "💰 <b>افزودن $type_title</b>\n\n";
      $text .= "عنوان $type_title را وارد کنید:\n";
      $text .= "مثال: قرض از احمد، فروش کالا به شرکت، و...";
   }

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'finance']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewFinancialItem($chat_id, $user_id, $message_id, $type, $item_id)
{
   global $pdo;

   if (strpos($type, 'check') === 0) {
      $check_type = str_replace('check_', '', $type);
      $stmt = $pdo->prepare("SELECT * FROM checks WHERE id = :id AND type = :type");
      $stmt->bindValue(':id', $item_id, PDO::PARAM_INT);
      $stmt->bindValue(':type', $check_type, PDO::PARAM_STR);
      $stmt->execute();
      $item = $stmt->fetch();

      if (!$item) {
         editMessage($chat_id, $message_id, "❌ چک یافت نشد.");
         return;
      }

      viewCheckDetails($chat_id, $user_id, $message_id, $item, $check_type);
   } else {
      $stmt = $pdo->prepare("SELECT * FROM finances WHERE id = :id AND type = :type");
      $stmt->bindValue(':id', $item_id, PDO::PARAM_INT);
      $stmt->bindValue(':type', $type, PDO::PARAM_STR);
      $stmt->execute();
      $item = $stmt->fetch();

      if (!$item) {
         editMessage($chat_id, $message_id, "❌ مورد یافت نشد.");
         return;
      }

      viewDebtCreditDetails($chat_id, $user_id, $message_id, $item, $type);
   }
}

function viewDebtCreditDetails($chat_id, $user_id, $message_id, $item, $type)
{
   $type_title = $type === 'debt' ? 'بدهی' : 'طلب';
   $type_icon = $type === 'debt' ? '💳' : '💰';

   $created_date = jdate('Y/m/d', strtotime($item['created_at']));
   $amount = number_format($item['amount']);

   $text = "$type_icon <b>جزئیات $type_title</b>\n\n";
   $text .= "📋 <b>عنوان:</b> " . htmlspecialchars($item['title']) . "\n";
   $text .= "👤 <b>طرف حساب:</b> " . htmlspecialchars($item['person_name']) . "\n";
   $text .= "💰 <b>مبلغ:</b> $amount تومان\n";

   if ($item['due_date']) {
      $due_date = jdate('Y/m/d', strtotime($item['due_date']));
      $text .= "📅 <b>سررسید:</b> $due_date\n";

      if (!$item['is_paid']) {
         $days_left = ceil((strtotime($item['due_date']) - time()) / (24 * 3600));
         if ($days_left < 0) {
            $text .= "⚠️ <b>وضعیت:</b> گذشته از سررسید (" . abs($days_left) . " روز)\n";
         } elseif ($days_left == 0) {
            $text .= "🔥 <b>وضعیت:</b> امروز سررسید است!\n";
         } elseif ($days_left <= 7) {
            $text .= "⏰ <b>وضعیت:</b> $days_left روز تا سررسید\n";
         }
      }
   }

   $status_text = $item['is_paid'] ? '✅ تسویه شده' : '⏳ در انتظار تسویه';
   $text .= "📊 <b>وضعیت:</b> $status_text\n";

   if ($item['is_paid'] && $item['paid_at']) {
      $paid_date = jdate('Y/m/d', strtotime($item['paid_at']));
      $text .= "💚 <b>تاریخ تسویه:</b> $paid_date\n";
   }

   $text .= "📆 <b>تاریخ ثبت:</b> $created_date\n";

   if ($item['description']) {
      $text .= "💬 <b>توضیحات:</b> " . htmlspecialchars($item['description']) . "\n";
   }

   $keyboard = [
      'inline_keyboard' => []
   ];

   if (!$item['is_paid']) {
      $keyboard['inline_keyboard'][] = [
         ['text' => '✅ تسویه شد', 'callback_data' => "finance_pay_{$type}_" . $item['id']]
      ];
   }

   $keyboard['inline_keyboard'][] = [
      ['text' => '🗑 حذف', 'callback_data' => "finance_delete_{$type}_" . $item['id']],
      ['text' => '✏️ ویرایش', 'callback_data' => "finance_edit_{$type}_" . $item['id']]
   ];

   $list_callback = $type === 'debt' ? 'finance_debts_1' : 'finance_credits_1';
   $keyboard['inline_keyboard'][] = [
      ['text' => '📋 بازگشت به لیست', 'callback_data' => $list_callback]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewCheckDetails($chat_id, $user_id, $message_id, $check, $type)
{
   $type_title = $type === 'received' ? 'دریافتی' : 'صادره';
   $type_icon = $type === 'received' ? '📋' : '📄';

   $created_date = jdate('Y/m/d', strtotime($check['created_at']));
   $due_date = jdate('Y/m/d', strtotime($check['due_date']));
   $amount = number_format($check['amount']);

   $text = "$type_icon <b>جزئیات چک $type_title</b>\n\n";

   if ($check['check_number']) {
      $text .= "🔢 <b>شماره چک:</b> " . htmlspecialchars($check['check_number']) . "\n";
   }

   $text .= "👤 <b>صاحب حساب:</b> " . htmlspecialchars($check['account_holder']) . "\n";
   $text .= "💰 <b>مبلغ:</b> $amount تومان\n";

   if ($check['bank_name']) {
      $text .= "🏦 <b>بانک:</b> " . htmlspecialchars($check['bank_name']) . "\n";
   }

   if ($check['issue_date']) {
      $issue_date = jdate('Y/m/d', strtotime($check['issue_date']));
      $text .= "📅 <b>تاریخ صدور:</b> $issue_date\n";
   }

   $text .= "⏰ <b>تاریخ سررسید:</b> $due_date\n";

   // وضعیت چک
   $status_texts = [
      'pending' => '⏳ در انتظار',
      'cashed' => '✅ نقد شده',
      'bounced' => '❌ برگشت خورده',
      'cancelled' => '🚫 لغو شده'
   ];
   $status_text = $status_texts[$check['status']] ?? '❓ نامشخص';
   $text .= "📊 <b>وضعیت:</b> $status_text\n";

   // بررسی سررسید
   if ($check['status'] === 'pending') {
      $days_left = ceil((strtotime($check['due_date']) - time()) / (24 * 3600));
      if ($days_left < 0) {
         $text .= "⚠️ <b>هشدار:</b> گذشته از سررسید (" . abs($days_left) . " روز)\n";
      } elseif ($days_left == 0) {
         $text .= "🔥 <b>هشدار:</b> امروز سررسید است!\n";
      } elseif ($days_left <= 3) {
         $text .= "🚨 <b>توجه:</b> $days_left روز تا سررسید\n";
      } elseif ($days_left <= 7) {
         $text .= "⏰ <b>یادآوری:</b> $days_left روز تا سررسید\n";
      }
   }

   if ($check['cashed_at']) {
      $cashed_date = jdate('Y/m/d', strtotime($check['cashed_at']));
      $text .= "💚 <b>تاریخ نقد:</b> $cashed_date\n";
   }

   $text .= "📆 <b>تاریخ ثبت:</b> $created_date\n";

   if ($check['description']) {
      $text .= "💬 <b>توضیحات:</b> " . htmlspecialchars($check['description']) . "\n";
   }

   $keyboard = [
      'inline_keyboard' => []
   ];

   // دکمه‌های وضعیت
   if ($check['status'] === 'pending') {
      $keyboard['inline_keyboard'][] = [
         ['text' => '✅ نقد شد', 'callback_data' => "finance_cash_check_" . $check['id']],
         ['text' => '❌ برگشت خورد', 'callback_data' => "finance_bounce_check_" . $check['id']]
      ];
   }

   $keyboard['inline_keyboard'][] = [
      ['text' => '🗑 حذف', 'callback_data' => "finance_delete_check_{$type}_" . $check['id']],
      ['text' => '✏️ ویرایش', 'callback_data' => "finance_edit_check_{$type}_" . $check['id']]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '📋 بازگشت به لیست', 'callback_data' => "finance_checks_{$type}_1"]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function markAsPaid($chat_id, $user_id, $message_id, $type, $item_id)
{
   global $pdo;

   $stmt = $pdo->prepare("UPDATE finances SET is_paid = 1, paid_at = NOW() WHERE id = :id AND type = :type");
   $stmt->bindValue(':id', $item_id, PDO::PARAM_INT);
   $stmt->bindValue(':type', $type, PDO::PARAM_STR);

   if ($stmt->execute()) {
      global $callback_query_id;
      $type_title = $type === 'debt' ? 'بدهی' : 'طلب';
      answerCallbackQuery($callback_query_id, "✅ $type_title تسویه شد");

      viewFinancialItem($chat_id, $user_id, $message_id, $type, $item_id);
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در تسویه");
   }
}

function confirmDeleteFinancialItem($chat_id, $user_id, $message_id, $type, $item_id)
{
   $type_title = '';

   if (strpos($type, 'check') === 0) {
      $check_type = str_replace('check_', '', $type);
      $type_title = $check_type === 'received' ? 'چک دریافتی' : 'چک صادره';
   } else {
      $type_title = $type === 'debt' ? 'بدهی' : 'طلب';
   }

   $text = "🗑 <b>حذف $type_title</b>\n\n";
   $text .= "آیا مطمئن هستید که می‌خواهید این $type_title را حذف کنید؟\n\n";
   $text .= "⚠️ این عمل قابل بازگشت نیست!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف کن', 'callback_data' => "finance_confirmdelete_{$type}_{$item_id}"],
            ['text' => '❌ خیر', 'callback_data' => "finance_view_{$type}_{$item_id}"]
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function deleteFinancialItem($chat_id, $user_id, $message_id, $type, $item_id)
{
   global $pdo;

   if (strpos($type, 'check') === 0) {
      $stmt = $pdo->prepare("DELETE FROM checks WHERE id = :id");
      $stmt->bindValue(':id', $item_id, PDO::PARAM_INT);
   } else {
      $stmt = $pdo->prepare("DELETE FROM finances WHERE id = :id");
      $stmt->bindValue(':id', $item_id, PDO::PARAM_INT);
   }

   if ($stmt->execute()) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "🗑 حذف شد");

      // بازگشت به لیست مناسب
      if (strpos($type, 'check') === 0) {
         $check_type = str_replace('check_', '', $type);
         showChecksList($chat_id, $user_id, $message_id, $check_type, 1);
      } else {
         if ($type === 'debt') {
            showDebtsList($chat_id, $user_id, $message_id, 1);
         } else {
            showCreditsList($chat_id, $user_id, $message_id, 1);
         }
      }
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در حذف");
   }
}

function updateCheckStatus($chat_id, $user_id, $check_id, $status, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("UPDATE checks SET status = :status, cashed_at = :cashed_at WHERE id = :id");
   $cashed_at = $status === 'cashed' ? date('Y-m-d H:i:s') : null;
   $stmt->bindValue(':status', $status, PDO::PARAM_STR);
   $stmt->bindValue(':cashed_at', $cashed_at, PDO::PARAM_STR);
   $stmt->bindValue(':id', $check_id, PDO::PARAM_INT);

   if ($stmt->execute()) {
      global $callback_query_id;
      $status_text = $status === 'cashed' ? 'نقد شد' : 'برگشت خورد';
      answerCallbackQuery($callback_query_id, "✅ چک $status_text");

      // نمایش جزئیات بروزشده
      $stmt = $pdo->prepare("SELECT *, type FROM checks WHERE id = :id");
      $stmt->bindValue(':id', $check_id, PDO::PARAM_INT);
      $stmt->execute();
      $check = $stmt->fetch();

      if ($check) {
         viewCheckDetails($chat_id, $user_id, $message_id, $check, $check['type']);
      }
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در بروزرسانی");
   }
}

// توابع پردازش فرم‌های اضافه کردن موارد جدید
function processFinancialForm($chat_id, $user_id, $text, $step)
{
   $user = getUser($user_id);

   switch ($step) {
      // === بدهی ===
      case 'finance_adding_debt_title':
         updateUser($user_id, [
            'step' => 'finance_adding_debt_person',
            'temp_data' => json_encode(['title' => $text])
         ]);
         sendMessage($chat_id, "👤 نام شخص یا شرکت بدهکار را وارد کنید:");
         break;

      case 'finance_adding_debt_person':
         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['person_name'] = $text;
         updateUser($user_id, [
            'step' => 'finance_adding_debt_amount',
            'temp_data' => json_encode($temp_data)
         ]);
         sendMessage($chat_id, "💰 مبلغ بدهی را به تومان وارد کنید:");
         break;

      case 'finance_adding_debt_amount':
         $text = cleanNumber($text);
         if (!is_numeric($text) || $text <= 0) {
            sendMessage($chat_id, "❌ لطفاً مبلغ معتبری وارد کنید.");
            return;
         }

         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['amount'] = $text;
         updateUser($user_id, [
            'step' => 'finance_adding_debt_due_date',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ بدون سررسید', 'callback_data' => 'finance_skip_due_date']]
            ]
         ];

         sendMessage($chat_id, "📅 تاریخ سررسید را وارد کنید:\n(فرمت: 1403/12/15 یا از دکمه زیر استفاده کنید)", $keyboard);
         break;

      case 'finance_adding_debt_due_date':
         $temp_data = json_decode($user['temp_data'], true);

         if (trim($text) === '' || $text === 'بدون سررسید') {
            $due_date = null;
         } else {
            $due_date = validatePersianDate($text);
            if (!$due_date) {
               sendMessage($chat_id, "❌ تاریخ نامعتبر است. لطفاً به فرمت 1403/12/15 وارد کنید یا از دکمه بدون سررسید استفاده کنید.");
               return;
            }
         }

         saveDebtCredit($chat_id, $user_id, 'debt', $temp_data['title'], $temp_data['person_name'], $temp_data['amount'], $due_date);
         break;

      // === طلب ===
      case 'finance_adding_credit_title':
         updateUser($user_id, [
            'step' => 'finance_adding_credit_person',
            'temp_data' => json_encode(['title' => $text])
         ]);
         sendMessage($chat_id, "👤 نام شخص یا شرکت بدهکار را وارد کنید:");
         break;

      case 'finance_adding_credit_person':
         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['person_name'] = $text;
         updateUser($user_id, [
            'step' => 'finance_adding_credit_amount',
            'temp_data' => json_encode($temp_data)
         ]);
         sendMessage($chat_id, "💰 مبلغ طلب را به تومان وارد کنید:");
         break;

      case 'finance_adding_credit_amount':
         $text = cleanNumber($text);
         if (!is_numeric($text) || $text <= 0) {
            sendMessage($chat_id, "❌ لطفاً مبلغ معتبری وارد کنید.");
            return;
         }

         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['amount'] = $text;
         updateUser($user_id, [
            'step' => 'finance_adding_credit_due_date',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ بدون سررسید', 'callback_data' => 'finance_skip_due_date']]
            ]
         ];

         sendMessage($chat_id, "📅 تاریخ سررسید را وارد کنید:\n(فرمت: 1403/12/15 یا از دکمه زیر استفاده کنید)", $keyboard);
         break;

      case 'finance_adding_credit_due_date':
         $temp_data = json_decode($user['temp_data'], true);

         if (trim($text) === '' || $text === 'بدون سررسید') {
            $due_date = null;
         } else {
            $due_date = validatePersianDate($text);
            if (!$due_date) {
               sendMessage($chat_id, "❌ تاریخ نامعتبر است. لطفاً به فرمت 1403/12/15 وارد کنید یا از دکمه بدون سررسید استفاده کنید.");
               return;
            }
         }

         saveDebtCredit($chat_id, $user_id, 'credit', $temp_data['title'], $temp_data['person_name'], $temp_data['amount'], $due_date);
         break;

      // === چک دریافتی ===
      case 'finance_adding_check_received_holder':
         updateUser($user_id, [
            'step' => 'finance_adding_check_received_amount',
            'temp_data' => json_encode(['account_holder' => $text])
         ]);
         sendMessage($chat_id, "💰 مبلغ چک را به تومان وارد کنید:");
         break;

      case 'finance_adding_check_received_amount':
         $text = cleanNumber($text);
         if (!is_numeric($text) || $text <= 0) {
            sendMessage($chat_id, "❌ لطفاً مبلغ معتبری وارد کنید.");
            return;
         }

         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['amount'] = $text;
         updateUser($user_id, [
            'step' => 'finance_adding_check_received_due_date',
            'temp_data' => json_encode($temp_data)
         ]);

         sendMessage($chat_id, "📅 تاریخ سررسید چک را وارد کنید:\n(فرمت: 1403/12/15)");
         break;

      case 'finance_adding_check_received_due_date':
         $due_date = validatePersianDate($text);
         if (!$due_date) {
            sendMessage($chat_id, "❌ تاریخ نامعتبر است. لطفاً به فرمت 1403/12/15 وارد کنید.");
            return;
         }

         $temp_data = json_decode($user['temp_data'], true);
         saveCheck($chat_id, $user_id, 'received', $temp_data['account_holder'], $temp_data['amount'], $due_date);
         break;

      // === چک صادره ===
      case 'finance_adding_check_issued_holder':
         updateUser($user_id, [
            'step' => 'finance_adding_check_issued_amount',
            'temp_data' => json_encode(['account_holder' => $text])
         ]);
         sendMessage($chat_id, "💰 مبلغ چک را به تومان وارد کنید:");
         break;

      case 'finance_adding_check_issued_amount':
         $text = cleanNumber($text);
         if (!is_numeric($text) || $text <= 0) {
            sendMessage($chat_id, "❌ لطفاً مبلغ معتبری وارد کنید.");
            return;
         }

         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['amount'] = $text;
         updateUser($user_id, [
            'step' => 'finance_adding_check_issued_due_date',
            'temp_data' => json_encode($temp_data)
         ]);

         sendMessage($chat_id, "📅 تاریخ سررسید چک را وارد کنید:\n(فرمت: 1403/12/15)");
         break;

      case 'finance_adding_check_issued_due_date':
         $due_date = validatePersianDate($text);
         if (!$due_date) {
            sendMessage($chat_id, "❌ تاریخ نامعتبر است. لطفاً به فرمت 1403/12/15 وارد کنید.");
            return;
         }

         $temp_data = json_decode($user['temp_data'], true);
         saveCheck($chat_id, $user_id, 'issued', $temp_data['account_holder'], $temp_data['amount'], $due_date);
         break;

      default:
         updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);
         sendMessage($chat_id, "❌ مرحله نامعتبر. لطفاً دوباره تلاش کنید.");
         break;
   }
}

function validatePersianDate($date_str)
{
   // تبدیل تاریخ شمسی به میلادی
   if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date_str, $matches)) {
      $year = intval($matches[1]);
      $month = intval($matches[2]);
      $day = intval($matches[3]);

      if ($year >= 1400 && $year <= 1450 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
         // استفاده از تابع jalali_to_gregorian از jdf.php
         list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
         return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
      }
   }

   return false;
}

function saveDebtCredit($chat_id, $user_id, $type, $title, $person_name, $amount, $due_date = null, $description = null)
{
   global $pdo;

   $stmt = $pdo->prepare("INSERT INTO finances (type, title, person_name, amount, due_date, description, created_at) VALUES (:type, :title, :person_name, :amount, :due_date, :description, NOW())");
   $stmt->bindValue(':type', $type, PDO::PARAM_STR);
   $stmt->bindValue(':title', $title, PDO::PARAM_STR);
   $stmt->bindValue(':person_name', $person_name, PDO::PARAM_STR);
   $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);
   $stmt->bindValue(':due_date', $due_date, PDO::PARAM_STR);
   $stmt->bindValue(':description', $description, PDO::PARAM_STR);

   if ($stmt->execute()) {
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $type_title = $type === 'debt' ? 'بدهی' : 'طلب';
      $amount_formatted = number_format($amount);

      $response = "✅ <b>$type_title با موفقیت ثبت شد!</b>\n\n";
      $response .= "📋 عنوان: $title\n";
      $response .= "👤 طرف حساب: $person_name\n";
      $response .= "💰 مبلغ: $amount_formatted تومان\n";

      if ($due_date) {
         $due_date_persian = jdate('Y/m/d', strtotime($due_date));
         $response .= "📅 سررسید: $due_date_persian\n";
      }

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => "📋 لیست {$type_title}ها", 'callback_data' => "finance_{$type}s_1"],
               ['text' => '💰 مدیریت مالی', 'callback_data' => 'finance']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.");
   }
}

function saveCheck($chat_id, $user_id, $type, $account_holder, $amount, $due_date, $check_number = null, $bank_name = null, $description = null)
{
   global $pdo;

   $stmt = $pdo->prepare("INSERT INTO checks (type, account_holder, amount, due_date, check_number, bank_name, description, created_at) VALUES (:type, :account_holder, :amount, :due_date, :check_number, :bank_name, :description, NOW())");
   $stmt->bindValue(':type', $type, PDO::PARAM_STR);
   $stmt->bindValue(':account_holder', $account_holder, PDO::PARAM_STR);
   $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);
   $stmt->bindValue(':due_date', $due_date, PDO::PARAM_STR);
   $stmt->bindValue(':check_number', $check_number, PDO::PARAM_STR);
   $stmt->bindValue(':bank_name', $bank_name, PDO::PARAM_STR);
   $stmt->bindValue(':description', $description, PDO::PARAM_STR);

   if ($stmt->execute()) {
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $type_title = $type === 'received' ? 'دریافتی' : 'صادره';
      $amount_formatted = number_format($amount);
      $due_date_persian = jdate('Y/m/d', strtotime($due_date));

      // محاسبه روزهای باقیمانده
      $days_left = ceil((strtotime($due_date) - time()) / (24 * 3600));
      $days_text = $days_left > 0 ? " ($days_left روز دیگر)" : " (گذشته از سررسید)";

      $response = "✅ <b>چک $type_title با موفقیت ثبت شد!</b>\n\n";
      $response .= "👤 صاحب حساب: $account_holder\n";
      $response .= "💰 مبلغ: $amount_formatted تومان\n";
      $response .= "📅 سررسید: $due_date_persian$days_text\n";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => "📋 لیست چک‌های $type_title", 'callback_data' => "finance_checks_{$type}_1"],
               ['text' => '💰 مدیریت مالی', 'callback_data' => 'finance']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.");
   }
}

