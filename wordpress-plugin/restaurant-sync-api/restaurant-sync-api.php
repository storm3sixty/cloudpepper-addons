<?php
/**
 * Plugin Name: Restaurant Sync API
 * Description: Read-only normalized booking endpoints for desktop sync app.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('restaurant-sync/v1', '/bookings', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('read'); },
        'callback' => 'restaurant_sync_get_bookings',
    ]);

    register_rest_route('restaurant-sync/v1', '/bookings/(?P<id>\d+)', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('read'); },
        'callback' => 'restaurant_sync_get_booking_by_id',
    ]);
});

function restaurant_sync_get_bookings(WP_REST_Request $request)
{
    $after = $request->get_param('after');
    $args = [
        'post_type' => 'restaurant_booking',
        'post_status' => 'any',
        'posts_per_page' => 50,
        'orderby' => 'date',
        'order' => 'ASC',
    ];

    if (!empty($after)) {
        $args['date_query'] = [[ 'after' => sanitize_text_field($after) ]];
    }

    $posts = get_posts($args);
    $normalized = array_map('restaurant_sync_normalize_booking', $posts);

    return new WP_REST_Response($normalized, 200);
}

function restaurant_sync_get_booking_by_id(WP_REST_Request $request)
{
    $id = intval($request->get_param('id'));
    $post = get_post($id);

    if (!$post) {
        return new WP_Error('not_found', 'Booking not found', ['status' => 404]);
    }

    return new WP_REST_Response(restaurant_sync_normalize_booking($post), 200);
}

function restaurant_sync_normalize_booking($post)
{
    $party_size = intval(get_post_meta($post->ID, 'party_size', true));
    $booking_time = get_post_meta($post->ID, 'booking_time', true);

    return [
        'id' => intval($post->ID),
        'created_at' => get_post_time('c', true, $post),
        'booking_time' => !empty($booking_time) ? $booking_time : get_post_time('c', true, $post),
        'party_size' => $party_size > 0 ? $party_size : 2,
        'name' => get_post_meta($post->ID, 'name', true),
        'phone' => get_post_meta($post->ID, 'phone', true),
        'email' => get_post_meta($post->ID, 'email', true),
        'notes' => get_post_meta($post->ID, 'notes', true),
        'status' => $post->post_status,
        'admin_url' => get_edit_post_link($post->ID, ''),
    ];
}
