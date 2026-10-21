# Flawed Urbanwear — Vercel edition

This is the complete website, not an update for the XAMPP folder. Keep its folders together.
The storefront, product galleries, categories, SOLD OUT ribbons, cart, checkout and admin pages are included.
You do not need InfinityFree or a running home computer.

## What you need

1. A Vercel **Pro** account for a commercial shop. Vercel Hobby is for personal, non-commercial use.
2. A cloud **MySQL** database with remote connections and a CA certificate. Aiven for MySQL is one option with a free database tier.
3. Your Google account, Sheet and Apps Script deployment for order emails and Sheet copies.

Vercel runs the website using the pinned community PHP runtime. MySQL stores products, orders, stock, login sessions and new product photos. Original product images are deployed with the website. Google sends the email notifications to **urbanwearflawed@gmail.com**.
The website can accept orders even if Google is temporarily unavailable; its SQL queue preserves notification work for retries.

## 1. Create the cloud database

Create a MySQL service, for example at https://aiven.io/free-mysql-database.
Copy its host, port, database name, username and password. Download the service's CA certificate.
Use the actual port displayed by the provider; it may not be 3306. Use a dedicated empty database for this store.
Do not use the old InfinityFree database or XAMPP credentials.

## 2. Deploy this entire folder

Option A — GitHub:
- Create a private GitHub repository and upload this folder's **contents**, preserving all subfolders and dotfiles.
- In Vercel, choose Add New Project, import the repository, and select framework **Other**.
- The project root is the folder containing **vercel.json**, **api**, **assets**, and **server**.
- Use Node.js **22.x**. Do not enter a frontend build command or choose an output directory.
- Add the environment variables below, then deploy.

Option B — terminal:
- Open a terminal inside this folder and run `npx vercel`.
- Sign in and choose/create your project, with framework Other.
- Add the environment variables in the Vercel dashboard.
- Run `npx vercel --prod` after adding them.
- The first deployment may show a setup error until the database variables are saved and redeployed.

### Vercel → Project → Settings → Environment Variables

| Name | Value |
|---|---|
| `DB_HOST` | Cloud MySQL hostname |
| `DB_PORT` | Cloud MySQL port |
| `DB_NAME` | Database name, for example `defaultdb` if that is what the provider shows |
| `DB_USER` | Cloud MySQL username |
| `DB_PASSWORD` | Cloud MySQL password |
| `DB_SSL_CA` | Entire downloaded CA certificate text, including BEGIN and END lines |
| `OWNER_EMAIL` | Your private admin login email |
| `OWNER_PASSWORD` | Your private admin password, at least 12 characters |
| `CRON_SECRET` | A different random secret of at least 32 characters |

Generate your owner password and cron secret locally with `node tools/create-secrets.mjs`, or use your password manager.
Enter secrets directly in Vercel. Do not put them in source files, GitHub or chat.
Save these variables for **Production**. Use a separate test database for Preview deployments, or leave Preview unconfigured.
After changing variables, redeploy so they take effect.

## 3. Open your store

Open your deployment URL. The first request creates the tables, imports all 12 products and 46 size variants, and creates the owner account from OWNER_EMAIL and OWNER_PASSWORD. No setup page or SETUP_KEY is needed.

Sign in at `/admin/` using those owner details. OWNER_PASSWORD is used only to create the first owner; changing it later does not reset an existing account. You may remove it from Vercel after successful initialization and redeploy. Keep the password in your password manager.

Initialization is locked against concurrent requests and records completion in the database. Later deployments preserve inventory, accounts and orders, even if you remove products from the catalog.

Check stock before launch: the supplied snapshot has LOST L = 1 and PASSION L = 3; all other sizes are sold out. Review delivery fees in Settings.
Existing local orders/accounts are not migrated automatically. Do not import local test databases.

You may alternatively supply MYSQL_URL or DATABASE_URL (a MySQL URL, not PostgreSQL) instead of the five DB connection fields. DB_SSL_CA is still required for remote connections. Use your provider's actual details; connecting an integration only works automatically if it supplies these names. A connection URL must percent-encode special characters in credentials. Explicit DB_* variables override URL fields.

## 4. Connect order notifications

Follow **GOOGLE-SETUP.md** in this folder.
Connect your private Google Sheet, authorize the Apps Script, and save its deployment URL and shared secret under Admin → Settings → Connect Google & Email.
Click **Send test email** and confirm receipt at urbanwearflawed@gmail.com.
Use a new shared secret rather than one previously pasted into chat.

After connection:
- Checkout saves an order and reserves available stock in one SQL transaction.
- Google Sheets and email are attempted immediately after the transaction commits.
- Vercel Cron checks pending notification work once daily at 00:00 UTC in **Production**. CRON_SECRET authenticates the request.
- Each scheduled run handles one due order. Failed attempts become eligible after five minutes, but the automatic scheduler runs only daily. Use Admin → Notifications to retry sooner; up to ten attempts are allowed.
- Admin → Notifications shows successes and failures and lets you retry manually.
- Google quotas still apply. Delivery is not guaranteed until a live test succeeds.
- The Sheet is an order-receipt log; later admin status changes are managed in the website, not synchronized back to the Sheet.

## 5. Open the admin on your phone

Open `https://YOUR-PROJECT.vercel.app/admin/`, sign in, and bookmark it.
You can view orders, update their status, change stock and prices, and upload product photos from your phone.
Refresh the Orders page to see new orders. Email notifications can appear on your phone through Gmail if Gmail notifications are enabled.
No separate mobile app or direct database connection is needed.

Photo uploads must be PNG, JPEG or WebP, at most 3 MB **combined per save**. They are stored in MySQL and survive redeployments.
Monitor database storage as your image library grows; a large catalog should use dedicated object storage.
bKash remains marked Coming Soon. This version accepts cash-on-delivery order requests.

## Before sharing the public link

- Finish setup, review inventory and set delivery rates.
- Verify a real notification test. Local tests used mock email delivery.
- Place one clearly labeled test order and verify the admin, Sheet, email and stock deduction.
- Cancel the test order in Admin to return its reserved stock.
- Keep the Google Sheet private. Customers only receive their own private order link.
- Keep database backups and credentials in your hosting account/password manager.
- Routine redeployments do not overwrite your live database or inventory.

## Troubleshooting

- **503 / store not ready:** check DB_* variables, port, CA certificate, provider connection availability, then redeploy.
- **First launch unavailable:** verify OWNER_EMAIL and OWNER_PASSWORD (12+ characters), database variables and CA certificate, then redeploy.
- **Old setup bookmark:** `/setup.php` now redirects to `/admin/login.php`.
- **Missing notifications:** check Admin → Notifications, Apps Script deployment access, shared secret, Google quotas and Vercel Cron logs.
- **Upload too large:** reduce the combined size of the front/back images to under 3 MB.
- **Local site still works differently:** this is a separate cloud store; XAMPP does not synchronize with it.

## Implementation and verification

Vercel runtime: https://github.com/vercel-community/php
Database option: https://aiven.io/docs/products/mysql/get-started
Cron: https://vercel.com/docs/cron-jobs/manage-cron-jobs
Commercial plan rules: https://vercel.com/docs/limits/fair-use-guidelines

Local integration tests cover protected routes, first-time setup, product/category counts, cross-instance SQL sessions, CSRF rejection, checkout pricing, duplicate checkout handling, stock concurrency, cancellation, and persistent photo uploads.
Mock notification tests cover queue retries and Google Sheets/email deduplication. A live Vercel build, cloud TLS connection and real email delivery still require your service accounts and credentials.
