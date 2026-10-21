# Activate Google Sheets and email

The code is installed. It is **not connected to your Google account yet**. Until you complete the following authorization and deployment, orders remain in MySQL and no real emails or Sheet updates are sent.

Owner notifications are addressed to **urbanwearflawed@gmail.com**. Customers receive an order acknowledgment only when they supply an email address. Acknowledgments are not payment receipts or dispatch confirmations.

## One-time connection

1. Start XAMPP and sign in at `http://localhost/flawed/admin/`. Open Settings → **Connect Google & Email**. If shown, click **Install notification queue**. Existing orders remain intact; only new orders are queued.
2. Sign in to Google as `urbanwearflawed@gmail.com`. Create a **private** Google Sheet named **Flawed Urbanwear Orders**. Copy the spreadsheet ID from its URL: `https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit`. Do not share the Sheet publicly.
3. In that Sheet, open **Extensions → Apps Script**. Replace the editor's sample code with the entire contents of `integrations/google-apps-script/Code.gs` from this package. Save.
4. In the store's Google & Email admin page, click **Generate a shared secret**. Copy it privately. It is not saved until you save the connection form. Do not send it in chat or put it in HTML/JavaScript.
5. In Apps Script, open **Project Settings → Script properties**. Add `SPREADSHEET_ID` with your Sheet ID and `WEBHOOK_SECRET` with the generated secret. Save the properties.
6. In the script editor, select **authorizeAndPrepare** from the function menu and click **Run**. Review and approve Google's requested access to your Sheet and permission to send emails. The script does not read your Gmail inbox. The “Flawed Orders” tab will be created and formatted.
7. Click **Deploy → New deployment → Web app**. Select **Execute as: Me** and **Who has access: Anyone**. Deploy and copy the URL ending in `/exec`. This allows your PHP server to reach the endpoint without interactive Google login; the script checks your private secret before writing or sending anything. It does not make the Sheet public. Your Google account must allow this deployment type.
8. Back in the store admin page, enter the deployment URL and the same secret, check **Enable Sheet updates and emails**, and click **Save connection**.
9. Click **Send test email**. This checks Sheet access and sends one labeled test email to `urbanwearflawed@gmail.com`, without creating a customer order. Check inbox and spam. If it fails, the admin page explains the failure.
10. Place a test order using an email address you control. Confirm the MySQL order, Google Sheet row, owner email and customer acknowledgment. Then cancel that test order in admin if appropriate.

If editing Apps Script code later, update the deployed version through **Deploy → Manage deployments → Edit → New version**. Saving editor changes alone does not update an existing `/exec` deployment.

## Automatic sending and retries

- Every new order is saved with a notification-queue entry in the same MySQL transaction. Google delivery is attempted immediately after the transaction commits when enabled.
- Google errors cannot undo a successfully saved order. Queue status shows the Sheet, owner-email and customer-email results separately.
- The initial queue migration must be installed for an existing store. New installations create it during setup. Orders placed before the queue is installed are not backfilled or emailed automatically.
- Failed attempts wait five minutes before automatic worker retry. **Retry** in the admin dashboard allows an immediate manual retry. **Process next 3 due orders** handles a small batch. Sent entries are not resent.
- For unattended retries, optionally schedule the PHP CLI command below every five minutes in Windows Task Scheduler (while your computer and MySQL are running), or use your future host's cron service:

  Program: `D:\SOFTWARE\xamp\php\php.exe`

  Argument: `"D:\SOFTWARE\xamp\htdocs\flawed\process-notifications.php"`

- The worker accepts CLI execution only; there is no public cron URL. It processes up to 10 jobs per run. Jobs stop automatic retry after 10 attempts; review them and use manual Retry after correcting the connection.
- No scheduled task has been created automatically. The first attempt happens during checkout even without a scheduler.
- The Sheet deduplicates by order reference. Email sent flags are saved after Google accepts each email. A rare crash between acceptance and saving that flag can cause a duplicate email on retry; exactly-once email delivery cannot be guaranteed.
- “Accepted by Google” means Google's send API accepted the message. It does not guarantee inbox placement or prove the recipient read it.
- This is a new-order snapshot, not ongoing synchronization. Status changes made later in MySQL do not update the Sheet. MySQL remains the authoritative order record.

## Sheet columns

Order reference, placed date, customer name, phone, email, address, delivery area, items, subtotal, delivery fee, total, payment method, payment status at receipt, order status at receipt, notes, owner email sent, customer email sent, received date.

Keep the “Flawed Orders” tab and its columns intact. Keep all columns together when sorting; do not delete order rows or edit sent flags while notifications may retry. Protect access to this Sheet because it contains customer details. Formula-like user input is escaped, and phone numbers retain leading zeroes. The private order-page bearer token is never exported.

## Troubleshooting

- **Not active:** install the queue, save URL and secret, and enable the connection.
- **Sign-in page / invalid response:** use `/exec`, not `/dev`, and deploy with access for Anyone, executing as you.
- **Secret mismatch:** the stored secret and `WEBHOOK_SECRET` Script Property must match exactly.
- **Email quota:** Google imposes daily email limits. Leave failed jobs queued and retry after quota resets. Each order normally uses two recipients, or one when the customer leaves email blank.
- **TLS certificate error in XAMPP:** configure `curl.cainfo` in PHP's `php.ini` with a current trusted CA bundle and restart Apache. Do not disable certificate verification.
- **Local internet offline:** orders still save in MySQL; reconnect and retry.
- **Hosting blocks Google requests:** the hosting provider must permit outbound PHP cURL HTTPS to `script.google.com` and `script.googleusercontent.com`. Verify this before choosing free hosting.

The store never stores your Gmail password. Emails are sent under the account that authorized and deployed the script, so use your business account.

Official references:
- https://developers.google.com/apps-script/guides/web
- https://developers.google.com/apps-script/reference/mail/mail-app
- https://developers.google.com/apps-script/guides/content
- https://developers.google.com/apps-script/guides/services/quotas

## Validation performed

Local tests verified checkout queueing, CSRF and admin access, disabled-mode behavior, URL validation, partial result persistence, retry backoff, sent-job deduplication, and preservation of orders on delivery failure. The Google script was tested with in-memory service mocks for authentication, Sheet creation, duplicate rows, phone/formula handling, customer email omission, partial email failure and quota recovery. No live Google deployment, real Sheet write or real email send has been verified until you finish authorization and run the connection test.
