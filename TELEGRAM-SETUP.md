# Telegram order alerts

1. In Telegram, open the official @BotFather, send `/newbot`, and choose a name and available username ending in `bot`.
2. Keep the returned token private. Add it as `TELEGRAM_BOT_TOKEN` in Vercel Production environment variables; never commit it.
3. Open your new bot and press Start. Obtain your private chat's numeric ID from the bot's `getUpdates` API response (message.chat.id), keeping the token out of screenshots, chat and logs. Do not select a stranger's chat.
4. Set `TELEGRAM_CHAT_ID` to that numeric ID in Vercel Production, and redeploy.
5. Sign in to `/admin/notifications.php` and click Send Telegram Test. Verify the message arrives in your own chat.

Configured Telegram takes priority over Google Sheets/email. It sends order references, products, sizes, quantities and totals; customer contact information stays in the admin dashboard. It does not send customer emails. Removing the Telegram variables restores the previous configured Google fallback.

Orders commit to MySQL before dispatch. The existing outbox records successful/failed delivery and supports manual retry. Existing sent entries are not replayed. Pending older entries may be delivered when processed. A network timeout after Telegram accepts a message can cause duplicates on retry; use the reference to identify the same order.

Automatic retry uses the existing daily Vercel cron and requires CRON_SECRET. Immediate checkout dispatch and admin retries do not require that cron. The hosted database must be running. No end-to-end delivery is confirmed until a real test reaches the intended chat.

API reference: https://core.telegram.org/bots/api
