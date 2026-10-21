<?php
/** Extension point for a future merchant-authorized bKash integration.
 * Checkout intentionally accepts only COD until this adapter is completed and tested.
 * Read amount/order ownership from SQL, never trust a browser amount or callback status.
 * Store provider payment IDs uniquely; verify with execute/query before marking paid.
 * Credentials belong in private server config or environment, never HTML/JavaScript.
 */
interface PaymentGateway {
    public function createPayment(array $order): array;
    public function verifyPayment(string $providerPaymentId): array;
}
final class BkashGateway implements PaymentGateway {
    public function createPayment(array $order): array { throw new LogicException('bKash is not configured. No payment was initiated.'); }
    public function verifyPayment(string $providerPaymentId): array { throw new LogicException('bKash is not configured. No payment was verified.'); }
}
