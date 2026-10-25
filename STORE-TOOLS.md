# Store tools

Open `/admin/growth.php` after signing in, or use the tools link on Orders.

- Sales: last 30 days, paid order value (not profit), delivered/cancelled counts, gross product units and popular sizes.
- Discount codes: percentage or fixed BDT; minimum subtotal, expiry by Dhaka date and a total use limit. One code per order. Delivery is excluded. Terms are immutable; disable and replace a code. Cancelled orders do not refund a use.
- Reviews: a private order link permits one review per purchased product only after delivery. Text/photo publication requires consent and admin approval. Moderate by relevance and privacy, not by positive rating.
- Restock: customers request a sold-out size and give their email consent. The admin queue highlights returned stock. Contact customers individually, then mark contacted or closed. Automated customer email is not connected.
- Pathao: enter a consignment ID and optional official HTTPS tracking link on the order. Customers see these on their private order page and after reference/phone verification. Automated booking and courier status syncing are not connected; merchant API credentials and a sandbox test are required first. No real shipment is created by this release.

Migration 5 adds tables without resetting stock, photos or orders. Discounts are stored in a separate ledger and preserved when delivery fees change. Existing orders receive no discount. No promotion is enabled automatically.

Performance: hero image preloaded; product settings read together; catalogue queries omit descriptions; only six approved reviews loaded initially; images lazy loaded; no third-party analytics or frontend dependencies added.
