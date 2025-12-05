<?php

function showSocialMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   // آمار کلی مخاطبین
   $stmt = $pdo->prepare("SELECT COUNT(*) as total_contacts FROM contacts WHERE user_id = :user_id");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $stats = $stmt->fetch();

   // تولدهای امروز
   $stmt = $pdo->prepare("SELECT name FROM contacts 
        WHERE user_id = :user_id AND birthday IS NOT NULL
        AND DATE_FORMAT(birthday, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $today_birthdays = $stmt->fetchAll();

   // تولدهای نزدیک (7 روز آینده - بدون امروز)
   $stmt = $pdo->prepare("SELECT name, birthday, 
        DATEDIFF(
            CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(birthday, '%m-%d')),
            CURDATE()
        ) as days_until
        FROM contacts 
        WHERE user_id = :user_id AND birthday IS NOT NULL
        HAVING days_until > 0 AND days_until <= 7
        ORDER BY days_until ASC
        LIMIT 3");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $upcoming_birthdays = $stmt->fetchAll();

   // افرادی که باید باهاشون تماس گرفت (فقط کسانی که قبلاً تماس گرفته‌اند)
   $stmt = $pdo->prepare("SELECT name, 
        CONCAT(DATEDIFF(CURDATE(), last_contact_date), ' روز پیش') as last_contact_text,
        DATEDIFF(CURDATE(), last_contact_date) as days_since
        FROM contacts 
        WHERE user_id = :user_id 
        AND contact_frequency > 0
        AND last_contact_date IS NOT NULL 
        AND DATEDIFF(CURDATE(), last_contact_date) >= contact_frequency
        ORDER BY days_since DESC
        LIMIT 3");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $contact_reminders = $stmt->fetchAll();

   $text = "👥 <b>مدیریت مخاطبین</b>\n\n";

   // متغیر برای بررسی اینکه آیا اطلاعات فوری وجود دارد یا نه
   $has_urgent_info = false;

   // نمایش تولدهای امروز
   if (!empty($today_birthdays)) {
      $text .= "🎉 <b>تولد امروز:</b>\n";
      foreach ($today_birthdays as $birthday) {
         $text .= "🎂 " . htmlspecialchars($birthday['name']) . "\n";
      }
      $text .= "\n";
      $has_urgent_info = true;
   }

   // نمایش تولدهای نزدیک
   if (!empty($upcoming_birthdays)) {
      $text .= "🎁 <b>تولدهای نزدیک:</b>\n";
      foreach ($upcoming_birthdays as $birthday) {
         $days = $birthday['days_until'];
         if ($days == 1) {
            $text .= "🎈 فردا: " . htmlspecialchars($birthday['name']) . "\n";
         } else {
            $text .= "📅 $days روز دیگر: " . htmlspecialchars($birthday['name']) . "\n";
         }
      }
      $text .= "\n";
      $has_urgent_info = true;
   }

   // نمایش یادآوری تماس
   if (!empty($contact_reminders)) {
      $text .= "📞 <b>نیاز به تماس:</b>\n";
      foreach ($contact_reminders as $contact) {
         $text .= "☎️ " . htmlspecialchars($contact['name']) . " - " . $contact['last_contact_text'] . "\n";
      }
      $text .= "\n";
      $has_urgent_info = true;
   }

   // اگر هیچ اطلاعات فوری نداشتیم، پیام مثبت نمایش دهیم
   if (!$has_urgent_info /* && ($stats['total_contacts'] ?? 0) > 0 */) {
      $text .= "✅ <b>همه چیز به‌روز است!</b>\n";
      $text .= "• هیچ تولدی در هفته آینده نیست\n";
      $text .= "• همه تماس‌های لازم انجام شده\n\n";
   }

   // آمار کلی
   $text .= "📊 <b>آمار:</b>\n";
   $text .= "👤 تعداد مخاطبین: " . ($stats['total_contacts'] ?? 0) . "\n\n";

   $text .= "<b>امکانات:</b>\n";
   $text .= "• ثبت اطلاعات کامل افراد\n";
   $text .= "• یادآوری خودکار تولد و تماس\n";
   $text .= "• جستجوی هوشمند مخاطبین";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '👥 لیست مخاطبین' . " (" . ($stats['total_contacts'] ?? 0) . ")", 'callback_data' => 'social_list'],
            ['text' => '➕ افزودن مخاطب', 'callback_data' => 'social_add']
         ],
         [
            ['text' => '🔍 جستجو', 'callback_data' => 'social_search']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function handleSocialCallback($chat_id, $user_id, $data, $message_id)
{
   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'list':
         $page = $parts[2] ?? 1;
         showContactsList($chat_id, $user_id, $message_id, $page);
         break;

      case 'add':
         startAddContact($chat_id, $user_id, $message_id);
         break;

      case 'view':
         $contact_id = $parts[2] ?? 0;
         viewContactDetails($chat_id, $user_id, $message_id, $contact_id);
         break;

      case 'edit':
         $contact_id = $parts[2] ?? 0;
         $field = $parts[3] ?? '';
         startEditContact($chat_id, $user_id, $message_id, $contact_id, $field);
         break;

      case 'delete':
         $contact_id = $parts[2] ?? 0;
         confirmDeleteContact($chat_id, $user_id, $message_id, $contact_id);
         break;

      case 'confirmdelete':
         $contact_id = $parts[2] ?? 0;
         deleteContact($chat_id, $user_id, $message_id, $contact_id);
         break;

      case 'search':
         startContactSearch($chat_id, $user_id, $message_id);
         break;

      case 'contacted':
         $contact_id = $parts[2] ?? 0;
         markAsContacted($chat_id, $user_id, $message_id, $contact_id);
         break;

      case 'skip':
         $type = $parts[2] ?? '';
         deleteMessage($chat_id, $message_id);
         skipContactField($chat_id, $user_id, $type);
         break;

      default:
         global $callback_query_id;
         answerCallbackQuery($callback_query_id, "❌ دستور نامعتبر");
         break;
   }
}

function showContactsList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 8;
   $offset = ($page - 1) * $limit;

   $stmt = $pdo->prepare("SELECT * FROM contacts WHERE user_id = :user_id ORDER BY name ASC LIMIT :limit OFFSET :offset");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $contacts = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE user_id = :user_id");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $total = $stmt->fetchColumn();

   if (empty($contacts)) {
      $text = "👥 <b>لیست مخاطبین</b>\n\n";
      $text .= "❌ هیچ مخاطبی ثبت نشده!\n\n";
      $text .= "برای افزودن مخاطب جدید از دکمه زیر استفاده کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ افزودن مخاطب', 'callback_data' => 'social_add']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'social']]
         ]
      ];
   } else {
      $text = "👥 <b>لیست مخاطبین</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total نفر\n\n";

      $keyboard = [
         'inline_keyboard' => []
      ];

      foreach ($contacts as $contact) {
         // آیکون‌های وضعیت
         $icons = [];

         // بررسی تولد نزدیک
         if ($contact['birthday']) {
            $days_until = calculateDaysUntilBirthday($contact['birthday']);
            if ($days_until >= 0 && $days_until <= 7) {
               $icons[] = '🎂';
            }
         }

         // بررسی نیاز به تماس
         if ($contact['last_contact_date'] && $contact['contact_frequency']) {
            $days_since = (time() - strtotime($contact['last_contact_date'])) / (24 * 3600);
            if ($days_since >= $contact['contact_frequency']) {
               $icons[] = '📞';
            }
         }

         $icon_text = !empty($icons) ? ' ' . implode('', $icons) : '';
         $name = mb_strlen($contact['name']) > 25 ? mb_substr($contact['name'], 0, 25) . '...' : $contact['name'];

         $keyboard['inline_keyboard'][] = [
            [
               'text' => "👤 $name$icon_text",
               'callback_data' => 'social_view_' . $contact['id']
            ]
         ];
      }

      // صفحه‌بندی
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         if ($page > 1) {
            $pagination_row[] = ['text' => '⬅️', 'callback_data' => 'social_list_' . ($page - 1)];
         }

         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = ['text' => $page_text, 'callback_data' => 'social_list_' . $i];
         }

         if ($page < $total_pages) {
            $pagination_row[] = ['text' => '➡️', 'callback_data' => 'social_list_' . ($page + 1)];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '➕ افزودن مخاطب', 'callback_data' => 'social_add']
      ];
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'social']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewContactDetails($chat_id, $user_id, $message_id, $contact_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $contact_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $contact = $stmt->fetch();

   if (!$contact) {
      editMessage($chat_id, $message_id, "❌ مخاطب یافت نشد.");
      return;
   }

   $text = "👤 <b>" . htmlspecialchars($contact['name']) . "</b>\n\n";

   // اطلاعات اصلی
   if ($contact['phone']) {
      $text .= "📱 <b>تلفن:</b> " . htmlspecialchars($contact['phone']) . "\n";
   }

   if ($contact['birthday']) {
      $birthday = jdate('Y/m/d', strtotime($contact['birthday']));
      $age = calculateAge($contact['birthday']);
      $days_until = calculateDaysUntilBirthday($contact['birthday']);

      $text .= "🎂 <b>تولد:</b> $birthday ($age ساله)\n";

      if ($days_until == 0) {
         $text .= "🎉 <b>امروز تولدش است!</b>\n";
      } elseif ($days_until > 0 && $days_until <= 7) {
         $text .= "🎈 <b>$days_until روز تا تولد</b>\n";
      }
   }

   if ($contact['relationship']) {
      $text .= "👥 <b>نسبت:</b> " . htmlspecialchars($contact['relationship']) . "\n";
   }

   // یادداشت‌ها
   if ($contact['notes']) {
      $text .= "\n📝 <b>یادداشت:</b>\n" . htmlspecialchars($contact['notes']) . "\n";
   }

   // آخرین تماس
   if ($contact['last_contact_date']) {
      $last_contact = jdate('Y/m/d', strtotime($contact['last_contact_date']));
      $days_since = ceil((time() - strtotime($contact['last_contact_date'])) / (24 * 3600));

      $text .= "\n📞 <b>آخرین تماس:</b> $last_contact ($days_since روز پیش)\n";

      if ($contact['contact_frequency'] && $days_since >= $contact['contact_frequency']) {
         $text .= "⚠️ <b>زمان تماس مجدد فرا رسیده!</b>\n";
      }
   }

   // تاریخ ثبت
   $created = jdate('Y/m/d', strtotime($contact['created_at']));
   $text .= "\n📅 <b>تاریخ ثبت:</b> $created";

   $keyboard = [
      'inline_keyboard' => []
   ];

   // دکمه تماس گرفتم
   if ($contact['contact_frequency']) {
      $keyboard['inline_keyboard'][] = [
         ['text' => '✅ تماس گرفتم', 'callback_data' => 'social_contacted_' . $contact_id]
      ];
   }

   // دکمه‌های ویرایش
   $keyboard['inline_keyboard'][] = [
      ['text' => '✏️ ویرایش نام', 'callback_data' => 'social_edit_' . $contact_id . '_name'],
      ['text' => '📱 ویرایش تلفن', 'callback_data' => 'social_edit_' . $contact_id . '_phone']
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🎂 ویرایش تولد', 'callback_data' => 'social_edit_' . $contact_id . '_birthday'],
      ['text' => '📝 ویرایش یادداشت', 'callback_data' => 'social_edit_' . $contact_id . '_notes']
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🗑 حذف', 'callback_data' => 'social_delete_' . $contact_id]
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '📋 بازگشت به لیست', 'callback_data' => 'social_list_1']
   ];

   $keyboard['inline_keyboard'][] = [
      ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function startAddContact($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'social_adding_contact_name']);

   $text = "➕ <b>افزودن مخاطب جدید</b>\n\n";
   $text .= "لطفاً نام کامل شخص را وارد کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'social']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function startContactSearch($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'social_searching_contacts']);

   $text = "🔍 <b>جستجوی مخاطبین</b>\n\n";
   $text .= "نام، شماره تلفن یا هر اطلاعاتی از مخاطب را وارد کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'social']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function confirmDeleteContact($chat_id, $user_id, $message_id, $contact_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT name FROM contacts WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $contact_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $contact = $stmt->fetch();

   if (!$contact) {
      editMessage($chat_id, $message_id, "❌ مخاطب یافت نشد.");
      return;
   }

   $text = "🗑 <b>حذف مخاطب</b>\n\n";
   $text .= "آیا مطمئن هستید که می‌خواهید این مخاطب را حذف کنید؟\n\n";
   $text .= "👤 <b>نام:</b> " . htmlspecialchars($contact['name']) . "\n\n";
   $text .= "⚠️ این عمل قابل بازگشت نیست!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف کن', 'callback_data' => 'social_confirmdelete_' . $contact_id],
            ['text' => '❌ خیر', 'callback_data' => 'social_view_' . $contact_id]
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function deleteContact($chat_id, $user_id, $message_id, $contact_id)
{
   global $pdo;

   $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $contact_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

   if ($stmt->execute()) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "🗑 مخاطب حذف شد");
      showContactsList($chat_id, $user_id, $message_id, 1);
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در حذف مخاطب");
   }
}

function markAsContacted($chat_id, $user_id, $message_id, $contact_id)
{
   global $pdo;

   $stmt = $pdo->prepare("UPDATE contacts SET last_contact_date = CURDATE() WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $contact_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

   if ($stmt->execute()) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "✅ تاریخ تماس ثبت شد");
      viewContactDetails($chat_id, $user_id, $message_id, $contact_id);
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در ثبت تماس");
   }
}

// توابع پردازش فرم‌ها
function processSocialForm($chat_id, $user_id, $text, $step)
{
   $user = getUser($user_id);

   switch ($step) {
      case 'social_adding_contact_name':
         updateUser($user_id, [
            'step' => 'social_adding_contact_phone',
            'temp_data' => json_encode(['name' => $text])
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_phone']]
            ]
         ];

         sendMessage($chat_id, "📱 شماره تلفن (اختیاری):", $keyboard);
         break;

      case 'social_adding_contact_phone':
         $temp_data = json_decode($user['temp_data'], true);

         if ($text !== '' && !preg_match('/^[\d\s\-\+\(\)]+$/', $text)) {
            sendMessage($chat_id, "❌ شماره تلفن معتبر نیست. فقط اعداد و کاراکترهای - + () مجاز است.");
            return;
         }

         $temp_data['phone'] = $text ?: null;
         updateUser($user_id, [
            'step' => 'social_adding_contact_birthday',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_birthday']]
            ]
         ];

         sendMessage($chat_id, "🎂 تاریخ تولد (فرمت: 1376/1/15):", $keyboard);
         break;

      case 'social_adding_contact_birthday':
         $temp_data = json_decode($user['temp_data'], true);

         if ($text !== '') {
            $birthday = validatePersianDate($text);
            if (!$birthday) {
               sendMessage($chat_id, "❌ تاریخ نامعتبر است. لطفاً به فرمت 1370/1/15 وارد کنید.");
               return;
            }
            $temp_data['birthday'] = $birthday;
         } else {
            $temp_data['birthday'] = null;
         }

         updateUser($user_id, [
            'step' => 'social_adding_contact_relationship',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_relationship']]
            ]
         ];

         sendMessage($chat_id, "👥 نسبت (مثل: دوست، همکار، خانواده):", $keyboard);
         break;

      case 'social_adding_contact_relationship':
         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['relationship'] = $text ?: null;

         updateUser($user_id, [
            'step' => 'social_adding_contact_notes',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_notes']]
            ]
         ];

         sendMessage($chat_id, "📝 یادداشت (اختیاری):", $keyboard);
         break;

      case 'social_adding_contact_notes':
         $temp_data = json_decode($user['temp_data'], true);
         $temp_data['notes'] = $text ?: null;

         updateUser($user_id, [
            'step' => 'social_adding_contact_frequency',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_frequency']]
            ]
         ];

         sendMessage($chat_id, "📞 هر چند روز یکبار تماس بگیرید؟ (عدد وارد کنید):", $keyboard);
         break;

      case 'social_adding_contact_frequency':
         $temp_data = json_decode($user['temp_data'], true);

         if ($text !== '') {
            $text = cleanNumber($text);
            if (!is_numeric($text) || $text < 0) {
               sendMessage($chat_id, "❌ لطفاً یک عدد معتبر وارد کنید.");
               return;
            }
            $temp_data['contact_frequency'] = intval($text);
         } else {
            $temp_data['contact_frequency'] = 30; // پیش‌فرض 30 روز
         }

         // ذخیره مخاطب
         saveContact($chat_id, $user_id, $temp_data);
         break;

      case 'social_searching_contacts':
         searchContacts($chat_id, $user_id, $text);
         break;

      default:
         // ویرایش فیلدهای مخاطب
         if (strpos($step, 'social_editing_contact_') === 0) {
            $parts = explode('_', $step);
            $contact_id = $parts[2] ?? 0;
            $field = $parts[3] ?? '';

            updateContactField($chat_id, $user_id, $contact_id, $field, $text);
         }
         break;
   }
}

function saveContact($chat_id, $user_id, $data)
{
   global $pdo;

   $stmt = $pdo->prepare("INSERT INTO contacts (user_id, name, phone, birthday, relationship, notes, contact_frequency, created_at) 
                          VALUES (:user_id, :name, :phone, :birthday, :relationship, :notes, :contact_frequency, NOW())");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
   $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
   $stmt->bindValue(':birthday', $data['birthday'], PDO::PARAM_STR);
   $stmt->bindValue(':relationship', $data['relationship'], PDO::PARAM_STR);
   $stmt->bindValue(':notes', $data['notes'], PDO::PARAM_STR);
   $stmt->bindValue(':contact_frequency', $data['contact_frequency'] ?? 30, PDO::PARAM_INT);

   if ($stmt->execute()) {
      $contact_id = $pdo->lastInsertId();
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $response = "✅ <b>مخاطب با موفقیت ثبت شد!</b>\n\n";
      $response .= "👤 نام: " . htmlspecialchars($data['name']) . "\n";

      if ($data['phone']) {
         $response .= "📱 تلفن: " . htmlspecialchars($data['phone']) . "\n";
      }

      if ($data['birthday']) {
         $response .= "🎂 تولد: " . jdate('Y/m/d', strtotime($data['birthday'])) . "\n";
      }

      if ($data['notes']) {
         $response .= "📝 یادداشت: " . htmlspecialchars($data['notes']) . "\n";
      }

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '👁 مشاهده مخاطب', 'callback_data' => 'social_view_' . $contact_id],
               ['text' => '➕ افزودن مخاطب دیگر', 'callback_data' => 'social_add']
            ],
            [
               ['text' => '📋 لیست مخاطبین', 'callback_data' => 'social_list_1'],
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در ثبت مخاطب. لطفاً دوباره تلاش کنید.");
   }
}

function searchContacts($chat_id, $user_id, $query)
{
   global $pdo;

   $search_term = "%$query%";
   $stmt = $pdo->prepare("
        SELECT * FROM contacts 
        WHERE user_id = :user_id 
        AND (name LIKE :search1 OR phone LIKE :search2 OR relationship LIKE :search3 OR notes LIKE :search4)
        ORDER BY name ASC
        LIMIT 10
    ");
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->bindValue(':search1', $search_term, PDO::PARAM_STR);
   $stmt->bindValue(':search2', $search_term, PDO::PARAM_STR);
   $stmt->bindValue(':search3', $search_term, PDO::PARAM_STR);
   $stmt->bindValue(':search4', $search_term, PDO::PARAM_STR);
   $stmt->execute();
   $results = $stmt->fetchAll();

   if (empty($results)) {
      $text = "🔍 <b>نتیجه جستجو</b>\n\n";
      $text .= "❌ مخاطبی با این مشخصات یافت نشد.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '🔍 جستجوی مجدد', 'callback_data' => 'social_search'],
               ['text' => '🔙 بازگشت', 'callback_data' => 'social']
            ]
         ]
      ];
   } else {
      $text = "🔍 <b>نتایج جستجو</b>\n";
      $text .= "تعداد: " . count($results) . " مخاطب\n\n";

      $keyboard = ['inline_keyboard' => []];

      foreach ($results as $index => $contact) {
         $num = $index + 1;
         $text .= "$num. 👤 " . htmlspecialchars($contact['name']);

         if ($contact['phone']) {
            $text .= " - " . htmlspecialchars($contact['phone']);
         }

         if ($contact['relationship']) {
            $text .= " (" . htmlspecialchars($contact['relationship']) . ")";
         }

         $text .= "\n";

         $keyboard['inline_keyboard'][] = [
            ['text' => "$num. " . $contact['name'], 'callback_data' => 'social_view_' . $contact['id']]
         ];
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '🔍 جستجوی مجدد', 'callback_data' => 'social_search'],
         ['text' => '🔙 بازگشت', 'callback_data' => 'social']
      ];
   }

   updateUser($user_id, ['step' => 'completed']);
   sendMessage($chat_id, $text, $keyboard);
}

function skipContactField($chat_id, $user_id, $type)
{
   $user = getUser($user_id);
   $temp_data = json_decode($user['temp_data'], true);

   switch ($type) {
      case 'phone':
         $temp_data['phone'] = null;
         updateUser($user_id, [
            'step' => 'social_adding_contact_birthday',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_birthday']]
            ]
         ];

         sendMessage($chat_id, "🎂 تاریخ تولد (فرمت: 1370/1/15):", $keyboard);
         break;

      case 'birthday':
         $temp_data['birthday'] = null;
         updateUser($user_id, [
            'step' => 'social_adding_contact_relationship',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_relationship']]
            ]
         ];

         sendMessage($chat_id, "👥 نسبت (مثل: دوست، همکار، خانواده):", $keyboard);
         break;

      case 'relationship':
         $temp_data['relationship'] = null;
         updateUser($user_id, [
            'step' => 'social_adding_contact_notes',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_notes']]
            ]
         ];

         sendMessage($chat_id, "📝 یادداشت (اختیاری):", $keyboard);
         break;

      case 'notes':
         $temp_data['notes'] = null;
         updateUser($user_id, [
            'step' => 'social_adding_contact_frequency',
            'temp_data' => json_encode($temp_data)
         ]);

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '⏭ رد کردن', 'callback_data' => 'social_skip_frequency']]
            ]
         ];

         sendMessage($chat_id, "📞 هر چند روز یکبار تماس بگیرید؟ (عدد وارد کنید):", $keyboard);
         break;

      case 'frequency':
         $temp_data['contact_frequency'] = 30; // پیش‌فرض 30 روز
         saveContact($chat_id, $user_id, $temp_data);
         break;
   }
}

function startEditContact($chat_id, $user_id, $message_id, $contact_id, $field)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':id', $contact_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
   $stmt->execute();
   $contact = $stmt->fetch();

   if (!$contact) {
      editMessage($chat_id, $message_id, "❌ مخاطب یافت نشد.");
      return;
   }

   $field_names = [
      'name' => 'نام',
      'phone' => 'شماره تلفن',
      'birthday' => 'تاریخ تولد',
      'notes' => 'یادداشت'
   ];

   $field_hints = [
      'name' => 'نام کامل شخص',
      'phone' => 'شماره تلفن (اختیاری)',
      'birthday' => 'تاریخ تولد به فرمت 1370/1/15',
      'notes' => 'یادداشت یا توضیحات'
   ];

   updateUser($user_id, ['step' => 'social_editing_contact_' . $contact_id . '_' . $field]);

   $text = "✏️ <b>ویرایش " . $field_names[$field] . "</b>\n\n";
   $text .= "👤 مخاطب: " . htmlspecialchars($contact['name']) . "\n\n";

   if ($contact[$field]) {
      $current_value = $contact[$field];
      if ($field == 'birthday') {
         $current_value = jdate('Y/m/d', strtotime($current_value));
      }
      $text .= "💡 مقدار فعلی: " . htmlspecialchars($current_value) . "\n\n";
   }

   $text .= "لطفاً " . $field_hints[$field] . " را وارد کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'social_view_' . $contact_id]]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function updateContactField($chat_id, $user_id, $contact_id, $field, $value)
{
   global $pdo;

   // اعتبارسنجی بر اساس نوع فیلد
   switch ($field) {
      case 'name':
         if (empty(trim($value))) {
            sendMessage($chat_id, "❌ نام نمی‌تواند خالی باشد.");
            return;
         }
         break;

      case 'phone':
         if (!empty($value) && !preg_match('/^[\d\s\-\+\(\)]+$/', $value)) {
            sendMessage($chat_id, "❌ شماره تلفن معتبر نیست.");
            return;
         }
         break;

      case 'birthday':
         if (!empty($value)) {
            $value = validatePersianDate($value);
            if (!$value) {
               sendMessage($chat_id, "❌ تاریخ نامعتبر است. لطفاً به فرمت 1370/1/15 وارد کنید.");
               return;
            }
         }
         break;
   }

   // به‌روزرسانی در دیتابیس
   $stmt = $pdo->prepare("UPDATE contacts SET $field = :value, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
   $stmt->bindValue(':value', $value ?: null, PDO::PARAM_STR);
   $stmt->bindValue(':id', $contact_id, PDO::PARAM_INT);
   $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

   if ($stmt->execute()) {
      updateUser($user_id, ['step' => 'completed']);

      $response = "✅ <b>" . $field . " با موفقیت به‌روزرسانی شد!</b>";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '👁 مشاهده مخاطب', 'callback_data' => 'social_view_' . $contact_id]],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در به‌روزرسانی. لطفاً دوباره تلاش کنید.");
   }
}


function calculateDaysUntilBirthday($birthday)
{
   $today = new DateTime();
   $birthDate = new DateTime($birthday);

   // تنظیم سال تولد به سال جاری
   $birthDate->setDate($today->format('Y'), $birthDate->format('m'), $birthDate->format('d'));

   // اگر تولد امسال گذشته، سال بعد را حساب کن
   if ($birthDate < $today) {
      $birthDate->modify('+1 year');
   }

   $interval = $today->diff($birthDate);
   return $interval->days;
}

function validatePersianDate($date)
{
   // حذف فاصله‌های اضافی
   $date = trim($date);

   // تبدیل اعداد فارسی به انگلیسی
   $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
   $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
   $date = str_replace($persian, $english, $date);

   // بررسی فرمت
   if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)) {
      return false;
   }

   $year = intval($matches[1]);
   $month = intval($matches[2]);
   $day = intval($matches[3]);

   // بررسی محدوده
   if ($year < 1300 || $year > 1500 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
      return false;
   }

   // تبدیل به میلادی
   $gregorian = jalali_to_gregorian($year, $month, $day);
   return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
}
