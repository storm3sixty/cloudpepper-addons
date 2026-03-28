<?php

declare(strict_types=1);

namespace SwiftPrint;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class Rest {
    public function __construct(private Quote_Service $quoteService, private Repository $repository) {}

    public function hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('swiftprint/v1', '/quote', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'quote'],
        ]);

        register_rest_route('swiftprint/v1', '/products', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_manage'],
            'callback' => [$this, 'products'],
        ]);

        register_rest_route('swiftprint/v1', '/schema/(?P<product_id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_manage'],
            'callback' => [$this, 'schema_get'],
        ]);

        register_rest_route('swiftprint/v1', '/schema/(?P<product_id>\d+)', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage'],
            'callback' => [$this, 'schema_post'],
        ]);
    }

    public function can_manage(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    public function quote(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $productId = (int) $request->get_param('product_id');
        $selection = (array) $request->get_param('selections');
        $setId = (int) $request->get_param('base_price_set_id');
        $schemaVersion = (int) $request->get_param('schema_version');

        $quote = $this->quoteService->create_quote($productId, $selection, $setId, $schemaVersion);
        if (is_wp_error($quote)) {
            return $quote;
        }

        return new WP_REST_Response($quote, 200);
    }

    public function products(WP_REST_Request $request): WP_REST_Response {
        $search = sanitize_text_field((string) $request->get_param('search'));
        $items = $this->repository->search_products($search, 100);

        return new WP_REST_Response(['items' => $items], 200);
    }

    public function schema_get(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $productId = (int) $request->get_param('product_id');
        $schema = $this->repository->get_schema_for_product($productId);

        if (! $schema) {
            return new WP_Error('swiftprint_not_found', 'Product not found.', ['status' => 404]);
        }

        return new WP_REST_Response($schema, 200);
    }

    public function schema_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $productId = (int) $request->get_param('product_id');
        $body = (array) $request->get_json_params();
        if ($productId <= 0) {
            return new WP_Error('swiftprint_bad_product', 'Invalid product.', ['status' => 400]);
        }

        $saved = $this->repository->save_schema_for_product($productId, $body);

        return new WP_REST_Response([
            'success' => true,
            'schema' => $saved,
            'schema_version' => (int) ($saved['schema_version'] ?? 1),
        ], 200);
    }
}
