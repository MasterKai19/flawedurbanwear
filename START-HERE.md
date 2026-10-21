# Flawed Urbanwear — XAMPP / PHP + MySQL

An online-only streetwear store, established in 2023. The Living Tee is the first product, priced at BDT 500. Add more products from the admin dashboard. The supplied logo is used in the header and footer; the campaign photo has been cleaned of its old year and offline-store wording.

## Run in XAMPP

1. Extract this folder into your XAMPP `htdocs` folder and rename it `flawed`. Your installation was found at `D:\SOFTWARE\xamp`, so the expected destination is `D:\SOFTWARE\xamp\htdocs\flawed`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and create an empty database called `flawed_store`, with `utf8mb4_unicode_ci` collation. You do not need to import SQL manually: setup creates the tables.
4. Check `app/config.php`. It is already included and set to your detected XAMPP MySQL port **3307**, database `flawed_store`, username `root`, and an empty password. Change credentials if yours differ. `config.example.php` uses the standard port 3306 for other machines. Keep `base_path` as `/flawed` if that is the folder name.
5. Open `http://localhost/flawed/setup.php`. Choose your own admin email and a password of at least 12 characters. Setup adds the initial Living Tee and then locks itself. No default admin account or test orders are shipped.
6. Sign in at `http://localhost/flawed/admin/`. Set real delivery fees in Settings and confirm the product sizes and available stock in Products.
7. Visit `http://localhost/flawed/` and place a test order. It appears in the admin Orders page.

If Apache uses a different port, include it in the URL, for example `http://localhost:8080/flawed/`.

The files must run through PHP/Apache. Double-clicking an HTML file cannot run checkout, sessions or MySQL. PHP 8.1+ with PDO MySQL and Fileinfo is required; verified with XAMPP PHP 8.2.12 and MariaDB 10.4.32.

## What works

- Brand homepage, responsive layout, product catalog, front/back image views and size selection.
- Server-session cart, quantity editing, Add to Bag, Buy Now and checkout.
- SQL order records, item/price snapshots, customer and delivery details, private order-status page.
- Cash-on-delivery order requests. Google Sheets logging, owner emails to urbanwearflawed@gmail.com and customer acknowledgments are implemented; activate them with GOOGLE-SETUP.md. SMS is not included.
- Admin login, product creation/editing, images, visible/hidden products, sizes and stock.
- Admin order filters, status changes, agreed delivery fee, manually verified payment status and CSV export.
- Prices and totals calculated on the server; duplicate checkout requests do not create extra orders.
- Stock reserved transactionally on ordering, and returned once when an unshipped order is cancelled.
- CSRF protection, password hashing, prepared SQL queries, session regeneration and login attempt throttling.

Stock is initially **untracked**, because no actual inventory quantities were supplied. Blank stock means untracked; 0 means sold out. Removing a size from a product makes it sold out. Keep tracked stock modes consistent while there are pending orders, and enter the currently available quantity after existing reservations. New items do not affect old order price snapshots.

Delivery fees initially await confirmation. Set actual Dhaka/outside-Dhaka charges in Admin → Settings. A blank fee requires agreement with the customer before dispatch; zero means free delivery. Updating store rates does not alter old orders. Edit an existing order to set its agreed fee.

Order flow: pending → confirmed → processing → shipped → delivered. Pending, confirmed and processing orders may be cancelled. Shipped/delivered orders cannot be cancelled through this initial workflow. Returns/refunds are handled manually; no automated refund system is included. A cancelled order cannot be reopened.

## Your computer and future public hosting

Currently the store is local. Your friends cannot open your `localhost` URL from their devices.

To make the store public, upload this PHP project to compatible PHP/MySQL hosting and use a database on that host. You can then open `https://your-domain/admin/` from your computer to see and manage all online orders. CSV export saves a copy of order information to your computer. Orders do **not** automatically sync into a separate local XAMPP database; the hosted database is the live source of truth.

### Upload to PHP/MySQL hosting (such as InfinityFree)

1. Create the hosting account/domain and MySQL database. Use the database host and credentials provided by the host, not local root credentials.
2. Upload the project files to the public website folder, including `.htaccess` files. Do not upload local database server files, logs, backups or tests.
3. Set `app/config.php` to the hosted credentials. Set `base_path` to `''` for the domain root, or `/flawed` if deployed in that subfolder.
4. To preserve existing local products/orders/admin, export the local database with phpMyAdmin and import it into the host's database; also copy `assets/uploads`. Do not run first-time setup against that imported database.
5. For a fresh database, set a long random `setup_key` in private config, visit `/setup.php`, provide the key and create the admin account. Clear the setup key afterwards. Setup locks after an admin exists; you may remove `setup.php` after installation.
6. Enable HTTPS, verify that `/database/schema.sql` and `/app/` are denied, and test checkout and admin before sharing the store.
7. Keep database exports and product uploads backed up outside the public website folder.

InfinityFree advertises PHP/MySQL support, making this structure suitable for that hosting type. Free-host restrictions and payment-provider requirements must be checked before using real online payments. Vercel is not a drop-in XAMPP host: it uses function runtimes, PHP is a community runtime, and you would need an external database plus changes for persistent sessions/uploads. This project is intentionally packaged for Apache/PHP/MySQL hosting.

References: https://www.infinityfree.com/ and https://vercel.com/docs/functions/runtimes

## bKash: prepared extension point, not live

`app/payments.php` contains a disabled gateway interface for a later integration. It deliberately throws if used. The checkout always uses COD and never pretends a bKash payment succeeded. `bkash_enabled` is reserved configuration and does not activate payments by itself.

Once you have merchant API access, the next integration must:

1. Store merchant credentials in private server configuration/environment, not browser code.
2. Create a payment using the order amount already saved in SQL.
3. Redirect to the official bKash checkout; never collect wallet PINs or OTPs on this site.
4. Record a unique provider payment ID tied to an order.
5. Execute/query the payment server-side and verify the payment ID, amount, currency and completed status before marking paid. A return URL saying success is not proof of payment.
6. Handle retries, cancellation, uncertain execution, refunds and duplicate callbacks idempotently.
7. Test with bKash sandbox credentials and confirm the hosting provider permits required outbound HTTPS requests before enabling live payments.

Merchant credentials, API implementation, sandbox certification and activation are not included in this version because you asked to prepare bKash for later.

## File map

- `index.php`, `product.php`: homepage and catalog product page.
- `cart.php`, `checkout.php`, `order.php`: shopping and order flow.
- `admin/`: protected product/order/settings dashboard and CSV export.
- `app/bootstrap.php`: database connection, session, security and shared page layout.
- `app/config.example.php`: configuration template.
- `app/payments.php`: disabled future payment gateway interface.
- `database/schema.sql`: MySQL/MariaDB schema.
- `assets/`: CSS, JavaScript, supplied product images, cleaned campaign image and logo.

## Verification

All PHP files passed PHP syntax checks. End-to-end tests against an isolated MariaDB database passed: first-time setup and setup lock, admin authentication, CSRF checks, stock limits, validation, server-side prices, delivery calculation, stored orders, duplicate submission handling, private status links, stock return on cancellation, product editing and CSV export. The homepage was visually checked in a browser. No live bKash transactions were attempted.

## Google Sheets and email notifications

See **GOOGLE-SETUP.md** to authorize your Google account and activate the integration. Open Admin → Settings → Connect Google & Email. Code is included, but real delivery requires your private Apps Script deployment and matching shared secret. No Gmail password is needed.
