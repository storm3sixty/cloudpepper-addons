<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

require_once __DIR__ . '/class-swiftprint-installer.php';
require_once __DIR__ . '/class-swiftprint-repository.php';
require_once __DIR__ . '/class-swiftprint-pricing-engine.php';
require_once __DIR__ . '/class-swiftprint-quote-service.php';
require_once __DIR__ . '/class-swiftprint-rest.php';
require_once __DIR__ . '/class-swiftprint-admin.php';
require_once __DIR__ . '/class-swiftprint-product.php';
require_once __DIR__ . '/class-swiftprint-cart.php';

final class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        Installer::maybe_upgrade();

        $repository    = new Repository();
        $pricingEngine = new Pricing_Engine($repository);
        $quoteService  = new Quote_Service($pricingEngine);

        (new Rest($quoteService, $repository))->hooks();
        (new Admin($repository))->hooks();
        (new Product($repository))->hooks();
        (new Cart($quoteService))->hooks();
    }
}
