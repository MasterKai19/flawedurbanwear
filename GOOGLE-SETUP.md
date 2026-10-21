# Google Sheets and email on Vercel

This optional integration writes each new order to your private Sheet and sends mail to urbanwearflawed@gmail.com. If the customer supplied an email, it sends their acknowledgement too. The cloud MySQL database is the primary order record.

1. Sign into Google as urbanwearflawed@gmail.com. Open your Sheet. Your previously supplied Sheet ID is `1ZeO_aNSD7H3zdtTnyI7qw00rDIWITHBTLfDuSUw5p98`; use a different Sheet ID if you choose a new Sheet.
2. In the Sheet, open **Extensions → Apps Script** (not AppSheet).
3. Paste the contents of `server/integrations/google-apps-script/Code.gs` into the script editor, replacing the starter function. Save.
4. Open your deployed website's **Admin → Settings → Connect Google & Email**. Use **Generate shared secret**. Keep the resulting secret private.
5. In Apps Script, open **Project Settings → Script properties**. Add `SPREADSHEET_ID` with the Sheet ID, and `WEBHOOK_SECRET` with the generated secret. Save.
6. Back in the script editor, select **authorizeAndPrepare**, click Run, and authorize Sheets and email access for your own script. Confirm the Google account is urbanwearflawed@gmail.com.
7. Click **Deploy → New deployment → Web app**. Execute as **Me**; access **Anyone** so the website server can call it. The shared secret authenticates order writes. The Sheet itself stays private.
8. Copy the deployment URL ending in `/exec`. In your website's Notifications settings, paste that URL and the same shared secret, enable notifications, then save.
9. Click **Send test email**. Verify the test result in the website, confirm the `Flawed Orders` tab exists in the Sheet, and check the inbox/spam folder of urbanwearflawed@gmail.com.

After setup, new orders are attempted immediately and failed notifications are retried by Vercel Cron. Configure CRON_SECRET as described in START-HERE.md. Cron runs only on Production deployments.
The website does not need your Gmail password or an SMTP password.

When you change Apps Script code, use Deploy → Manage deployments → Edit → New version, then deploy again. Saving code alone does not update the deployed version.
If Google says access is denied, check the signed-in account, Sheet ownership and web-app deployment access.
If the website reports a secret mismatch, use the exact same secret in both places.
Google daily email quotas apply. Pending work is visible in Admin → Notifications and can be retried after quotas reset.
The Google Sheet records order details when received; use the website admin for current order/payment status.
