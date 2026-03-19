<?php
/**
 * Plugin Name: WP Affiliate Manager
 * Plugin URI:  https://github.com/kojirooda/WEB_site_consulting
 * Description: アフィリエイトリンク・広告ブロック・配信ページを一元管理するプラグイン。
 * Version:     1.0.0
 * Author:      kojirooda
 * Text Domain: wp-aff-manager
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────
define( 'AFF_VERSION',    '1.0.0' );
define( 'AFF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AFF_DB_VERSION', '1.0' );

// ── Autoload ───────────────────────────────────────────────────────────
require_once AFF_PLUGIN_DIR . 'includes/class-aff-db.php';
require_once AFF_PLUGIN_DIR . 'includes/class-aff-query.php';
require_once AFF_PLUGIN_DIR . 'includes/class-aff-renderer.php';
require_once AFF_PLUGIN_DIR . 'includes/class-aff-tracker.php';
require_once AFF_PLUGIN_DIR . 'includes/class-aff-hooks.php';

if ( is_admin() ) {
    require_once AFF_PLUGIN_DIR . 'admin/class-aff-admin.php';
    require_once AFF_PLUGIN_DIR . 'admin/class-aff-csv.php';
    require_once AFF_PLUGIN_DIR . 'admin/class-aff-sheets.php';
}

// ── Activation / Deactivation ──────────────────────────────────────────
register_activation_hook( __FILE__, function () {
    Aff_DB::install();
    // /aff-click/ リライトルールをキャッシュに書き込んでから即フラッシュ。
    // これをしないとプラグイン有効化直後にクリック計測 URL が 404 になる。
    add_rewrite_rule( '^aff-click/?$', 'index.php?aff_click=1', 'top' );
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

// ── Bootstrap ──────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    // DB upgrade check
    if ( get_option( 'aff_db_version' ) !== AFF_DB_VERSION ) {
        Aff_DB::install();
    }

    // Admin UI
    if ( is_admin() ) {
        new Aff_Admin();
    }

    // Frontend hooks + shortcode
    $hooks = new Aff_Hooks();
    $hooks->register();
    add_shortcode( 'aff_block', [ $hooks, 'shortcode_handler' ] );
    add_shortcode( 'aff_link',  [ $hooks, 'shortcode_link_handler' ] );

    // Click tracker
    $tracker = new Aff_Tracker();
    $tracker->register();
} );
