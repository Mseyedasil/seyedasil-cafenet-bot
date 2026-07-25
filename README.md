# ربات جامع کافی‌نت سیداصیل — GitHub/VPS Ready

نسخه یک‌جای پروژه شامل:

- ربات تلگرام Webhook
- PHP 8.2 + MySQL + Apache
- نصب خودکار با `install.sh`
- دسته‌بندی و خدمت نامحدود
- فیلدساز اختصاصی هر خدمت: متن، عدد، موبایل، توضیحات، انتخابی، فایل و تصویر
- ثبت سفارش از داخل تلگرام
- محاسبه مبلغ خدمت
- کسر مبلغ از کیف پول در زمان ثبت نهایی سفارش
- کیف پول مشتری
- درخواست شارژ کیف پول با کارت‌به‌کارت و ارسال تصویر رسید
- مدیریت درخواست شارژ از پنل
- مدیریت سفارش‌ها و تغییر وضعیت
- ارسال فایل نتیجه سفارش برای مشتری
- ارسال پیام مستقیم به مشتری
- مدیریت کاربران
- لاگ مدیریتی
- CSRF، Session امن، Prepared Statements و Webhook Secret
- فایل‌های مشتری خارج از `public` ذخیره می‌شوند

## نصب

Repository را روی GitHub بسازید و فایل‌های این پروژه را داخل آن قرار دهید.

روی VPS Ubuntu:

```bash
sudo apt update
sudo apt install -y git
cd /opt
sudo git clone https://github.com/USERNAME/seyedasil-cafenet-bot.git
cd seyedasil-cafenet-bot
sudo bash install.sh
```

نصب‌کننده دامنه، توکن ربات، اطلاعات مدیر و مشخصات کارت‌به‌کارت را می‌گیرد و Apache/PHP/MySQL/SSL/Webhook را تنظیم می‌کند.

## به‌روزرسانی

```bash
cd /opt/seyedasil-cafenet-bot
sudo git pull
sudo bash update.sh
```

`update.sh` فایل‌های برنامه را به‌روزرسانی می‌کند و دیتابیس را دستکاری نمی‌کند.

## نکته امنیتی

توکن ربات، رمز دیتابیس و رمز مدیر داخل GitHub ذخیره نمی‌شوند. نصب‌کننده آنها را در `/etc/seyedasil-bot/config.php` ایجاد می‌کند.

## مسیرهای اصلی

```text
پنل: https://DOMAIN/admin/login.php
Health: https://DOMAIN/health.php
Webhook: https://DOMAIN/bot.php
```

## جریان سفارش

کاربر:
1. خدمات کافی‌نت
2. دسته‌بندی
3. خدمت
4. فرم اختصاصی خدمت
5. ارسال اطلاعات/فایل
6. مشاهده مبلغ
7. تأیید نهایی
8. کسر مبلغ از کیف پول
9. ایجاد سفارش

مدیر:
- سفارش جدید را می‌بیند.
- وضعیت را تغییر می‌دهد.
- برای سفارش فایل نتیجه آپلود می‌کند.
- فایل نتیجه به تلگرام مشتری ارسال می‌شود.

## ساختار

```text
app/
database/
public/
storage/
install.sh
update.sh
README.md
.gitignore
```
