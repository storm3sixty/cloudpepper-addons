<?php

declare(strict_types=1);

namespace SwiftPrint;

use WP_Error;

defined('ABSPATH') || exit;

final class Quote_Service {
    public function __construct(private Pricing_Engine $engine) {}

    public function create_quote(int $productId, array $selection, int $basePriceSetId, int $schemaVersion): array|WP_Error {
        $result = $this->engine->quote($productId, $selection);
        if (isset($result['error'])) {
            return new WP_Error('swiftprint_quote_failed', (string) $result['error'], ['status' => 400]);
        }

        $expires = time() + (15 * MINUTE_IN_SECONDS);
        $payload = [
            'product_id' => $productId,
            'base_price_set_id' => $basePriceSetId,
            'schema_version' => $schemaVersion,
            'selection' => $selection,
            'total' => $result['total'],
            'expires' => $expires,
        ];

        $token = $this->sign($payload);
        $this->store_quote_log($token, $productId, $payload, (float) $result['total']);

        return [
            'total' => $result['total'],
            'currency' => get_woocommerce_currency(),
            'breakdown' => $result['breakdown'],
            'weight' => $result['weight'],
            'expires_at' => gmdate('c', $expires),
            'quote_token' => $token,
        ];
    }

    public function verify_quote_token(string $token, int $productId, array $selection): array|WP_Error {
        $decoded = $this->verify_and_decode($token);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if ((int) ($decoded['product_id'] ?? 0) !== $productId) {
            return new WP_Error('swiftprint_token_product_mismatch', 'Quote product mismatch.', ['status' => 403]);
        }

        if (($decoded['selection'] ?? []) !== $selection) {
            return new WP_Error('swiftprint_token_selection_mismatch', 'Quote selection mismatch.', ['status' => 403]);
        }

        $fresh = $this->engine->quote($productId, $selection);
        if (is_array($fresh) && abs((float) $fresh['total'] - (float) $decoded['total']) > 0.009) {
            return new WP_Error('swiftprint_reprice_mismatch', 'Server reprice mismatch.', ['status' => 409]);
        }

        return [
            'total' => (float) $decoded['total'],
            'breakdown' => $fresh['breakdown'] ?? [],
            'weight' => $fresh['weight'] ?? 0,
        ];
    }

    private function sign(array $payload): string {
        $json = wp_json_encode($payload);
        $sig = hash_hmac('sha256', $json, wp_salt('swiftprint_quote'));
        return base64_encode($json . '.' . $sig);
    }

    private function verify_and_decode(string $token): array|WP_Error {
        $raw = base64_decode($token, true);
        if (! $raw || ! str_contains($raw, '.')) {
            return new WP_Error('swiftprint_invalid_token', 'Invalid quote token format.', ['status' => 403]);
        }

        [$json, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $json, wp_salt('swiftprint_quote'));
        if (! hash_equals($expected, $sig)) {
            return new WP_Error('swiftprint_invalid_signature', 'Invalid quote signature.', ['status' => 403]);
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return new WP_Error('swiftprint_invalid_payload', 'Invalid quote payload.', ['status' => 403]);
        }

        if ((int) ($payload['expires'] ?? 0) < time()) {
            return new WP_Error('swiftprint_quote_expired', 'Quote token expired.', ['status' => 410]);
        }

        return $payload;
    }

    private function store_quote_log(string $token, int $productId, array $payload, float $total): void {
        global $wpdb;
        $table = $wpdb->prefix . 'swiftprint_quote_logs';
        $wpdb->insert($table, [
            'token_hash' => hash('sha256', $token),
            'product_id' => $productId,
            'payload' => wp_json_encode($payload),
            'total' => $total,
            'currency' => get_woocommerce_currency(),
            'expires_at' => gmdate('Y-m-d H:i:s', (int) $payload['expires']),
            'created_at' => current_time('mysql', true),
        ]);
    }
}
