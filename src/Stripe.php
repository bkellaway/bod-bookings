<?php
declare(strict_types=1);

/** Minimal Stripe client: Checkout, Billing Portal, Subscriptions, webhook signature verification. */
final class Stripe
{
    private const API_VERSION = '2024-06-20';
    private string $base = 'https://api.stripe.com';

    public function __construct(private string $secretKey)
    {
        if ($u = getenv('STRIPE_API_URL')) $this->base = $u;   // test hook
    }

    public static function forApp(): self
    {
        return new self(Env::require('STRIPE_SECRET_KEY'));
    }

    public static function enabled(): bool
    {
        return Env::get('STRIPE_SECRET_KEY') !== null && Env::get('STRIPE_PRICE_PRO_MONTHLY') !== null;
    }

    public function createCheckoutSession(string $priceId, string $customerEmail, ?string $customerId, int $userId, string $successUrl, string $cancelUrl): array
    {
        $params = [
            'mode'                => 'subscription',
            'line_items[0][price]'    => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'client_reference_id' => (string)$userId,
            'allow_promotion_codes' => 'true',
            'subscription_data[metadata][user_id]' => (string)$userId,
        ];
        if ($customerId) {
            $params['customer'] = $customerId;
        } else {
            $params['customer_email'] = $customerEmail;
        }
        return $this->request('POST', '/v1/checkout/sessions', $params);
    }

    public function createPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->request('POST', '/v1/billing_portal/sessions', ['customer' => $customerId, 'return_url' => $returnUrl]);
    }

    public function getCheckoutSession(string $id): array
    {
        return $this->request('GET', '/v1/checkout/sessions/' . rawurlencode($id));
    }

    public function getSubscription(string $id): array
    {
        return $this->request('GET', '/v1/subscriptions/' . rawurlencode($id));
    }

    /** Verify Stripe-Signature header (v1 scheme, HMAC-SHA256, 5-minute tolerance). Returns decoded event. */
    public static function verifyWebhook(string $payload, string $sigHeader, string $secret, int $tolerance = 300): array
    {
        $ts = null;
        $sigs = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') $ts = (int)$v;
            if ($k === 'v1') $sigs[] = $v;
        }
        if (!$ts || !$sigs) {
            throw new RuntimeException('Malformed Stripe-Signature header');
        }
        if (abs(time() - $ts) > $tolerance) {
            throw new RuntimeException('Webhook timestamp outside tolerance');
        }
        $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
        $ok = false;
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) $ok = true;
        }
        if (!$ok) {
            throw new RuntimeException('Webhook signature mismatch');
        }
        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            throw new RuntimeException('Invalid webhook payload');
        }
        return $event;
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $ch = curl_init($this->base . $path . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
        $headers = ['Authorization: Bearer ' . $this->secretKey, 'Stripe-Version: ' . self::API_VERSION];
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20];
        if ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Idempotency-Key: ' . bin2hex(random_bytes(16));
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException("Stripe request failed: $err");
        $data = json_decode($raw, true) ?? [];
        if ($status >= 300) {
            throw new RuntimeException('Stripe error (' . $status . '): ' . ($data['error']['message'] ?? $raw));
        }
        return $data;
    }
}
