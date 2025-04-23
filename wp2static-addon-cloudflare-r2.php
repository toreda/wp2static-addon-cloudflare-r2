<?php

/**
 * Plugin Name:       WP2Static Add-on: Cloudflare R2 Deployment
 * Plugin URI:        https://wp2static.com
 * Description:       Deploy WP2Static sites directly to Cloudflare R2.
 * Version:           0.0.1
 * Requires PHP:      7.3
 * Author:            Toreda, Inc.
 * Author URI:        https://www.toreda.com
 * License:           MIT
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP2STATIC_CLOUDFLARE_R2_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP2STATIC_CLOUDFLARE_R2_VERSION', '0.0.1' );

if ( file_exists( WP2STATIC_CLOUDFLARE_R2_PATH . 'vendor/autoload.php' ) ) {
    require_once WP2STATIC_CLOUDFLARE_R2_PATH . 'vendor/autoload.php';
}

function run_wp2static_addon_cloudflare_r2() : void {
    $controller = new WP2StaticCloudflareR2\Controller();
    $controller->run();
}

register_activation_hook(
    __FILE__,
    [ 'WP2StaticCloudflareR2\Controller', 'activate' ]
);

register_deactivation_hook(
    __FILE__,
    [ 'WP2StaticCloudflareR2\Controller', 'deactivate' ]
);

run_wp2static_addon_cloudflare_r2();

