# Elegance Couture

The PHP 8.3/MySQL application is the server-backed storefront. `preview.html` is a separate static prototype; it does not create server-side orders or process payments.

## Run with Docker

1. Copy `.env.example` to `.env`.
2. Change `DB_PASS` and `MYSQL_ROOT_PASSWORD` to private passwords. Add your Paystack **test** secret key to `PAYSTACK_SECRET` when you are ready to test payments.
3. From this folder, run `docker compose up --build`.
4. Open [http://localhost:8080](http://localhost:8080) for the PHP app or [http://localhost:8080/preview.html](http://localhost:8080/preview.html) for the static prototype.
5. Stop the containers with `docker compose down`. Database data remains in the Docker volume. To remove it and all saved database data, run `docker compose down -v`.
6. To make an admin, register in the app, then run `docker compose exec db mysql -u root -p elegance` and execute `UPDATE users SET role='admin' WHERE email='you@example.com';`.

The database schema and sample products are loaded automatically on the first start. If you change the schema later, existing Docker database volumes are not reinitialized automatically.

## Location and currency

The storefront detects the visitor's approximate region from their IP using ipapi.co, then loads exchange rates from open.er-api.com. Visitors can change the display currency manually. Location is approximate (no GPS permission is requested). ipapi.co receives the visitor's IP address; the exchange-rate service receives the app server's IP address.

Prices are stored in NGN. Other currencies are indicative conversions, not locked quotes. Paystack checkout is still initiated and verified in NGN; changing the display currency does not change the payment amount. The static preview does not process payments.

## Paystack and deployment

Set `PAYSTACK_SECRET` in `.env` to your Paystack test secret key for testing. Configure your deployed Paystack webhook URL as `https://yourdomain/paystack/webhook`. The callback and webhook require a publicly reachable HTTPS deployment to test end-to-end. Do not commit `.env` or live API keys.

For production, use HTTPS, strong unique database passwords, and a deployment appropriate for PHP and MySQL. GitHub Pages alone cannot run this PHP application.

## Current scope

Built: accounts, shop, orders with server-side NGN prices, Paystack initialization and verification, signed webhook, WhatsApp pending orders, measurement profiles, custom requests, Client Corner, basic admin, and localized display-price estimates.

Not built: product/inventory admin forms, custom-request quotations and deposits, private image uploads, email/in-app notifications, reviews, password reset, content editing, reports, and complete legal pages.
