<?php

function showNotesMenu($chat_id, $user_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes");
   
   $stmt->execute();
   $count = $stmt->fetchColumn();

   $text = "📒 <b>مدیریت یادداشت‌ها</b>\n\n";

   $text .= "<b>امکانات:</b>\n";
   $text .= "• ذخیره سریع با ارسال متن\n";
   $text .= "• مشاهده و مدیریت یادداشت‌ها\n";
   $text .= "• حذف و ویرایش آسان\n\n";

   $text .= "<b>نمونه‌های یادداشت:</b>\n";
   $text .= "• ایده جالب برای پروژه جدید\n";
   $text .= "• لیست خرید: نان، شیر، تخم مرغ\n";
   $text .= "• یادداشت جلسه فردا ساعت 10\n";
   $text .= "• شماره تلفن مشتری مهم\n";
   $text .= "• کد تخفیف فروشگاه آنلاین\n\n";

   $text .= "💡 هر متنی که ارسال کنید به عنوان یادداشت ذخیره می‌شود!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '📋 مشاهده یادداشت‌ها' . " ($count)", 'callback_data' => 'note_list']
         ],
         [
            ['text' => '🔍 جستجوی', 'callback_data' => 'note_search']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function handleNoteCallback($chat_id, $user_id, $data, $message_id)
{
   $parts = explode('_', $data);
   $action = $parts[1] ?? '';

   switch ($action) {
      case 'list':
         $page = $parts[2] ?? 1;
         showNotesList($chat_id, $user_id, $message_id, $page);
         break;
      case 'view':
         $note_id = $parts[2] ?? 0;
         viewNote($chat_id, $user_id, $note_id, $message_id);
         break;
      case 'delete':
         $note_id = $parts[2] ?? 0;
         confirmDeleteNote($chat_id, $user_id, $note_id, $message_id);
         break;
      case 'confirmdelete':
         $note_id = $parts[2] ?? 0;
         deleteNote($chat_id, $user_id, $note_id, $message_id);
         break;
      case 'edit':
         $note_id = $parts[2] ?? 0;
         startNoteEdit($chat_id, $user_id, $note_id, $message_id);
         break;
      case 'search':
         startNoteSearch($chat_id, $user_id, $message_id);
         break;
   }
}

function saveQuickNote($chat_id, $user_id, $text, $confirmed = false)
{
   global $message_id;

   // تولید عنوان خودکار
   $title = generateNoteTitle($text);

   // ذخیره یادداشت
   $note_id = saveNote($user_id, $title, $text);

   if ($note_id) {
      deleteMessage($chat_id, $message_id);

      $response = "✅ <b>یادداشت ذخیره شد!</b>\n\n";
      $response .= "📋 <b>عنوان:</b> " . htmlspecialchars($title) . "\n";
      $response .= "📝 <b>متن:</b> " . (mb_strlen($text) > 100 ? mb_substr($text, 0, 100) . '...' : $text) . "\n\n";
      $response .= "💡 برای مدیریت یادداشت‌ها از منوی ربات استفاده کنید.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '📋 مشاهده یادداشت‌ها', 'callback_data' => 'note_list'],
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در ذخیره یادداشت. لطفاً دوباره تلاش کنید.");
   }
}

function generateNoteTitle($text)
{
   // حذف فضاهای اضافی
   $clean_text = trim($text);

   // برگرداندن اولین 40 کاراکتر
   if (mb_strlen($clean_text) > 40) {
      return mb_substr($clean_text, 0, 37) . '...';
   }

   return $clean_text ?: 'یادداشت بدون عنوان';
}

function saveNote($user_id, $title, $content)
{
   global $pdo;

   $stmt = $pdo->prepare("INSERT INTO notes (title, content, created_at) VALUES (:title, :content, NOW())");
   $stmt->bindValue(':title', $title, PDO::PARAM_STR);
   $stmt->bindValue(':content', $content, PDO::PARAM_STR);

   if ($stmt->execute()) {
      return $pdo->lastInsertId();
   }

   return false;
}

function showNotesList($chat_id, $user_id, $message_id, $page = 1)
{
   global $pdo;

   $limit = 6;
   $offset = ($page - 1) * $limit;

   // اصلاح کوئری SQL
   $stmt = $pdo->prepare("SELECT * FROM notes ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
   
   $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
   $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
   $stmt->execute();
   $notes = $stmt->fetchAll();

   $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes");
   
   $stmt->execute();
   $total = $stmt->fetchColumn();

   if (empty($notes)) {
      $text = "📋 <b>لیست یادداشت‌ها</b>\n\n";
      $text .= "❌ هیچ یادداشتی ندارید.\n\n";
      $text .= "برای افزودن یادداشت جدید، متن خود را ارسال کنید.\n\n";
      $text .= "<b>نمونه:</b>\n";
      $text .= "• خرید نان و شیر از فروشگاه\n";
      $text .= "• شماره تلفن دکتر: 09123456789\n";
      $text .= "• ایده پروژه جدید برای شرکت";

      $keyboard = [
         'inline_keyboard' => [
            [['text' => '🔙 بازگشت', 'callback_data' => 'notes']]
         ]
      ];
   } else {
      $text = "📋 <b>یادداشت‌های شما</b>\n";
      $text .= "صفحه $page از " . ceil($total / $limit) . " • مجموع: $total یادداشت\n\n";
      $text .= "برای مشاهده جزئیات، روی یادداشت کلیک کنید:";

      $keyboard = [
         'inline_keyboard' => []
      ];

      // نمایش یادداشت‌ها به صورت دکمه‌های شیشه‌ای تک‌ردیفه
      foreach ($notes as $note) {
         $date = jdate('m/d H:i', strtotime($note['created_at']));
         $title = mb_strlen($note['title']) > 20 ? mb_substr($note['title'], 0, 20) . '...' : $note['title'];

         $button_text = "📝 $title • $date";

         // هر یادداشت یک ردیف کامل
         $keyboard['inline_keyboard'][] = [
            [
               'text' => $button_text,
               'callback_data' => 'note_view_' . $note['id']
            ]
         ];
      }

      // صفحه‌بندی شیشه‌ای
      $total_pages = ceil($total / $limit);
      if ($total_pages > 1) {
         $pagination_row = [];

         // دکمه قبلی
         if ($page > 1) {
            $pagination_row[] = [
               'text' => '⬅️',
               'callback_data' => 'note_list_' . ($page - 1)
            ];
         }

         // دکمه‌های شماره صفحه (حداکثر 5 صفحه نمایش)
         $start_page = max(1, $page - 2);
         $end_page = min($total_pages, $start_page + 4);

         for ($i = $start_page; $i <= $end_page; $i++) {
            $page_text = ($i == $page) ? "[$i]" : "$i";
            $pagination_row[] = [
               'text' => $page_text,
               'callback_data' => 'note_list_' . $i
            ];
         }

         // دکمه بعدی
         if ($page < $total_pages) {
            $pagination_row[] = [
               'text' => '➡️',
               'callback_data' => 'note_list_' . ($page + 1)
            ];
         }

         $keyboard['inline_keyboard'][] = $pagination_row;
      }

      // دکمه بازگشت
      $keyboard['inline_keyboard'][] = [
         ['text' => '🔙 بازگشت', 'callback_data' => 'notes']
      ];
   }

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function viewNote($chat_id, $user_id, $note_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = :id");
   $stmt->bindValue(':id', $note_id, PDO::PARAM_INT);
   
   $stmt->execute();
   $note = $stmt->fetch();

   if (!$note) {
      editMessage($chat_id, $message_id, "❌ یادداشت یافت نشد.");
      return;
   }

   $created_date = jdate('Y/m/d H:i', strtotime($note['created_at']));
   $updated_date = $note['updated_at'] ? jdate('Y/m/d H:i', strtotime($note['updated_at'])) : null;

   $text = "📝 <b>جزئیات یادداشت</b>\n\n";
   $text .= "📋 <b>عنوان:</b> " . htmlspecialchars($note['title']) . "\n\n";
   $text .= "📄 <b>متن:</b>\n" . htmlspecialchars($note['content']) . "\n\n";
   $text .= "📅 <b>ایجاد:</b> $created_date\n";

   if ($updated_date) {
      $text .= "🔄 <b>آخرین ویرایش:</b> $updated_date\n";
   }

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✏️ ویرایش', 'callback_data' => 'note_edit_' . $note_id],
            ['text' => '🗑 حذف', 'callback_data' => 'note_delete_' . $note_id]
         ],
         [
            ['text' => '📋 بازگشت به لیست', 'callback_data' => 'note_list_1']
         ],
         [
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function confirmDeleteNote($chat_id, $user_id, $note_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT title FROM notes WHERE id = :id");
   $stmt->bindValue(':id', $note_id, PDO::PARAM_INT);
   
   $stmt->execute();
   $note = $stmt->fetch();

   if (!$note) {
      editMessage($chat_id, $message_id, "❌ یادداشت یافت نشد.");
      return;
   }

   $text = "🗑 <b>حذف یادداشت</b>\n\n";
   $text .= "آیا مطمئن هستید که می‌خواهید این یادداشت را حذف کنید؟\n\n";
   $text .= "📋 <b>عنوان:</b> " . htmlspecialchars($note['title']) . "\n\n";
   $text .= "⚠️ این عمل قابل بازگشت نیست!";

   $keyboard = [
      'inline_keyboard' => [
         [
            ['text' => '✅ بله، حذف کن', 'callback_data' => 'note_confirmdelete_' . $note_id],
            ['text' => '❌ خیر', 'callback_data' => 'note_view_' . $note_id]
         ]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function deleteNote($chat_id, $user_id, $note_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("DELETE FROM notes WHERE id = :id");
   $stmt->bindValue(':id', $note_id, PDO::PARAM_INT);
   

   if ($stmt->execute()) {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "🗑 یادداشت حذف شد");
      showNotesList($chat_id, $user_id, $message_id, 1);
   } else {
      global $callback_query_id;
      answerCallbackQuery($callback_query_id, "❌ خطا در حذف یادداشت");
   }
}

function startNoteEdit($chat_id, $user_id, $note_id, $message_id)
{
   global $pdo;

   $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = :id");
   $stmt->bindValue(':id', $note_id, PDO::PARAM_INT);
   
   $stmt->execute();
   $note = $stmt->fetch();

   if (!$note) {
      editMessage($chat_id, $message_id, "❌ یادداشت یافت نشد.");
      return;
   }

   updateUser($user_id, ['step' => 'editing_note_' . $note_id]);

   $text = "✏️ <b>ویرایش یادداشت</b>\n\n";
   $text .= "📋 <b>عنوان فعلی:</b> " . htmlspecialchars($note['title']) . "\n\n";
   $text .= "📄 <b>متن فعلی:</b>\n" . htmlspecialchars($note['content']) . "\n\n";
   $text .= "💡 متن جدید یادداشت را ارسال کنید:";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 بازگشت', 'callback_data' => 'note_view_' . $note_id]]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function updateNote($chat_id, $user_id, $note_id, $new_content)
{
   global $pdo;

   $stmt = $pdo->prepare("UPDATE notes SET content = :content, updated_at = NOW() WHERE id = :id");
   $stmt->bindValue(':content', $new_content, PDO::PARAM_STR);
   $stmt->bindValue(':id', $note_id, PDO::PARAM_INT);
   

   if ($stmt->execute()) {
      updateUser($user_id, ['step' => 'completed']);

      $response = "✅ <b>یادداشت بروزرسانی شد!</b>\n\n";
      $response .= "📝 <b>متن جدید:</b> " . (mb_strlen($new_content) > 100 ? mb_substr($new_content, 0, 100) . '...' : $new_content);

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '👁 مشاهده یادداشت', 'callback_data' => 'note_view_' . $note_id],
               ['text' => '📋 لیست یادداشت‌ها', 'callback_data' => 'note_list_1']
            ],
            [
               ['text' => '🏠 منوی اصلی', 'callback_data' => 'back_main']
            ]
         ]
      ];

      sendMessage($chat_id, $response, $keyboard);
   } else {
      sendMessage($chat_id, "❌ خطا در بروزرسانی یادداشت.");
   }
}

function startNoteSearch($chat_id, $user_id, $message_id)
{
   updateUser($user_id, ['step' => 'searching_notes']);

   $text = "🔍 <b>جستجوی یادداشت‌ها</b>\n\n";
   $text .= "کلمه یا عبارت مورد نظر را وارد کنید:\n\n";
   $text .= "💡 نکات:\n";
   $text .= "• برای جستجوی دقیق از \" \" استفاده کنید\n";
   $text .= "• می‌توانید چند کلمه وارد کنید\n";
   $text .= "• از # برای جستجوی تگ استفاده کنید";

   $keyboard = [
      'inline_keyboard' => [
         [['text' => '🔙 انصراف', 'callback_data' => 'notes']]
      ]
   ];

   editMessage($chat_id, $message_id, $text, $keyboard);
}

function searchNotes($chat_id, $user_id, $query)
{
   global $pdo;

   // جستجوی هوشمند با FULLTEXT
   $stmt = $pdo->prepare("
      SELECT *, 
             MATCH(content) AGAINST(:query1 IN NATURAL LANGUAGE MODE) as relevance
      FROM notes 
      AND (
         MATCH(content) AGAINST(:query2 IN NATURAL LANGUAGE MODE) 
         OR content LIKE :search_term
      )
      ORDER BY relevance DESC, created_at DESC
      LIMIT 10
   ");

   $search_term = "%$query%";
   $stmt->bindValue(':query1', $query, PDO::PARAM_STR);
   
   $stmt->bindValue(':query2', $query, PDO::PARAM_STR);
   $stmt->bindValue(':search_term', $search_term, PDO::PARAM_STR);
   $stmt->execute();
   $results = $stmt->fetchAll();

   if (empty($results)) {
      $text = "🔍 <b>نتیجه جستجو</b>\n\n";
      $text .= "❌ یادداشتی با این مشخصات یافت نشد.";

      $keyboard = [
         'inline_keyboard' => [
            [
               ['text' => '🔍 جستجوی مجدد', 'callback_data' => 'note_search'],
               ['text' => '🔙 بازگشت', 'callback_data' => 'notes']
            ]
         ]
      ];
   } else {
      $text = "🔍 <b>نتایج جستجو</b>\n";
      $text .= "تعداد: " . count($results) . " یادداشت\n";
      $text .= "━━━━━━━━━━━━━━━\n\n";

      $keyboard = ['inline_keyboard' => []];
      $buttons_row = [];

      foreach ($results as $index => $note) {
         $num = $index + 1;
         $preview = mb_substr(strip_tags($note['content']), 0, 50);
         $created = jdate('Y/m/d', strtotime($note['created_at']));

         // هایلایت کلمه جستجو
         $highlighted = str_ireplace($query, "<b>$query</b>", $preview);

         $text .= "$num. 📝 $highlighted...\n";
         $text .= "   📅 $created\n\n";

         $buttons_row[] = [
            'text' => "$num 👁",
            'callback_data' => 'note_view_' . $note['id']
         ];

         if (count($buttons_row) >= 3) {
            $keyboard['inline_keyboard'][] = $buttons_row;
            $buttons_row = [];
         }
      }

      if (!empty($buttons_row)) {
         $keyboard['inline_keyboard'][] = $buttons_row;
      }

      $keyboard['inline_keyboard'][] = [
         ['text' => '🔍 جستجوی مجدد', 'callback_data' => 'note_search'],
         ['text' => '🔙 بازگشت', 'callback_data' => 'notes']
      ];
   }

   updateUser($user_id, ['step' => 'completed']);
   sendMessage($chat_id, $text, $keyboard);
}

