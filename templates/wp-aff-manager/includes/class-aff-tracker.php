<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_Tracker — クリック計測 & リダイレクト
 *
 * /aff-click/?id={assignment_id} にアクセスすると:
 *  1. assignment_id を検証
 *  2. 対応リンクの click_count をインクリメント
 *  3. アフィリエイト URL へ 302 リダイレクト
 */
class Aff_Tracker {

    public function register(): void {
        add_action( 'init',              [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'handle_click' ] );
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule( '^aff-click/?$', 'index.php?aff_click=1', 'top' );
    }

    public function add_query_var( array $vars ): array {
        $vars[] = 'aff_click';
        return $vars;
    }

    public function handle_click(): void {
        if ( ! get_query_var( 'aff_click' ) ) {
            return;
        }

        $assignment_id = absint( $_GET['id'] ?? 0 );
        if ( ! $assignment_id ) {
            wp_safe_redirect( home_url() );
            exit;
        }

        $row = Aff_Query::get_assignment_for_tracker( $assignment_id );
        if ( ! $row ) {
            wp_safe_redirect( home_url() );
            exit;
        }

        // クリック数インクリメント（link_id ベース）
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . Aff_DB::table('links') . " SET click_count = click_count + 1 WHERE id = %d",
            (int) $row->link_id
        ) );

        // noopenerのためヘッダーに Referrer-Policy を付与
        header( 'Referrer-Policy: no-referrer' );

        wp_redirect( esc_url_raw( $row->url ), 302 );
        exit;
    }
}
