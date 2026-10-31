---
name: homeoclass-co-il-hosting
description: "homeoclass.co.il: где лежит сайт на Shneider, как устроен DNS в MyNames, что сделано на этапе 2 (31.10.2026) и что осталось до запуска"
metadata:
  type: reference
---

## Домен
- Регистратор **MyNames** (dash.mynames.co.il), домен `homeoclass.co.il`, оплачен до 06.08.2029, заблокирован от переноса, автопродление включено.
- Серверы имён по умолчанию MyNames, зона правится в панели («ניהול DNS»). На 31.10.2026 записи: `A @` и `A www` на **185.201.150.252** (заглушка регистратора) и одна пустая TXT. **Переключено на Shneider 31.10.2026.**
- Для запуска: поменять обе A-записи на **31.42.188.5**, дождаться DNS, выпустить SSL (AutoSSL в cPanel). 

## Сервер
- Тот же аккаунт Shneider `wvcuwqbm`, что у tutor и curshen.info (см. [[curshen-info-site-access]] в репозитории curshen_info). IP 31.42.188.5 по ipinfo: Меппел, Нидерланды (Serverius). Панель cPanel, `uapi` работает из SSH.
- Домен добавлен как addon domain (30.10.2026 по uapi `AddonDomain addaddondomain`), корень **`/home/wvcuwqbm/homeoclass.co.il`**, временный поддомен `homeoclass-co-il.curshen.ru`.
- База `wvcuwqbm_hco`, пользователь `wvcuwqbm_hco`, пароль только в `wp-config.php`.
- WordPress 7.1.4, язык he_IL, пермалинки `/%postname%/`, часовой пояс Asia/Jerusalem, **индексация выключена** (`blog_public=0`) до запуска.
- Админ WP `yehuda`, пароль лежит на сервере в **`/home/wvcuwqbm/.hco-admin-credentials`** (права 600). Перенести в менеджер паролей и сменить, файл удалить.
- Тема Kadence, меню `Main` в позициях primary и mobile.
- **Грабля:** WP-CLI на этом хостинге не видит `mod_rewrite`, поэтому `wp rewrite flush --hard` не пишет правила в `.htaccess`. Блок `# BEGIN WordPress` дописан вручную. Без него все постоянные ссылки отдают 404.

## Сделано
- Каркас: роль `student` («תלמיד»), рубрика `workbook`, восемь страниц-заглушек и меню, скрипт `plugins/setup/skeleton.php` (идемпотентный).
- mu-плагин `plugins/mu-plugins/workbook-restrict.php` (копия tutor `workbooks-restrict.php`, слаг `workbook`, доступ у администратора и роли `student`). Проверено анонимно 31.10.2026: запись рубрики 404, REST пуст, карта сайта чистая; обычная запись 200.

## Осталось
Плагины Lt и CF7 с tutor, перевод интерфейса кабинета на иврит, первые три опросника, оплата, письма, SSL после переключения DNS, DNS-записи почты (SPF/DKIM) для отправки с домена.

## Почта проекта
altevacure@gmail.com (решение Иегуды 31.10.2026, окончательное): админ-почта WordPress и GitHub. Адрес homeoclass@gmail.com перегружен, для проекта не использовать.

## SSL и почта
SSL выпущен AutoSSL 31.10.2026. Первая проверка упала: сервер ещё видел старый A-адрес из кэша DNS (TTL 1 час), после обновления помогло `uapi SSL start_autossl_check`. Почту решено уточнить: ящики в MyNames стоят 120 ₪ в год, лимит 150 писем в день; cPanel на Shneider хранит почту файлами в `~/mail`, не в базе, копии аккаунта еженедельные. На DNS нет MX, DKIM отсутствует, SPF проходит.

## Почта на Shneider (31.10.2026)
Решение Иегуды: почта на Shneider. Созданы ящики `ask@homeoclass.co.il` (для Киры) и `curshen@homeoclass.co.il` (для Иегуды), квота 2000 МБ, пересылка обоих на altevacure@gmail.com **с копией в ящике** (проверено тестовыми письмами: в Maildir по одному письму у обоих). Пароли ящиков лежат на сервере в `/home/wvcuwqbm/.hco-mail-credentials` (права 600), перенести в менеджер паролей и файл удалить. Ответ с домена из бесплатного Gmail: «Настройки → Аккаунты → Отправлять письма как», SMTP `server1669.shneider.host`, порт 587, логин полный адрес.
Не сделано: MX и запись `mail` в DNS MyNames, DKIM, проверка входящих с внешнего адреса.
