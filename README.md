# Flawed Urbanwear — automatic Vercel setup

All 12 products, 46 size variants, images, categories, sold-out ribbons, cart, cash-on-delivery checkout, order management and Google Sheets/email integration are included.

1. Upload this folder's contents to your GitHub repository, replacing the previous Vercel edition. Keep api, assets, server and vercel.json at repository root.
2. Import it in Vercel as framework Other, Node 22.x. Leave build command and output directory unset.
3. Connect a cloud MySQL database. Add its MYSQL_URL (or individual DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD fields) and DB_SSL_CA certificate to Vercel Production variables.
4. Set OWNER_EMAIL, OWNER_PASSWORD (12+ characters), and CRON_SECRET (32+ random characters) privately in Vercel. Run `node tools/create-secrets.mjs` locally to generate password/cron values if needed.
5. Deploy and open your website. Tables, all 12 products, and your owner account initialize automatically. Sign in at /admin/.

No setup page, setup key, SQL import or running XAMPP is required. Connection credentials still come from your cloud database provider; code alone cannot provision a database. See START-HERE.md for the complete variable table.

Orders and stock are saved immediately to hosted MySQL. Refresh the admin Orders page to see new orders. Redeployments do not reset database records. Set up Google Sheets/email separately using GOOGLE-SETUP.md and test delivery before launch.

The bundled stock snapshot has LOST L: 1 and PASSION L: 3; other sizes are sold out. Review stock and delivery charges before sharing your link. bKash is not yet integrated.

Vercel Hobby is personal/non-commercial only; this commercial store requires Pro: https://vercel.com/docs/plans/hobby
PHP uses the community vercel-php runtime. Live deployment, cloud TLS and real notifications still require verification with your accounts. Local tests cover automatic setup, checkout, stock concurrency, sessions, access protection and uploads.
