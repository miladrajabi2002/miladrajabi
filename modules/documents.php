<?php
function showDocumentsMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   // آمار کلی اسناد
   $stmt = $pdo->prepare("
      SELECT 
         COUNT(*) as total,
         SUM(CASE WHEN expire_date <= CURDATE() THEN 1 ELSE 0 END) as expired,
         SUM(CASE WHEN expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
         SUM(CASE WHEN expire_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as valid,
         SUM(CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END) as no_expire
      FROM documents
   ");
   $stmt->execute();
   $stats = $stmt->fetch();

   $text = "📑 <b>مدیریت اسناد و مدارک</b>\n\n";

   // نمایش آمار به صورت گرافیکی
   if ($stats['total'] > 0) {
      $text .= "📊 <b>گزارش وضعیت اسناد:</b>\n";
      $text .= "├ 📁 مجموع: {$stats['total']} سند\n";

      if ($stats['expired'] > 0) {
         $text .= "├ 🔴 منقضی شده: {$stats['expired']}\n";
      }
      if ($stats['expiring_soon'] > 0) {
         $text .= "├ 🟠 نزدیک انقضا: {$stats['expiring_soon']}\n";
      }
      if ($stats['valid'] > 0) {
         $text .= "├ 🟢 معتبر: {$stats['valid']}\n";
      }
      if ($stats['no_expire'] > 0) {
         $text .= "└ ⚪ بدون انقضا: {$stats['no_expire']}\n";
      }

      // اسناد بحرانی
      if ($stats['expired'] > 0 || $stats['expiring_soon'] > 0) {
         $text .= "\n⚠️ <b>نیاز به توجه فوری:</b>\n";

         // نمایش 3 سند منقضی شده
         if ($stats['expired'] > 0) {
            $stmt = $pdo->prepare("
               SELECT name, DATEDIFF(CURDATE(), expire_date) as days_expired 
               FROM documents 
               WHERE expire_date <= CURDATE() 
               ORDER BY expire_date ASC LIMIT 3
            ");
            $stmt->execute();
            $expired_docs = $stmt->fetchAll();

            foreach ($expired_docs as $doc) {
               $text .= "🔴 {$doc['name']} ({$doc['days_expired']} روز)\n";
            }
         }

         // نمایش 3 سند نزدیک انقضا
         if ($stats['expiring_soon'] > 0) {
            $stmt = $pdo->prepare("
               SELECT name, DATEDIFF(expire_date, CURDATE()) as days_left 
               FROM documents 
               WHEREexpire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               ORDER BY expire_date ASC LIMIT 3
            ");
            $stmt->execute();
            $expiring_docs = $stmt->fetchAll();

            foreach ($expiring_docs as $doc) {
               $text .= "🟠 {$doc['name']} ({$doc['days_left']} روز مانده)\n";
            }
         }
      }
   } else {
      $text .= "📭 هنوز سندی ثبت نکرده‌اید.\n\n";
      $text .= "💡 می‌توانید انواع اسناد مهم خود را ذخیره کنید:\n";
      $text .= "• کارت ملی، گذرنامه، شناسنامه\n";
      $text .= "• گواهینامه، کارت خودرو، بیمه‌نامه\n";
      $text .= "• مدارک تحصیلی، قراردادها\n";
      $text .= "• کارت‌های بانکی، اشتراک‌ها\n";
   }

   // if (!$user['is_premium'] && $stats['total'] >= MAX_FREE_DOCUMENTS - 2) {
   //    $remaining = MAX_FREE_DOCUMENTS - $stats['total'];
   //    $text .= "\n⚠️ <b>توجه:</b> $remaining سند دیگر می‌توانید ثبت کنید.";
   // }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '➕ سند جدید', 'callback_data' => 'doc_add'],
            ['text' => '📋 لیست اسناد' . " ({$stats['total']})", 'callback_data' => 'doc_list_1']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   deleteMessage($chat_id, $message_id);
   sendMessage($chat_id, $text, $keyboard);
}

function handleDocumentCallback($chat_id, $user_id, $data, $message_id)
{
   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'add':
         showAddDocumentForm($chat_id, $user_id, $message_id);
         break;
      case 'list':
         $page = $parts[2] ?? 1;
         showDocumentsList($chat_id, $user_id, $message_id, $page);
         break;
      case 'view':
         $doc_id = $parts[2] ?? 0;
         viewDocument($chat_id, $user_id, $doc_id, $message_id);
         break;
      case 'delete':
         $doc_id = $parts[2] ?? 0;
         confirmDeleteDocument($chat_id, $user_id, $doc_id, $message_id);
         break;
      case 'confirmdelete':
         $doc_id = $parts[2] ?? 0;
         deleteDocument($chat_id, $user_id, $doc_id, $message_id);
         break;
      case 'expire':
         deleteMessage($chat_id, $message_id);
         processDocumentForm($chat_id, $user_id, $data, 'waiting_document_expire');
         break;
   }
}

function showAddDocumentForm($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'waiting_document_name']);

   $text = "📄 <b>افزودن سند جدید</b>\n\n";
   $text .= "🏷 نام سند را وارد کنید:\n\n";
   $text .= "<b>نمونه‌های پرکاربرد:</b>\n";
   $text .= "├ • هویتی: کارت ملی، گذرنامه، شناسنامه\n";
   $text .= "├ • خودرو: گواهینامه، کارت خودرو، بیمه\n";
   $text .= "├ • تحصیلی: مدرک، ریزنمرات، گواهی\n";
   $text .= "└ • مالی: کارت بانکی، دسته چک، قرارداد\n\n";
   $text .= "💡 نام را کوتاه و گویا انتخاب کنید.";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'documents']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function showDocumentsList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 10; // 10 تا در هر صفحه برای تک ردیف
   $offset = ($page - 1) * $limit;

   // تبدیل به integer
   $limit = (int)$limit;
   $offset = (int)$offset;
   $page = (int)$page;

   $sql = "
      SELECT *, DATEDIFF(expire_date, CURDATE()) as days_left
      FROM documents 
      ORDER BY 
         CASE 
            WHEN expire_date IS NULL THEN 2
            WHEN expire_date <= CURDATE() THEN 0
            ELSE 1
         END,
         expire_date ASC 
      LIMIT $limit OFFSET $offset
   ";

   $stmt = $pdo->prepare($sql);
   $stmt->execute();
   $documents = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents");
   $stmt->execute();
   $total = $stmt->fetchColumn();

   if (empty($documents)) {
      $text = "📋 <b>لیست اسناد</b>\n\n";
      $text .= "📭 هیچ سندی ثبت نشده است.\n\n";
      $text .= "برای شروع، سند جدید اضافه کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '➕ سند جدید', 'callback_data' => 'doc_add']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'documents']]
         ]
      ];
   } else {
      $total_pages = ceil($total / $limit);

      $text = "📋 <b>لیست اسناد</b>\n\n";
      $text .= "📊 صفحه $page از $total_pages | مجموع: $total سند\n";
      $text .= "🔘 روی هر سند کلیک کنید تا جزئیات را ببینید";

      $keyboard = ['inline_keyboard' => []];

      // نمایش اسناد به صورت تک ردیف
      foreach ($documents as $index => $doc) {
         $status_icon = getDocumentStatusIcon($doc['days_left']);

         // محدود کردن نام به 25 کاراکتر برای تک ردیف
         $short_name = mb_strlen($doc['name']) > 25 ?
            mb_substr($doc['name'], 0, 25) . '...' :
            $doc['name'];

         $button_text = "$status_icon $short_name";

         // هر سند یک ردیف جداگانه
         $keyboard['inline_keyboard'][] = [
            ['text' => $button_text, 'callback_data' => 'doc_view_' . $doc['id']]
         ];
      }

      // صفحه‌بندی شیشه‌ای
      $nav_row = [];

      if ($page > 1) {
         $nav_row[] = ['text' => '⬅️ قبلی', 'callback_data' => 'doc_list_' . ($page - 1)];
      }

      if ($page < $total_pages) {
         $nav_row[] = ['text' => 'بعدی ➡️', 'callback_data' => 'doc_list_' . ($page + 1)];
      }

      if (!empty($nav_row)) {
         $keyboard['inline_keyboard'][] = $nav_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'documents']
      ];
   }

   deleteMessage($chat_id, $message_id);
   sendMessage($chat_id, $text, $keyboard);
}
function getDocumentStatusIcon($days_left)
{
   if ($days_left === null) {
      return '⚪';
   } elseif ($days_left < 0) {
      return '🔴';
   } elseif ($days_left <= 7) {
      return '🟠';
   } elseif ($days_left <= 30) {
      return '🟡';
   } else {
      return '🟢';
   }
}

function getFileTypeIcon($type)
{
   $icons = [
      'photo' => '🖼',
      'document' => '📎',
      'video' => '🎥',
      'audio' => '🎵'
   ];

   return $icons[$type] ?? '📎';
}

function processDocumentForm($chat_id, $user_id, $input, $step)
{
   global $pdo;

   $user = getUser($user_id);
   $temp_data = json_decode($user['temp_data'] ?? '{}', true);

   switch ($step) {
      case 'waiting_document_name':
         $temp_data['name'] = trim($input);
         updateUser($user_id, [
            'step' => 'waiting_document_expire',
            'temp_data' => json_encode($temp_data)
         ]);

         $text = "📅 <b>تاریخ انقضا را وارد کنید:</b>\n\n";
         $text .= "فرمت: 1404/12/29\n\n";
         $text .= "یا یکی از گزینه‌های زیر را انتخاب کنید:";

         $keyboard = [
            'inline_keyboard' => [
               // [
               //    ['text' => '📅 1 ماه دیگر', 'callback_data' => 'doc_expire_1month'],
               //    ['text' => '📅 3 ماه دیگر', 'callback_data' => 'doc_expire_3month']
               // ],
               // [
               //    ['text' => '📅 6 ماه دیگر', 'callback_data' => 'doc_expire_6month'],
               //    ['text' => '📅 1 سال دیگر', 'callback_data' => 'doc_expire_1year']
               // ],
               [
                  ['text' => '⏭ بدون انقضا', 'callback_data' => 'doc_expire_none']
               ],
               [
                  ['text' => '🔙 انصراف', 'callback_data' => 'documents']
               ]
            ]
         ];

         sendMessage($chat_id, $text, $keyboard);
         break;

      case 'waiting_document_expire':
         $expire_date = null;

         if (strpos($input, 'doc_expire_') === 0) {
            $option = str_replace('doc_expire_', '', $input);
            switch ($option) {
               // case '1month':
               //    $expire_date = date('Y-m-d', strtotime('+1 month'));
               //    break;
               // case '3month':
               //    $expire_date = date('Y-m-d', strtotime('+3 months'));
               //    break;
               // case '6month':
               //    $expire_date = date('Y-m-d', strtotime('+6 months'));
               //    break;
               // case '1year':
               //    $expire_date = date('Y-m-d', strtotime('+1 year'));
               //    break;
               case 'none':
                  $expire_date = null;
                  break;
            }
         } else {
            $expire_date = validatePersianDate($input);
            if ($expire_date === false) {
               sendMessage($chat_id, "❌ تاریخ نامعتبر! لطفاً به فرمت 1404/12/29 وارد کنید.");
               return;
            }
         }

         $temp_data['expire_date'] = $expire_date;
         updateUser($user_id, [
            'step' => 'waiting_document_file',
            'temp_data' => json_encode($temp_data)
         ]);

         $text = "📎 <b>فایل سند را ارسال کنید:</b>\n\n";
         $text .= "می‌توانید انواع فایل ارسال کنید:\n";
         $text .= "• 🖼 عکس\n";
         $text .= "• 📄 PDF یا اسناد\n";
         $text .= "• 🎥 ویدیو\n";
         $text .= "• 🎵 صوت\n\n";
         $text .= "فایل خود را ارسال کنید...";

         $keyboard = [
            'inline_keyboard' => [
               [['text' => '🔙 انصراف', 'callback_data' => 'documents']]
            ]
         ];

         sendMessage($chat_id, $text, $keyboard);
         break;
   }
}
function saveDocumentFile($chat_id, $user_id, $message)
{
   global $pdo;

   $user = getUser($user_id);
   $temp_data = json_decode($user['temp_data'] ?? '{}', true);

   $file_id = null;
   $file_type = 'document';
   $file_size = null;

   if ($message) {
      // تشخیص نوع فایل
      if (isset($message['photo'])) {
         $photo = end($message['photo']);
         $file_id = $photo['file_id'];
         $file_type = 'photo';
         $file_size = $photo['file_size'] ?? null;
      } elseif (isset($message['document'])) {
         $file_id = $message['document']['file_id'];
         $file_type = 'document';
         $file_size = $message['document']['file_size'] ?? null;
      } elseif (isset($message['video'])) {
         $file_id = $message['video']['file_id'];
         $file_type = 'video';
         $file_size = $message['video']['file_size'] ?? null;
      } elseif (isset($message['audio'])) {
         $file_id = $message['audio']['file_id'];
         $file_type = 'audio';
         $file_size = $message['audio']['file_size'] ?? null;
      }
   }

   // ذخیره سند
   $stmt = $pdo->prepare("
      INSERT INTO documents (name, expire_date, file_id, file_type, file_size, created_at) 
      VALUES (:name, :expire_date, :file_id, :file_type, :file_size, NOW())
   ");
   $stmt->bindValue(':name', $temp_data['name'], PDO::PARAM_STR);
   $stmt->bindValue(':expire_date', $temp_data['expire_date'], PDO::PARAM_STR);
   $stmt->bindValue(':file_id', $file_id, PDO::PARAM_STR);
   $stmt->bindValue(':file_type', $file_type, PDO::PARAM_STR);
   $stmt->bindValue(':file_size', $file_size, PDO::PARAM_INT);

   if ($stmt->execute()) {
      updateUser($user_id, ['step' => 'completed', 'temp_data' => null]);

      $text = "✅ <b>سند با موفقیت ذخیره شد!</b>\n\n";
      $text .= "📄 عنوان: {$temp_data['name']}\n";

      if ($temp_data['expire_date']) {
         $expire_persian = jdate('Y/m/d', strtotime($temp_data['expire_date']));
         $days_left = ceil((strtotime($temp_data['expire_date']) - time()) / (24 * 3600));
         $text .= "📅 انقضا: $expire_persian ($days_left روز مانده)\n";
      } else {
         $text .= "⚪ بدون تاریخ انقضا\n";
      }

      if ($file_id) {
         $file_icon = getFileTypeIcon($file_type);
         $text .= "$file_icon فایل ضمیمه شد\n";
      }

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '➕ سند جدید', 'callback_data' => 'doc_add'],
               ['text' => '📋 لیست اسناد', 'callback_data' => 'doc_list_1']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $text, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در ذخیره سند. لطفاً دوباره تلاش کنید.");
   }
}

function viewDocument($chat_id, $user_id, $doc_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT *, DATEDIFF(expire_date, CURDATE()) as days_left FROM documents WHERE id = :id");
   $stmt->bindValue(':id', $doc_id, PDO::PARAM_INT);
   $stmt->execute();
   $doc = $stmt->fetch();

   if (!$doc) {
      editMessage($chat_id, $message_id, "❌ سند یافت نشد.");
      return;
   }

   $text = "📄 <b>جزئیات سند {$doc['name']}</b>\n";
   $text .= "━━━━━━━━━━━━━━━\n\n";

   if ($doc['expire_date']) {
      $expire_date = jdate('Y/m/d', strtotime($doc['expire_date']));
      $days_left = $doc['days_left'];

      $text .= "📅 <b>تاریخ انقضا:</b> $expire_date\n";

      if ($days_left < 0) {
         $text .= "🔴 <b>وضعیت:</b> منقضی شده (" . abs($days_left) . " روز پیش)\n";
      } elseif ($days_left == 0) {
         $text .= "🟠 <b>وضعیت:</b> امروز منقضی می‌شود!\n";
      } elseif ($days_left <= 7) {
         $text .= "🟡 <b>وضعیت:</b> $days_left روز تا انقضا ⚠️\n";
      } elseif ($days_left <= 30) {
         $text .= "🟡 <b>وضعیت:</b> نزدیک انقضا ($days_left روز مانده)\n";
      } else {
         $text .= "🟢 <b>وضعیت:</b> معتبر ($days_left روز مانده)\n";
      }
   } else {
      $text .= "⚪ <b>وضعیت:</b> بدون تاریخ انقضا\n";
   }

   if ($doc['description']) {
      $text .= "\n📝 <b>توضیحات:</b>\n{$doc['description']}\n";
   }

   $created_date = jdate('Y/m/d H:i', strtotime($doc['created_at']));
   $text .= "\n📆 <b>تاریخ ثبت:</b> $created_date";

   if ($doc['file_id']) {
      $file_icon = getFileTypeIcon($doc['file_type']);
      $file_size_mb = $doc['file_size'] ? round($doc['file_size'] / 1024 / 1024, 2) : 0;
      $text .= "\n$file_icon <b>فایل:</b> ضمیمه شده";
      if ($file_size_mb > 0) {
         $text .= " ({$file_size_mb} MB)";
      }
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '🗑 حذف', 'callback_data' => 'doc_delete_' . $doc['id']],
            ['text' => '📋 لیست', 'callback_data' => 'doc_list_1']
         ],
         [
            ['text' => '🔙 بازگشت', 'callback_data' => 'documents']
         ]
      ]
   ];

   // ارسال فایل در صورت وجود
   if ($doc['file_id']) {
      deleteMessage($chat_id, $message_id);

      // ارسال فایل با کپشن
      $data = [
         'chat_id' => $chat_id,
         'caption' => $text,
         'parse_mode' => 'HTML',
         'reply_markup' => json_encode($keyboard)
      ];

      switch ($doc['file_type']) {
         case 'photo':
            $data['photo'] = $doc['file_id'];
            makeRequest('sendPhoto', $data);
            break;
         case 'video':
            $data['video'] = $doc['file_id'];
            makeRequest('sendVideo', $data);
            break;
         case 'audio':
            $data['audio'] = $doc['file_id'];
            makeRequest('sendAudio', $data);
            break;
         default:
            $data['document'] = $doc['file_id'];
            makeRequest('sendDocument', $data);
      }
   } else {
      editMessage($chat_id, $message_id, $text, $keyboard);
   }
}

function confirmDeleteDocument($chat_id, $user_id, $doc_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT name FROM documents WHERE id = :id");
   $stmt->bindValue(':id', $doc_id, PDO::PARAM_INT);
   $stmt->execute();
   $doc = $stmt->fetch();

   if (!$doc) {
      editMessage($chat_id, $message_id, "❌ سند یافت نشد.");
      return;
   }

   $text = "⚠️ <b>تایید حذف</b>\n\n";
   $text .= "آیا از حذف سند زیر اطمینان دارید؟\n\n";
   $text .= "📄 " . htmlspecialchars($doc['name']);

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف شود', 'callback_data' => 'doc_confirmdelete_' . $doc_id],
            ['text' => '❌ خیر', 'callback_data' => 'doc_view_' . $doc_id]
         ]
      ]
   ];

   deleteMessage($chat_id, $message_id);
   sendMessage($chat_id, $text, $keyboard);
}

function deleteDocument($chat_id, $user_id, $doc_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("DELETE FROM documents WHERE id = :id");
   $stmt->bindValue(':id', $doc_id, PDO::PARAM_INT);
   if ($stmt->execute()) {
      $text = "✅ سند با موفقیت حذف شد.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📋 لیست اسناد', 'callback_data' => 'doc_list_1'],
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      editMessage($chat_id, $message_id, $text, $keyboard);
   } else {
      editMessage($chat_id, $message_id, "❌ خطا در حذف سند.");
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

