<?php

/**
 * Normalize Iranian mobile numbers to the 09xxxxxxxxx format.
 * Accepts inputs like:
 *  - 09123456789
 *  - +989123456789
 *  - 989123456789
 *  - 00989123456789
 * Also tolerates spaces, dashes, parentheses etc.
 *
 * @param string $input
 * @return string|false Normalized phone (09...) or false if invalid
 */
function normalizeIranPhone($input)
{
   $digits = preg_replace('/\D+/', '', $input);

   if ($digits === '') {
      return false;
   }

   if (strpos($digits, '00') === 0) {
      $digits = preg_replace('/^00+/', '', $digits);
   }

   if (strpos($digits, '98') === 0) {
      $digits = substr($digits, 2);
   }

   if (strlen($digits) === 10 && $digits[0] === '9') {
      $digits = '0' . $digits; // becomes 11 digits
   }

   if (preg_match('/^09\d{9}$/', $digits)) {
      return $digits;
   }

   return false;
}

function validatePersianDate($date_str)
{
   if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date_str, $matches)) {
      $year = intval($matches[1]);
      $month = intval($matches[2]);
      $day = intval($matches[3]);

      if ($year >= 1300 && $year <= 1500 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
         // استفاده از تابع jalali_to_gregorian از jdf.php
         list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
         return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
      }
   }

   return false;
}

function convertPersianToEnglish($text)
{
   $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
   $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
   return str_replace($persian, $english, $text);
}

function extractTimeFromText($text, &$date_changed = false)
{
   $now = time();
   $current_time = $now;
   $current_hour = (int)date('H');
   $current_minute = (int)date('i');

   // تبدیل اعداد فارسی به انگلیسی
   $text = convertPersianToEnglish($text);

   // 🆕 اول: بخش‌های روز که ممکنه جلو یا عقب ساعت باشن
   // مثال: "امشب ساعت 8" یا "ساعت 8 شب"
   if (
      preg_match('/(صبح|ظهر|غروب|عصر|شب|امشب)\s*(ساعت\s*)?(\d{1,2}):?(\d{0,2})?/u', $text, $matches) ||
      preg_match('/(ساعت\s*)?(\d{1,2}):?(\d{0,2})?\s*(صبح|ظهر|غروب|عصر|شب)/u', $text, $matches2)
   ) {

      // تنظیم matches بر اساس اینکه کدوم pattern match شده
      if (!empty($matches[3])) {
         $period = $matches[1] == 'امشب' ? 'شب' : $matches[1];
         $hour = (int)$matches[3];
         $minute = (int)($matches[4] ?: '00');
      } else {
         $period = $matches2[4];
         $hour = (int)$matches2[2];
         $minute = (int)($matches2[3] ?: '00');
      }

      // تبدیل ساعت بر اساس بخش روز
      switch ($period) {
         case 'صبح':
            if ($hour >= 1 && $hour <= 11) {
               $final_hour = $hour;
            } else {
               $final_hour = 8;
            }
            break;

         case 'ظهر':
            if ($hour >= 12 && $hour <= 14) {
               $final_hour = $hour;
            } else if ($hour >= 1 && $hour <= 2) {
               $final_hour = $hour + 12;
            } else {
               $final_hour = 12;
            }
            break;

         case 'عصر':
            if ($hour >= 15 && $hour <= 18) {
               $final_hour = $hour;
            } else if ($hour >= 3 && $hour <= 6) {
               $final_hour = $hour + 12;
            } else {
               $final_hour = 16;
            }
            break;

         case 'غروب':
            $final_hour = 18;
            $minute = 0;
            break;

         case 'شب':
            if ($hour >= 19 && $hour <= 23) {
               $final_hour = $hour;
            } else if ($hour >= 1 && $hour <= 11) {
               $final_hour = $hour + 12; // 8 شب = 20
            } else if ($hour == 12) {
               $final_hour = 0; // 12 شب = نیمه شب
            } else {
               $final_hour = 20;
            }
            break;
      }

      $final_hour = (int)$final_hour;
      $final_minute = (int)$minute;

      // چک کردن اینکه آیا زمان گذشته است
      if (
         $final_hour < $current_hour ||
         ($final_hour == $current_hour && $final_minute <= $current_minute)
      ) {
         $date_changed = true;
      }

      return sprintf('%02d:%02d', $final_hour, $final_minute);
   }

   // 🆕 فقط بخش‌های روز بدون ساعت مشخص
   if (preg_match('/\b(صبح|ظهر|غروب|عصر|شب)\b/u', $text, $matches)) {
      $period = $matches[1];

      // ساعت‌های پیش‌فرض
      switch ($period) {
         case 'صبح':
            $final_hour = 8;
            $final_minute = 0;
            break;
         case 'ظهر':
            $final_hour = 12;
            $final_minute = 0;
            break;
         case 'عصر':
            $final_hour = 16;
            $final_minute = 0;
            break;
         case 'غروب':
            $final_hour = 18;
            $final_minute = 0;
            break;
         case 'شب':
            $final_hour = 20;
            $final_minute = 0;
            break;
      }

      // ✅ چک کردن اینکه آیا زمان گذشته است
      if (
         $final_hour < $current_hour ||
         ($final_hour == $current_hour && $final_minute <= $current_minute)
      ) {
         $date_changed = true;
      }

      return sprintf('%02d:%02d', $final_hour, $final_minute);
   }

   // 🔹 اول: ترکیبی (یک ساعت و نیم / یک ساعت نیم / بک ساعت نیم)
   if (preg_match('/(یک|بک|1)\s*ساعت\s*(و\s*)?نیم\s*(دیگه|دیگر|بعد)?/u', $text)) {
      $target_time = $current_time + (90 * 60); // 1.5 ساعت
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // نیم ساعت
   if (preg_match('/(نیم\s*ساعت|نصف\s*ساعت)\s*(دیگه|دیگر|بعد)/u', $text)) {
      $target_time = $current_time + (30 * 60);
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // یک ساعت
   if (preg_match('/(یک|بک|1)\s*ساعت\s*(دیگه|دیگر|بعد)/u', $text)) {
      $target_time = $current_time + (60 * 60);
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // یک روز
   if (preg_match('/(یک|بک|1)\s*روز\s*(دیگه|دیگر|بعد)/u', $text)) {
      $target_time = $current_time + (24 * 60 * 60);
      $date_changed = true; // حتماً تاریخ عوض میشه
      return date('H:i', $target_time);
   }

   // ربع ساعت
   if (preg_match('/(ربع\s*ساعت|ربعساعت|(یک|بک)\s*ربع)\s*(دیگه|دیگر|بعد)/u', $text)) {
      $target_time = $current_time + (15 * 60);
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // سه ربع ساعت
   if (preg_match('/(سه\s*ربع|سه‌ربع|3\s*ربع)\s*(دیگه|دیگر|بعد)/u', $text)) {
      $target_time = $current_time + (45 * 60);
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // دقیقه
   if (preg_match('/(\d+|نیم|یک|بک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده)\s*(دقیقه|دیقه|دیقع)\s*(دیگه|دیگر|بعد)/u', $text, $matches)) {
      $minutes = convertWordToNumber($matches[1]);
      if ($matches[1] == 'نیم') $minutes = 30;
      if ($matches[1] == 'بک') $minutes = 1;
      $target_time = $current_time + ($minutes * 60);
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // ساعت
   if (preg_match('/(\d+|نیم|یک|بک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده)\s*(ساعت|سعت)\s*(دیگه|دیگر|بعد)/u', $text, $matches)) {
      $hours = convertWordToNumber($matches[1]);
      if ($matches[1] == 'نیم') $hours = 0.5;
      if ($matches[1] == 'بک') $hours = 1;
      $target_time = $current_time + ($hours * 3600);
      if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
         $date_changed = true;
      }
      return date('H:i', $target_time);
   }

   // ساعت مشخص (ساعت 14 یا 14:30)
   if (preg_match('/ساعت\s*(\d{1,2}):?(\d{0,2})/u', $text, $matches)) {
      $hour = sprintf('%02d', $matches[1]);
      $minute = sprintf('%02d', $matches[2] ?: '00');

      // چک کنیم اگه این ساعت از الان کمتره، فردا باشه
      if (
         $hour < $current_hour ||
         ($hour == $current_hour && $minute <= $current_minute)
      ) {
         $date_changed = true;
      }

      return "$hour:$minute";
   }

   // زمان مشخص بدون کلمه ساعت (14:30)
   if (preg_match('/(\d{1,2}):(\d{2})/u', $text, $matches)) {
      $hour = sprintf('%02d', $matches[1]);
      $minute = sprintf('%02d', $matches[2]);

      // چک کنیم اگه این ساعت از الان کمتره، فردا باشه
      if (
         $hour < $current_hour ||
         ($hour == $current_hour && $minute <= $current_minute)
      ) {
         $date_changed = true;
      }

      return "$hour:$minute";
   }

   // اگر هیچ چیز پیدا نشد → +1 دقیقه
   $target_time = $current_time + 60;
   if (date('Y-m-d', $target_time) != date('Y-m-d', $current_time)) {
      $date_changed = true;
   }
   return date('H:i', $target_time);
}

function extractDateFromText($text, $date_changed = false)
{
   require_once 'jdf.php';

   $now = time();
   $today = date('Y-m-d');

   // تبدیل اعداد فارسی به انگلیسی
   $text = convertPersianToEnglish($text);

   // اگر از تابع زمان مشخص شده که تاریخ عوض شده
   if ($date_changed) {
      return date('Y-m-d', strtotime('+1 day'));
   }

   // 🆕 تکراری ماهانه - روز مشخص هر ماه (باید اول باشه تا "20 ام هر ماه" رو بگیره)
   if (
      preg_match('/(هر\s*ماه|هرماه)\s*(یکم|دوم|سوم|چهارم|پنجم|ششم|هفتم|هشتم|نهم|دهم|یازدهم|دوازدهم|سیزدهم|چهاردهم|پانزدهم|شانزدهم|هفدهم|هجدهم|نوزدهم|بیستم|بیست\s*و\s*یکم|بیست\s*و\s*دوم|بیست\s*و\s*سوم|بیست\s*و\s*چهارم|بیست\s*و\s*پنجم|بیست\s*و\s*ششم|بیست\s*و\s*هفتم|بیست\s*و\s*هشتم|بیست\s*و\s*نهم|سی‌ام|سی\s*و\s*یکم|یک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده|\d+)\s*(ام|م|اُم)?/u', $text, $matches) ||
      preg_match('/(یکم|دوم|سوم|چهارم|پنجم|ششم|هفتم|هشتم|نهم|دهم|یازدهم|دوازدهم|سیزدهم|چهاردهم|پانزدهم|شانزدهم|هفدهم|هجدهم|نوزدهم|بیستم|بیست\s*و\s*یکم|بیست\s*و\s*دوم|بیست\s*و\s*سوم|بیست\s*و\s*چهارم|بیست\s*و\s*پنجم|بیست\s*و\s*ششم|بیست\s*و\s*هفتم|بیست\s*و\s*هشتم|بیست\s*و\s*نهم|سی‌ام|سی\s*و\s*یکم|یک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده|\d+)\s*(ام|م|اُم)?\s*(هر\s*ماه|هرماه)/u', $text, $matches2)
   ) {

      // تنظیم day_text بر اساس اینکه کدوم pattern match شده
      if (!empty($matches[2])) {
         $day_text = trim($matches[2]);
      } else {
         $day_text = trim($matches2[1]);
      }

      // تبدیل کلمات به عدد
      $day_numbers = [
         'یکم' => 1,
         'اول' => 1,
         'دوم' => 2,
         'سوم' => 3,
         'چهارم' => 4,
         'پنجم' => 5,
         'ششم' => 6,
         'هفتم' => 7,
         'هشتم' => 8,
         'نهم' => 9,
         'دهم' => 10,
         'یازدهم' => 11,
         'دوازدهم' => 12,
         'سیزدهم' => 13,
         'چهاردهم' => 14,
         'پانزدهم' => 15,
         'شانزدهم' => 16,
         'هفدهم' => 17,
         'هجدهم' => 18,
         'نوزدهم' => 19,
         'بیستم' => 20,
         'بیست و یکم' => 21,
         'بیست و دوم' => 22,
         'بیست و سوم' => 23,
         'بیست و چهارم' => 24,
         'بیست و پنجم' => 25,
         'بیست و ششم' => 26,
         'بیست و هفتم' => 27,
         'بیست و هشتم' => 28,
         'بیست و نهم' => 29,
         'سی‌ام' => 30,
         'سی ام' => 30,
         'سی و یکم' => 31,
         // اعداد ساده
         'یک' => 1,
         'دو' => 2,
         'سه' => 3,
         'چهار' => 4,
         'پنج' => 5,
         'شش' => 6,
         'هفت' => 7,
         'هشت' => 8,
         'نه' => 9,
         'ده' => 10,
         'بیست' => 20,
         'سی' => 30
      ];

      $target_day = null;

      // اول چک کنیم کلمه باشه
      if (isset($day_numbers[$day_text])) {
         $target_day = $day_numbers[$day_text];
      }
      // اگر کلمه نبود، شاید عدد باشه
      else if (is_numeric($day_text)) {
         $target_day = (int)$day_text;
      }

      if ($target_day !== null && $target_day >= 1 && $target_day <= 31) {
         $current_persian_year = jdate('Y');
         $current_persian_month = jdate('n');
         $current_persian_day = jdate('j');

         // برای تکرار ماهانه، همیشه ماه آینده
         $target_month = $current_persian_month + 1;
         $target_year = $current_persian_year;

         if ($target_month > 12) {
            $target_month = 1;
            $target_year++;
         }

         // چک کنیم که روز هدف در ماه هدف وجود داشته باشه
         $days_in_target_month = ($target_month <= 6) ? 31 : (($target_month <= 11) ? 30 : (isLeapYear($target_year) ? 30 : 29));

         if ($target_day <= $days_in_target_month) {
            list($gy, $gm, $gd) = jalali_to_gregorian($target_year, $target_month, $target_day);
            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
         }
      }
   }

   // 🆕 تکراری ماهانه - آخر هر ماه (باید قبل از "پنج ام ماه" باشه)
   if (preg_match('/(آخر|انتهای?)\s*(هر\s*ماه|هرماه|ماه)/u', $text)) {
      $current_persian_year = jdate('Y');
      $current_persian_month = jdate('n');

      $target_month = $current_persian_month + 1;
      $target_year = $current_persian_year;

      if ($target_month > 12) {
         $target_month = 1;
         $target_year++;
      }

      // تعداد روزهای ماه هدف
      $days_in_month = ($target_month <= 6) ? 31 : (($target_month <= 11) ? 30 : (isLeapYear($target_year) ? 30 : 29));

      list($gy, $gm, $gd) = jalali_to_gregorian($target_year, $target_month, $days_in_month);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
   }

   // 🆕 روز مشخص ماه جاری (پنج ام ماه) - بدون "هر ماه"
   if (preg_match('/(یکم|دوم|سوم|چهارم|پنجم|ششم|هفتم|هشتم|نهم|دهم|یازدهم|دوازدهم|سیزدهم|چهاردهم|پانزدهم|شانزدهم|هفدهم|هجدهم|نوزدهم|بیستم|بیست\s*و\s*یکم|بیست\s*و\s*دوم|بیست\s*و\s*سوم|بیست\s*و\s*چهارم|بیست\s*و\s*پنجم|بیست\s*و\s*ششم|بیست\s*و\s*هفتم|بیست\s*و\s*هشتم|بیست\s*و\s*نهم|سی‌ام|سی\s*و\s*یکم|یک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده|\d+)\s*(ام|م|اُم)?\s*ماه(?!\s*(بعد|آینده|دیگه|دیگر))/u', $text, $matches)) {

      $day_text = trim($matches[1]);

      // تبدیل کلمات به عدد
      $day_numbers = [
         'یکم' => 1,
         'اول' => 1,
         'دوم' => 2,
         'سوم' => 3,
         'چهارم' => 4,
         'پنجم' => 5,
         'ششم' => 6,
         'هفتم' => 7,
         'هشتم' => 8,
         'نهم' => 9,
         'دهم' => 10,
         'یازدهم' => 11,
         'دوازدهم' => 12,
         'سیزدهم' => 13,
         'چهاردهم' => 14,
         'پانزدهم' => 15,
         'شانزدهم' => 16,
         'هفدهم' => 17,
         'هجدهم' => 18,
         'نوزدهم' => 19,
         'بیستم' => 20,
         'بیست و یکم' => 21,
         'بیست و دوم' => 22,
         'بیست و سوم' => 23,
         'بیست و چهارم' => 24,
         'بیست و پنجم' => 25,
         'بیست و ششم' => 26,
         'بیست و هفتم' => 27,
         'بیست و هشتم' => 28,
         'بیست و نهم' => 29,
         'سی‌ام' => 30,
         'سی ام' => 30,
         'سی و یکم' => 31,
         // اعداد ساده
         'یک' => 1,
         'دو' => 2,
         'سه' => 3,
         'چهار' => 4,
         'پنج' => 5,
         'شش' => 6,
         'هفت' => 7,
         'هشت' => 8,
         'نه' => 9,
         'ده' => 10
      ];

      $target_day = null;

      // اول چک کنیم کلمه باشه
      if (isset($day_numbers[$day_text])) {
         $target_day = $day_numbers[$day_text];
      }
      // اگر کلمه نبود، شاید عدد باشه
      else if (is_numeric($day_text)) {
         $target_day = (int)$day_text;
      }

      if ($target_day !== null && $target_day >= 1 && $target_day <= 31) {
         $current_persian_year = jdate('Y');
         $current_persian_month = jdate('n');
         $current_persian_day = jdate('j');

         // اگر روز هدف از امروز گذشته، ماه آینده
         if ($target_day <= $current_persian_day) {
            $target_month = $current_persian_month + 1;
            $target_year = $current_persian_year;

            if ($target_month > 12) {
               $target_month = 1;
               $target_year++;
            }
         } else {
            $target_month = $current_persian_month;
            $target_year = $current_persian_year;
         }

         // چک کنیم که روز هدف در ماه هدف وجود داشته باشه
         $days_in_target_month = ($target_month <= 6) ? 31 : (($target_month <= 11) ? 30 : (isLeapYear($target_year) ? 30 : 29));

         if ($target_day <= $days_in_target_month) {
            list($gy, $gm, $gd) = jalali_to_gregorian($target_year, $target_month, $target_day);
            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
         }
      }
   }

   // 🆕 تکراری ماهانه - اول هر ماه
   if (preg_match('/(اول|یکم|1)\s*(هر\s*)?(ماه|ماهه)/u', $text)) {
      $current_persian_year = jdate('Y');
      $current_persian_month = jdate('n');

      // اگر امروز اول ماه باشد، ماه آینده
      $current_persian_day = jdate('j');
      if ($current_persian_day >= 1) {
         $target_month = $current_persian_month + 1;
         $target_year = $current_persian_year;

         // اگر ماه از 12 بیشتر شد، سال بعد
         if ($target_month > 12) {
            $target_month = 1;
            $target_year++;
         }
      } else {
         $target_month = $current_persian_month;
         $target_year = $current_persian_year;
      }

      list($gy, $gm, $gd) = jalali_to_gregorian($target_year, $target_month, 1);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
   }

   // 🆕 تکراری ماهانه - وسط هر ماه (15ام)
   if (preg_match('/(وسط|نیمه|پانزدهم|15)\s*(هر\s*)?(ماه|ماهه)/u', $text)) {
      $current_persian_year = jdate('Y');
      $current_persian_month = jdate('n');
      $current_persian_day = jdate('j');

      if ($current_persian_day >= 15) {
         $target_month = $current_persian_month + 1;
         $target_year = $current_persian_year;
         if ($target_month > 12) {
            $target_month = 1;
            $target_year++;
         }
      } else {
         $target_month = $current_persian_month;
         $target_year = $current_persian_year;
      }

      list($gy, $gm, $gd) = jalali_to_gregorian($target_year, $target_month, 15);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
   }

   // 🆕 تکراری ماهانه - آخر هر ماه
   if (preg_match('/(آخر|انتها)\s*(هر\s*)?(ماه|ماهه)/u', $text)) {
      $current_persian_year = jdate('Y');
      $current_persian_month = jdate('n');

      $target_month = $current_persian_month + 1;
      $target_year = $current_persian_year;

      if ($target_month > 12) {
         $target_month = 1;
         $target_year++;
      }

      // تعداد روزهای ماه هدف
      $days_in_month = ($target_month <= 6) ? 31 : (($target_month <= 11) ? 30 : (isLeapYear($target_year) ? 30 : 29));

      list($gy, $gm, $gd) = jalali_to_gregorian($target_year, $target_month, $days_in_month);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
   }

   // 🆕 تکراری هفتگی - هر هفته
   if (preg_match('/(هر\s*هفته|هفتگی)/u', $text)) {
      return date('Y-m-d', strtotime('+1 week'));
   }

   // 🆕 روزهای هفته - شنبه، یکشنبه، ...
   $weekdays = [
      'شنبه' => 'Saturday',
      'یکشنبه' => 'Sunday',
      'دوشنبه' => 'Monday',
      'سه‌شنبه' => 'Tuesday',
      'سه شنبه' => 'Tuesday',
      'چهارشنبه' => 'Wednesday',
      'چهار شنبه' => 'Wednesday',
      'پنج‌شنبه' => 'Thursday',
      'پنج شنبه' => 'Thursday',
      'جمعه' => 'Friday'
   ];

   foreach ($weekdays as $persian => $english) {
      if (preg_match('/\b' . preg_quote($persian, '/') . '\b/u', $text)) {
         $next_weekday = strtotime("next $english");
         // اگر امروز همان روز هفته باشد، هفته آینده
         if (date('l') === $english) {
            $next_weekday = strtotime("+1 week");
         }
         return date('Y-m-d', $next_weekday);
      }
   }

   // 🔹 اول: ترکیبی (یک ساعت و نیم / یک ساعت نیم / بک ساعت نیم) - همان روز
   if (preg_match('/(یک|بک|1)\s*ساعت\s*(و\s*)?نیم\s*(دیگه|دیگر|بعد)?/u', $text)) {
      return $today;
   }

   // نیم ساعت - همان روز
   if (preg_match('/(نیم\s*ساعت|نصف\s*ساعت)\s*(دیگه|دیگر|بعد)/u', $text)) {
      return $today;
   }

   // یک ساعت - همان روز
   if (preg_match('/(یک|بک|1)\s*ساعت\s*(دیگه|دیگر|بعد)/u', $text)) {
      return $today;
   }

   // یک روز دیگر
   if (preg_match('/(یک|بک|1)\s*روز\s*(دیگه|دیگر|بعد)/u', $text)) {
      return date('Y-m-d', strtotime('+1 day'));
   }

   // ربع ساعت - همان روز
   if (preg_match('/(ربع\s*ساعت|ربعساعت|(یک|بک)\s*ربع)\s*(دیگه|دیگر|بعد)/u', $text)) {
      return $today;
   }

   // سه ربع ساعت - همان روز
   if (preg_match('/(سه\s*ربع|سه‌ربع|3\s*ربع)\s*(دیگه|دیگر|بعد)/u', $text)) {
      return $today;
   }

   // دقیقه - همان روز
   if (preg_match('/(\d+|نیم|یک|بک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده)\s*(دقیقه|دیقه|دیقع)\s*(دیگه|دیگر|بعد)/u', $text)) {
      return $today;
   }

   // ساعت (عمومی) - همان روز
   if (preg_match('/(\d+|نیم|یک|بک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده)\s*(ساعت|سعت)\s*(دیگه|دیگر|بعد)/u', $text)) {
      return $today;
   }

   // فردا
   if (preg_match('/(فردا|فرد)/u', $text)) {
      return date('Y-m-d', strtotime('+1 day'));
   }

   // پس فردا
   if (preg_match('/(پس‌فردا|پسفردا)/u', $text)) {
      return date('Y-m-d', strtotime('+2 days'));
   }

   // هفته آینده
   if (preg_match('/(هفته\s*آینده|هفته\s*دیگه|هفته\s*دیگر)/u', $text)) {
      return date('Y-m-d', strtotime('+1 week'));
   }

   // ماه آینده
   if (preg_match('/(ماه\s*آینده|ماه\s*دیگه|ماه\s*دیگر)/u', $text)) {
      return date('Y-m-d', strtotime('+1 month'));
   }

   // ساعت مشخص (ساعت 14 یا 14:30) - همان روز یا فردا
   if (preg_match('/ساعت\s*(\d{1,2}):?(\d{0,2})/u', $text, $matches)) {
      $hour = sprintf('%02d', $matches[1]);
      $minute = sprintf('%02d', $matches[2] ?: '00');

      $target_date = date('Y-m-d', mktime($hour, $minute, 0));
      $current_date = date('Y-m-d');
      if ($target_date != $current_date) {
         $date_changed = true;
      }

      return $today;
   }

   // زمان مشخص بدون کلمه ساعت (14:30) - همان روز یا فردا
   if (preg_match('/(\d{1,2}):(\d{2})/u', $text, $matches)) {
      $hour = sprintf('%02d', $matches[1]);
      $minute = sprintf('%02d', $matches[2]);

      $target_date = date('Y-m-d', mktime($hour, $minute, 0));
      $current_date = date('Y-m-d');
      if ($target_date != $current_date) {
         $date_changed = true;
      }

      return $today;
   }

   // تاریخ مشخص شمسی (1403/7/15)
   if (preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})/u', $text, $matches)) {
      $year = $matches[1];
      $month = sprintf('%02d', $matches[2]);
      $day = sprintf('%02d', $matches[3]);

      // تبدیل تاریخ شمسی به میلادی
      list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
   }

   // تاریخ کوتاه (15/7) - سال جاری شمسی
   if (preg_match('/(\d{1,2})\/(\d{1,2})/u', $text, $matches)) {
      $day = sprintf('%02d', $matches[1]);
      $month = sprintf('%02d', $matches[2]);

      // سال جاری شمسی
      $current_persian_year = jdate('Y');

      // تبدیل به میلادی
      list($gy, $gm, $gd) = jalali_to_gregorian($current_persian_year, $month, $day);
      return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
   }

   // اگر هیچ چیز پیدا نشد → همان روز
   return $today;
}

// تابع کمکی برای تشخیص سال کبیسه شمسی
function isLeapYear($year)
{
   $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
   $gy = $year + 1595;
   $leap = -14;
   $jp = $breaks[0];

   for ($j = 1; $j <= 19; $j++) {
      $jm = $breaks[$j];
      $jump = $jm - $jp;
      for ($n = 0; $n < $jump; $n++) {
         if ($year == $jp + $n) {
            if ($n < ($jump - $leap) || (($jump - $leap) < ($leap - $n))) {
               return false;
            } else {
               return true;
            }
         }
      }
      $leap = $leap + $jump - ($jm - $jp);
      $jp = $jm;
   }
   return false;
}


// تابع حذف پیام
function deleteMessage($chat_id, $message_id)
{
   $data = [
      'chat_id' => $chat_id,
      'message_id' => $message_id
   ];

   return makeRequest('deleteMessage', $data);
}

// درخواست به API تلگرام
function makeRequest($method, $data)
{
   $ch = curl_init();
   curl_setopt($ch, CURLOPT_URL, API_URL . $method);
   curl_setopt($ch, CURLOPT_POST, 1);
   curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

   $result = curl_exec($ch);
   curl_close($ch);

   return json_decode($result, true);
}

// تشخیص متن یادآور
function detectReminderText($text)
{
   $reminder_keywords = [
      'یادآوری',
      'یادآور',
      'یاد',
      'یادم',
      'یادت',
      'یادتون',
      'بنداز',
      'بنداذ',
      'بندار',
      'بگو',
      'بگی',
      'بگید',
      'دقیقه',
      'دقیقع',
      'دیقه',
      'دیقع',
      'ساعت',
      'سعت',
      'فردا',
      'پسفردا',
      'پس‌فردا',
      'هفته',
      'ماه',
      'دیگه',
      'دیگر',
      'بعد',
      'آینده',
      'نیم',
      'ربع',
      'یک',
      'دو',
      'سه',
      'چهار',
      'پنج',
      'شب',
      'غروب',
      'صبح',
      'عصر',
      'ظهر'
   ];

   $note_keywords = [
      'یادداشت',
      'نوت'
   ];
   $text_lower = mb_strtolower($text);



   foreach ($note_keywords as $keyword) {
      if (strpos($text_lower, $keyword) !== false) {
         return false;
      }
   }

   foreach ($reminder_keywords as $keyword) {
      if (strpos($text_lower, $keyword) !== false) {
         return true;
      }
   }

   // بررسی الگوهای زمانی
   if (preg_match('/(\d+|نیم|یک|دو|سه)\s*(دقیقه|دقیقع|دیقه|دیقع|ساعت|سعت|ربع)/u', $text)) {
      return true;
   }

   return false;
}

// استخراج مبلغ از متن
function extractAmountFromText($text)
{
   if (preg_match('/(\d+(?:,\d+)*)\s*(تومان|ریال)/u', $text, $matches)) {
      $amount = str_replace(',', '', $matches[1]);
      return intval($amount);
   }

   if (preg_match('/(\d+(?:,\d+)*)/u', $text, $matches)) {
      $amount = str_replace(',', '', $matches[1]);
      return intval($amount);
   }

   return 0;
}

// فرمت کردن عدد
function formatNumber($number)
{
   return number_format($number) . ' تومان';
}

// بررسی دسترسی ادمین
function isAdmin($user_id)
{
   global $ADMINS;
   return in_array($user_id, $ADMINS);
}

// تولید پیام انگیزشی
function getMotivationalMessage()
{
   $motivations = [
      "هر روز فرصتی تازه است تا از دیروز قوی‌تر شوی.",
      "موفقیت نتیجه عادت‌های کوچک اما پایدار است.",
      "هیچ بهانه‌ای نمی‌تواند جای تلاش واقعی را بگیرد.",
      "انضباط شخصی پلی است میان اهداف و دستاوردها.",
      "هر قدمی که برمی‌داری تو را از رؤیا به واقعیت نزدیک‌تر می‌کند.",
      "شکست پایان راه نیست، بخشی از مسیر موفقیت است.",
      "تمرکز روی اولویت‌ها، تو را سریع‌تر به قله می‌رساند.",
      "موفقیت تصادفی نیست؛ حاصل تصمیمات آگاهانه توست.",
      "برای رسیدن به چیزی که هرگز نداشتی، باید کسی شوی که هرگز نبودی.",
      "هر ساعت از امروز می‌تواند فردایت را تغییر دهد.",
      "کار امروزت، سرمایه فردایت است.",
      "موفقیت سهم کسانی است که عمل می‌کنند، نه کسانی که فقط آرزو می‌کنند.",
      "هر سختی در دل خود فرصتی برای رشد پنهان کرده است.",
      "پیشرفت آرام، بهتر از ایستادن کامل است.",
      "موفقیت یعنی ادامه دادن زمانی که دیگران متوقف می‌شوند.",
      "هیچ چیز قوی‌تر از ذهنی متمرکز و قلبی مصمم نیست.",
      "موفقیت به اندازه تلاشت، به تو نزدیک خواهد شد.",
      "انرژی تو جایی می‌رود که تمرکزت را هدایت کنی.",
      "امروز بکار تا فردا برداشت کنی.",
      "یادگیری مداوم سوخت اصلی رشد فردی است."
   ];

   return $motivations[array_rand($motivations)];
}

// پاکسازی متن
function cleanText($text)
{
   return trim(strip_tags($text));
}

// بررسی فرمت تاریخ
function isValidDate($date, $format = 'Y-m-d')
{
   $d = DateTime::createFromFormat($format, $date);
   return $d && $d->format($format) === $date;
}

function convertWordToNumber($word)
{
   $numbers = [
      'یک' => 1,
      'دو' => 2,
      'سه' => 3,
      'چهار' => 4,
      'پنج' => 5,
      'شش' => 6,
      'هفت' => 7,
      'هشت' => 8,
      'نه' => 9,
      'ده' => 10,
      'یازده' => 11,
      'دوازده' => 12,
      'سیزده' => 13,
      'چهارده' => 14,
      'پانزده' => 15,
      'شانزده' => 16,
      'هفده' => 17,
      'هجده' => 18,
      'نوزده' => 19,
      'بیست' => 20,
      'سی' => 30,
      'چهل' => 40,
      'پنجاه' => 50,
      'شصت' => 60
   ];

   return $numbers[$word] ?? (is_numeric($word) ? intval($word) : 1);
}

/**
 * محاسبه سن
 */
function calculateAge($birthday)
{
   $birth_date = new DateTime($birthday);
   $today = new DateTime('today');
   return $birth_date->diff($today)->y;
}

function cleanNumber($input)
{
   if (empty($input) || is_null($input)) {
      return 0;
   }

   // تبدیل به رشته
   $input = (string)$input;

   // نقشه تبدیل اعداد فارسی و عربی به انگلیسی
   $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
   $arabic_numbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
   $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

   // تبدیل اعداد فارسی و عربی به انگلیسی
   $input = str_replace($persian_numbers, $english_numbers, $input);
   $input = str_replace($arabic_numbers, $english_numbers, $input);

   // حذف تمام کاراکترهای غیر عددی به جز نقطه و منفی
   $input = preg_replace('/[^0-9.\-]/', '', $input);

   // حذف کاما و فاصله های اضافی
   $input = str_replace([',', ' ', '،'], '', $input);

   // مدیریت علامت منفی - فقط در ابتدا مجاز است
   $is_negative = false;
   if (strpos($input, '-') === 0) {
      $is_negative = true;
      $input = ltrim($input, '-');
   }

   // حذف تمام علامت های منفی از وسط و انتها
   $input = str_replace('-', '', $input);

   // مدیریت نقطه - فقط یکی مجاز است
   $parts = explode('.', $input);
   if (count($parts) > 2) {
      // اگر بیش از یک نقطه دارد، فقط اولی را نگه می‌داریم
      $input = $parts[0] . '.' . implode('', array_slice($parts, 1));
   }

   // حذف صفرهای اضافی از ابتدا
   $input = ltrim($input, '0');

   // اگر رشته خالی شد یا فقط نقطه باشد
   if (empty($input) || $input === '.') {
      return 0;
   }

   // اگر با نقطه شروع می‌شود، یک صفر اضافه کن
   if (strpos($input, '.') === 0) {
      $input = '0' . $input;
   }

   // اگر با نقطه تمام می‌شود، آن را حذف کن
   $input = rtrim($input, '.');

   // تبدیل به عدد
   if (strpos($input, '.') !== false) {
      $result = (float)$input;
   } else {
      $result = (int)$input;
   }

   // اعمال علامت منفی
   if ($is_negative) {
      $result = -$result;
   }

   return $result;
}

// ارسال پیام
function sendMessage($chat_id, $text, $keyboard = null)
{
   $data = [
      'chat_id' => $chat_id,
      'text' => $text . "\n\n•",
      'disable_web_page_preview' => true,
      'parse_mode' => 'HTML'
   ];

   if ($keyboard) {
      $data['reply_markup'] = json_encode($keyboard);
   }

   return makeRequest('sendMessage', $data);
}

// ویرایش پیام
function editMessage($chat_id, $message_id, $text, $keyboard = null)
{
   $data = [
      'chat_id' => $chat_id,
      'message_id' => $message_id,
      'text' => $text . "\n\n•",
      'parse_mode' => 'HTML'
   ];

   if ($keyboard) {
      $data['reply_markup'] = json_encode($keyboard);
   }

   return makeRequest('editMessageText', $data);
}

function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false)
{
   $data = [
      'callback_query_id' => $callback_query_id,
      'text' => $text,
      'show_alert' => $show_alert
   ];

   return makeRequest('answerCallbackQuery', $data);
}




