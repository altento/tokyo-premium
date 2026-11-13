# Booking email notifications

## Behavior

- PHP booking requests require a valid guest email and are saved as unconfirmed requests. The receipt says the request arrived and that the table is not reserved until an administrator confirms it.
- If `TOKYO_BOOKING_NOTIFY_EMAIL` is set, a separate new-request message is queued for that mailbox.
- The admin's “Подтвердить бронь” action updates the booking and adds the guest confirmation email in the same SQLite transaction. It sends only after the commit. A repeated status action reuses the unique event key.
- SMTP messages are attempted immediately after the database commit. If SMTP is absent or unavailable, the booking remains saved and the mail remains visible in the outbox; the admin shows sent/queued/failed states and has a CSRF-protected retry action.
- PHP booking forms attach a stable idempotency key across a retry with unchanged fields. Reservation, booking, email outbox rows and the first response are committed together. Repeated concurrent submissions with that key resolve to one booking and one request email.
- Public booking requests are limited to three per recipient email per day and five per source IP per hour. Only one-way hashes of those rate buckets are stored; old rows are pruned during subsequent booking writes. The PHP service must expose the real client IP through `REMOTE_ADDR` behind a trusted proxy; the app deliberately does not trust arbitrary `X-Forwarded-For` values.
- SMTP workers claim an outbox row under `BEGIN IMMEDIATE`; fresh-lock age is compared with SQLite's UTC clock so the PHP `Asia/Qyzylorda` timezone cannot make a new lease look stale. A unique event key prevents duplicate logical messages.
- GitHub Pages uses browser-local demo behavior, sends no email, and does not save the submitted email to its demo booking record. No payment or live table availability is implied.

## Runtime configuration

Set these variables in the PHP-FPM / web-server service environment or secret manager, then restart PHP workers. Do not put the password in Git, a public `.env`, `phpinfo()`, or an application response.

| Variable | Description |
| --- | --- |
| `TOKYO_SMTP_HOST` | SMTP server hostname. |
| `TOKYO_SMTP_PORT` | Usually 587 for STARTTLS or 465 for implicit TLS. Defaults follow the selected encryption mode. |
| `TOKYO_SMTP_ENCRYPTION` | `starttls` (default), `ssl`, or `none` only for unauthenticated loopback testing. `tls` is accepted as a STARTTLS alias. |
| `TOKYO_SMTP_USER` | Optional SMTP username; set together with the password. |
| `TOKYO_SMTP_PASSWORD` | SMTP password/app password. Store it in the service's secret manager. |
| `TOKYO_MAIL_FROM` | Verified sender address. |
| `TOKYO_MAIL_FROM_NAME` | Optional display name, defaults to Tokyo Premium. |
| `TOKYO_BOOKING_NOTIFY_EMAIL` | Optional restaurant mailbox for new requests. |
| `RESTAURANT_DB` | Persistent SQLite path shared by the PHP site and CLI worker; keep it outside the public document root and restrict file permissions. |

`starttls` and `ssl` verify peer certificates and host names. The transport does not log credentials or raw SMTP replies. Unencrypted SMTP is rejected except on a loopback IP/localhost with no AUTH credentials.

## Retry worker

Run the CLI worker in the same environment as the web app, including the same `RESTAURANT_DB` path. A scheduled invocation every few minutes can process up to 25 messages by default; pass an optional limit from 1 to 100.

```sh
php tools/send-mail-outbox.php 25
```

The worker does not expose email addresses in its output. Messages that exceed eight automatic attempts stay failed until an administrator retries them. The outbox uses at-least-once delivery: if an SMTP server accepts a message but the process crashes before SQLite records `sent`, a later retry may deliver a duplicate.

## Verification and limits

`tests/php_integrity_integration.php` uses a loopback fake SMTP server to verify accepted mail, rejection, safe failure state, retry, and a gated concurrent outbox claim while the Asia/Qyzylorda application timezone is active. It also tests v6-to-v7 bootstrap migration, same-key bookings and recipient rate limiting. It never contacts an external mail host. Real delivery is not enabled or tested until runtime credentials and a verified sender are configured. SMTP availability is not a reservation system; only the authenticated administrator controls the booking status.
