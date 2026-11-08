# CLAUDE.md — Expert Price & Stock Manager

راهنمای کار روی این پروژه برای Claude. / Guide for Claude when working on this repo.

## پروژه چیست؟ / What this is

افزونه ووکامرس `expert-price-manager` (نسخه فعلی در هدر `expert-price-manager.php`). ویرایش جدولی قیمت، SKU و موجودی محصولات و تنوع‌ها، داشبورد تغییرات، خروجی XLSX، حذف با ریدایرکت Rank Math. مالک: محمدرضا بصیر (گروه توسعه نرم‌افزاری هیلاوین). توضیح کامل در `README.md`.

- WordPress 6+, WooCommerce 7+, PHP 7.4+ (کد باید روی 7.4 کار کند: بدون `match`، `enum`، `readonly`، `str_contains` و syntax جدیدتر)
- Text domain: `expert-price-manager`. سورس رشته‌ها انگلیسی، ترجمه فارسی در `languages/`
- پوشه نصب‌شده باید `expert-price-manager/` باشد (نام ریپو `expert-price` است)

## ساختار / Layout

```
expert-price-manager.php   لودر تنبل + ثابت‌ها (EPM_VERSION، EPM_PLUGIN_DIR/URL/FILE)
includes/                  core, jalali, changelog, order-stock, admin-bar, class-epm-xlsx-writer
admin/                     menu (لود تنبل صفحه‌ها), common, pricing-page, changes-page
ajax/                      admin-pricing, admin-delete, admin-export, admin-changelog
assets/css|js|img          admin.css/js (جدول)، changes.css/js (داشبورد)
languages/                 .pot, fa_IR .po/.mo
uninstall.php, readme.txt, README.md, docs/
```

### قانون لیزلود (مهم)
فقط `core`, `jalali`, `changelog`, `order-stock` در هر درخواست لود می‌شوند. بقیه:
- `includes/admin-bar.php`: روی `init` برای کاربر با `manage_woocommerce` و نه در AJAX/cron
- فایل‌های `ajax/`: فقط در `wp_doing_ajax()` و بر اساس پیشوند اکشن (`epm_export_` → export، `epm_log_` → changelog، `epm_admin_delete_` → delete، بقیه → pricing). `admin-pricing.php` خودش `admin-delete.php` را require می‌کند
- صفحه‌ها: `admin/menu.php` روی `load-{hook}` فایل صفحه را require می‌کند. `pricing-page.php` خودش `ajax/admin-export.php` و `admin/common.php` را می‌آورد
- تابع مشترک جدید را در فایلی بگذارید که همیشه لود می‌شود یا در `common.php`/`order-stock.php`؛ **وابستگی بین فایل‌های AJAX را بدون `require_once` نگذارید**

## داده‌ها / Data model

- قیمت و موجودی مستقیم روی محصول ووکامرس (CRUD: `set_regular_price`, `set_stock_quantity`, …). هیچ آپشن اختصاصی برای آن‌ها وجود ندارد
- جدول `{prefix}epm_changes` (dbDelta، نسخه در `epm_db_version`)، نگهداری ۳۲ روز (`EPM_LOG_RETENTION_DAYS`)، هشدار از روز ۳۰ (`EPM_LOG_WARN_DAYS`)، پاک‌سازی روزانه `epm_purge_changes` در دسته‌های ۵۰۰۰
- متای محصول: `_price_update_date`, `_epm_price_updated_by`, `_epm_update_source`, `_epm_last_stock`
- Revert فقط برای منبع `user` و فقط وقتی مقدار فعلی با `new_value` ثبت‌شده برابر باشد
- **هر ستون/فیلد/متای اختصاصی جدید باید در `uninstall.php` پاک شود، ولی قیمت، موجودی و ریدایرکت Rank Math هرگز پاک نشوند**

## قراردادهای کد / Conventions

- WordPress Coding Standards (tab، Yoda، `array()` یا `[]` مطابق فایل مجاور). هر خروجی HTML با `esc_*` و هر ورودی با `sanitize_*`/`wp_unslash`
- هر endpoint AJAX: `check_ajax_referer( 'epm_admin_pricing_action', 'nonce' )` + `epm_can_access_price_tab()` (قابلیت `manage_woocommerce`)
- SQL فقط با `$wpdb->prepare` (هشدارهای phpcs فقط جایی که جدول مستقیم لازم است سکوت شوند)
- رشته‌های UI: `__()` با text domain؛ برای JS در لیست `wp_localize_script` اضافه شوند (`epm_get_js_i18n()` برای جدول، `epm_get_changes_js_i18n()` برای داشبورد) و در JS با `epmT/epmTF` (admin.js) یا `T/TF` (changes.js) خوانده شوند
- **هر رشته جدید:** به `languages/expert-price-manager.pot` و ترجمه فارسی در `fa_IR.po` اضافه شود، سپس `.mo` ساخته شود (`msgfmt -o … .po` یا `polib`)
- JS: بدون ES module و بدون بیلد؛ تمام مقادیر داخل `innerHTML` با `escapeHtml` escape شوند (باگ XSS قبلاً رفع شده، دوباره تکرار نشود)
- نسخه دارایی‌ها با `epm_asset_version()` (نسخه + filemtime)؛ رنگ‌ها/توکن‌های برند در بالای `admin.css` و `changes.css`
- UI راست‌به‌چپ (RTL) است؛ هر تغییر ظاهری هم در RTL و هم در عرض موبایل بررسی شود
- تاریخ‌ها به شمسی نمایش داده می‌شوند (`includes/jalali.php`)؛ زمان ذخیره در دیتابیس timestamp

## ریلیز و نسخه / Releasing

با هر تغییر قابل‌استفاده، عدد نسخه را در **دو جا** بالا ببرید: هدر `expert-price-manager.php` (`Version:` و `EPM_VERSION`) و `readme.txt` (`Stable tag` + بخش Changelog). اگر رفتار کاربر عوض شد، `README.md` را هم به‌روز کنید. زیپ نصب باید پوشه‌ی بالاترین سطحش `expert-price-manager/` باشد. نسخه‌ها patch-به‌patch زیاد می‌شوند (1.1.14 → 1.1.15).

## تست / Testing

هیچ تست خودکاری در ریپو نیست و محیط معمول Claude بدون وردپرس واقعی است. حداقل قبل از تحویل:
1. `php -l` روی همه فایل‌های PHP و `node --check` روی JS
2. phpcs با استاندارد `WordPress-Extra` (فقط هشدار `manage_woocommerce` قابل‌قبول است)
3. برای تغییر UI: رندر صفحه با شبیه‌ساز (`wp_*` stubها) و اسکرین‌شات Playwright (Chromium نصب‌شده؛ `playwright install` نزنید)
4. برای SQL داشبورد: تست روی MariaDB محلی
5. ماژول لودر: هر ۸ حالت (فرانت، ادمین، دو صفحه، AJAXهای export/log/delete/pricing/بی‌ربط) را شبیه‌سازی کنید و ببینید فقط فایل‌های لازم لود می‌شوند
6. **هرگز ادعا نکنید روی سایت زنده تست شده؛** تست واقعی با ووکامرس هنوز انجام نشده (کوئری‌های WP_Query، Rank Math، cron، سفارش‌ها)

## ترجیحات مالک / Owner preferences

- پاسخ‌ها **فارسی**، مستقیم، بدون مقدمه و عذرخواهی اضافه
- برای هر تغییر ظاهری **قبل یا همراه تحویل، تصویر پیش‌نمایش** نشان دهید
- بعد از هر تغییر، زیپ نصب‌شدنی (`expert-price-manager-<نسخه>.zip`) بدهید؛ زیپ‌های قدیمی‌تر را نگه ندارید
- کاربر مبتدی در گیت است: مراحل را قدم‌به‌قدم توضیح دهید
- آدرس ریپو: `https://github.com/rzabasir/expert-price` (شاخه `main`). برای پوش، اتصال گیت‌هاب باید به ریپو دسترسی push داشته باشد؛ کامیت‌ها با فرمت پیام کوتاه «نسخه X: توضیح»
- فایل `2.1.0` قدیمی با ۷ فایل (شاخه‌ی جداگانه) **جزو این کد نیست**؛ بدون تأیید مالک با آن ادغام نکنید

## چیزهایی که نباید بشکنند / Don't break

- ثبت تغییر موجودی سفارش‌ها در checkout سمت سایت (`includes/order-stock.php`)
- قانون «موجود است»: برداشتن تیک → موجودی ۰ و قفل، تیک دوباره → ۱ (هم JS هم سرور)
- ترتیب حذف محصول: اعتبارسنجی → ساخت ریدایرکت → حذف؛ در شکست، ریدایرکت برگردانده می‌شود
- `epm_guard_ajax_redirects` (پیام خوانا هنگام ریدایرکت AJAX توسط پلاگین دیگر)
- پنهان‌سازی اعلان‌های سایر افزونه‌ها فقط داخل صفحه‌های این افزونه
