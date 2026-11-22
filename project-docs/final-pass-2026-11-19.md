# Ускоренный финальный проход — 19 ноября 2026

## Исправлено и проверено

- Страница результата оплаты: исправлен вызов `page_start()`, подписанный адрес возвращает HTTP 200; ответ не кэшируется и не передаёт адрес через Referer.
- Таймаут FreedomPay больше не считается доказанным отказом: новая платёжная сессия блокируется до callback или сверки с провайдером. Callback не может заменить уже назначенный ID платежа; оплаченный заказ не становится неоплаченным после запоздавшего отказа.
- Неотклоняемый успешный платёж отменённого заказа сохраняется для учёта; админка показывает необходимость проверки возврата. Автоматического возврата нет.
- `Start-Tokyo.ps1` подключает установленный PDO SQLite и сохраняет базу вне публичной папки. `-CheckOnly` проверен на PHP 8.5.11 / SQLite 3.53.4; локальный сервер реально запущен. Старую базу можно выбрать через `RESTAURANT_DB`; скрипт её не заменяет.
- HTTP-роутер разрешает только публичные страницы и ресурсы. SQL, `.env`, `.git`, библиотеки, CLI-установщик, тесты и обходы путей возвращают 404.
- Активная красная линия восстановлена у «Философии» и «Отзывов», проверена в HTML PHP-ответов.
- Добавлена инструкция домашнего запуска, Tailscale и публичного HTTPS-туннеля; сам туннель не установлен и не включён.

## Короткие проверки

- `python tests/payment-home-http.py`: 27 проверок callback, статуса, закрытых файлов и навигации.
- PHP/SQLite integrity: 229 проверок. На Windows HTTP-запросы сериализовались встроенным сервером; это не доказательство конкурентного HTTP-исполнения.
- Admin account search/referral: 19 проверок; admin set formats: 15 проверок.
- Cart store: 14 проверок; синтаксис изменённых PHP и `git diff --check`: без ошибок.
- Прежний вывод об отсутствии SQLite был неточен: DLL установлена, но не загружалась. Временный PHP ini позволил проверить сервер локально.
- Playwright MCP не вернул результат открытия localhost; ожидание остановлено. Новые скриншоты и успешный визуальный просмотр в этом проходе не заявляются.
- Gemini CLI не авторизована; независимый ответ не получен.

## Публикация

Изменения `6f7a4d5` опубликованы. GitHub Actions [44427930855](https://github.com/altento/tokyo-premium/actions/runs/44427930855): build и deploy завершились успешно, включая новую HTTP-проверку callback/роутера. После публикации локальный Playwright Chromium открыл публичные `about.html` и `reviews.html` с параметром версии на ширине 390 px: HTTP 200, корректный активный пункт, ширина документа 390 px. Это короткая браузерная проверка, без новых скриншотов и визуальной приёмки всех страниц.

## Ограничения

Реальная оплата требует мерчанта, стабильного публичного HTTPS и сверки неизвестных платежей с провайдером. SMTP требует отправителя. Удалённый доступ требует установки Tailscale на обоих устройствах либо публичного туннеля. Ключи и учётные записи не выдуманы.

Полная визуальная идентичность 11 макетам и боевой платёжный сценарий остаются непроверенными. Известные визуальные отличия перечислены в `visual-qa/full-static-2026-11-18/comparison.md`; этот проход не объявляет их устранёнными. Pages остаётся статической демонстрацией без PHP-админки.


## 2026-11-23 — short admin acceptance follow-up

Re-ran only the two authenticated admin browser paths on isolated disposable SQLite databases. `npm run test:admin-booking-browser` passed: admin login, pending-review filter, Cyrillic booking search, confirm/cancel, guest status badge and two status notifications. `npm run test:admin-marketing-browser` passed: campaign metadata and inventory preservation, promo create/edit/duplicate handling, seasonal collection and linked-item editing, and 390 px layout. These checks verify the tested workflows; SMTP remained deliberately disabled, so no external message was sent. No application defect reproduced in this short pass.
