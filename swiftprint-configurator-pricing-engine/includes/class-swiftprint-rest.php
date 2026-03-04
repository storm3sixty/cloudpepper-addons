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

        register_rest_route('swiftprint/v1', '/schema/(?P<product_id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'schema'],
        ]);

        register_rest_route('swiftprint/v1', '/admin/schema/(?P<product_id>\d+)', [
            'methods' => ['GET', 'POST'],
            'permission_callback' => static fn () => current_user_can('manage_woocommerce'),
            'callback' => [$this, 'admin_schema'],
        ]);
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

    public function schema(WP_REST_Request $request): WP_REST_Response {
        $productId = (int) $request->get_param('product_id');
        $schema = $this->repository->get_set_by_product($productId);
        return new WP_REST_Response($schema ?: [], 200);
    }

    public function admin_schema(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $productId = (int) $request->get_param('product_id');
        if ($request->get_method() === 'GET') {
            return new WP_REST_Response($this->repository->get_set_by_product($productId) ?: [], 200);
        }

        $body = (array) $request->get_json_params();
        $setId = $this->repository->save_set($productId, $body);

        return new WP_REST_Response(['success' => true, 'set_id' => $setId], 200);
    }
}
