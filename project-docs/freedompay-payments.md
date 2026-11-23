# FreedomPay online checkout

Updated 2026-11-19

## Current state

The PHP/SQLite app now has an optional FreedomPay Kazakhstan hosted-checkout integration. It is **not connected or enabled yet**: this repository has no merchant ID, payment secret, or publicly reachable PHP host. The public GitHub Pages version remains a local-only demo and cannot create or confirm real payments.

FreedomPay was selected because its official Kazakhstan Merchant API supports KZT and redirects card entry to the provider's payment page. The provider says card data stays on its page, so the merchant site does not handle card numbers. See the [official Merchant API overview](https://docs.freedompay.kz/doc-741636), [create payment page](https://docs.freedompay.kz/create-payment-9890787e0), and [result notification rules](https://docs.freedompay.kz/result-notify-12007677e0).

## PHP host configuration

Obtain a merchant account and credentials directly from FreedomPay. Set these environment variables only on the PHP server:

```text
FREEDOMPAY_MERCHANT_ID=<merchant ID>
FREEDOMPAY_SECRET_KEY=<payment secret key>
TOKYO_PUBLIC_BASE_URL=https://<public-php-host>
FREEDOMPAY_TEST_MODE=1
```

The site must be public over HTTPS so FreedomPay can deliver the payment result to `/payment.php?action=notify`. PHP needs PDO SQLite, SimpleXML, and either cURL or `allow_url_fopen`. No credential belongs in Git, GitHub Pages, browser JavaScript, or a committed `.env` file.

Test mode is the default. FreedomPay must issue/enable the merchant test credentials. Before accepting real money, replace the fictional educational restaurant details, menu, prices, policies and operator information with verified details; complete merchant onboarding and callback configuration; then set `FREEDOMPAY_TEST_MODE=0` and explicitly set `TOKYO_ALLOW_LIVE_PAYMENTS=1` in the server environment. The application refuses live mode without that second flag.

## Flow and safeguards

1. PHP recalculates prices, promotions and gifts from SQLite and creates the order in the existing transaction.
2. The server returns a random payment capability once; SQLite stores only its SHA-256 hash.
3. The browser posts that capability with CSRF protection to `payment.php?action=start`.
4. PHP signs the FreedomPay request, validates the signed XML response and only redirects to the HTTPS FreedomPay customer host.
5. Only the signed server callback can set the order to `paid`. A browser return to the site displays the stored status but cannot mark the order paid.
6. The callback checks the unique provider order/payment IDs, KZT currency and exact server-side amount; repeated notifications do not double-apply a payment.
7. If payment-page creation times out, its result is uncertain. The attempt stays locked until the provider callback or manual provider reconciliation; a timeout never authorizes a second payment. A callback cannot replace an assigned provider payment ID. A non-rejectable payment received for a cancelled order is recorded as paid and flagged in the admin for refund review.

Use a public HTTPS PHP installation for test callbacks. GitHub Pages has no backend endpoint, so the Pages demo intentionally offers no online payment. No card data or live payment credentials are stored by this project. Refunds, fiscal receipts, fulfillment notifications and provider-downtime reconciliation are not implemented; configure those with the merchant before commercial launch.

## Focused local check

```sh
php tests/freedompay-signature-test.php
python tests/payment-home-http.py
php -l payment.php
php -l lib/freedompay.php
```

The signature test uses FreedomPay's published test vector and no live credentials.
The payment-page parser accepts the provider's documented unsigned `pg_status=error` response only when it includes a numeric `pg_error_code`; successful responses still require a valid signature. This lets a definitive provider rejection mark the attempt failed so the guest can retry, instead of leaving it locked as an uncertain network result. [FreedomPay documents that payment-page errors are unsigned](https://docs.freedompay.kz/create-payment-9890787e0) and that successful responses carry a signature ([Merchant API response rules](https://docs.freedompay.kz/overview-741636m0)).

The HTTP check uses a temporary local server and SQLite database with synthetic records; it does not contact FreedomPay. It covers callbacks, repeated notifications, amount/ID mismatch, uncertain-attempt locking, signed status rendering, private-file blocking and active navigation markers. Merchant credentials and provider reconciliation remain prerequisites for real payment activation.
