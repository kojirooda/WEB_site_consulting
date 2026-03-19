<?php
/**
 * プラグイン削除時に実行。テーブルと設定を削除する。
 * WordPress が直接呼び出すファイル — ABSPATH と WP_UNINSTALL_PLUGIN の両方を確認する。
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 定数を定義してから Aff_DB を読み込む
define( 'AFF_VERSION',    '1.0.0' );
define( 'AFF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AFF_DB_VERSION', '1.0' );

require_once AFF_PLUGIN_DIR . 'includes/class-aff-db.php';

Aff_DB::uninstall();
