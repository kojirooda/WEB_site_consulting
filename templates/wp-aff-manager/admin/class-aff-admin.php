<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_Admin — 管理画面メニュー・フォーム処理
 *
 * 各サブページ: Links / Blocks / Pages / Assignments
 * アクション:   list（一覧） / edit（新規・編集） / delete（削除）
 */
class Aff_Admin {

    /** 管理ページスラッグのプレフィックス */
    const MENU_SLUG = 'wp-aff-manager';

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ $this, 'handle_post' ] );
    }

    // ── メニュー登録 ──────────────────────────────────────────────────
    public function register_menus(): void {
        add_menu_page(
            'Affiliate Manager',
            'Aff Manager',
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'page_links' ],
            'dashicons-megaphone',
            58
        );
        add_submenu_page( self::MENU_SLUG, 'リンク管理',       'リンク管理',       'manage_options', self::MENU_SLUG,              [ $this, 'page_links' ] );
        add_submenu_page( self::MENU_SLUG, 'ブロック管理',     'ブロック管理',     'manage_options', self::MENU_SLUG . '-blocks',   [ $this, 'page_blocks' ] );
        add_submenu_page( self::MENU_SLUG, 'ページ条件管理',   'ページ条件管理',   'manage_options', self::MENU_SLUG . '-pages',    [ $this, 'page_pages' ] );
        add_submenu_page( self::MENU_SLUG, '割り当て管理',     '割り当て管理',     'manage_options', self::MENU_SLUG . '-assigns',  [ $this, 'page_assigns' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }
        wp_enqueue_style( 'wp-aff-admin', AFF_PLUGIN_URL . 'admin/css/admin.css', [], AFF_VERSION );
    }

    // ── POST ハンドラ（admin_init で処理してリダイレクト） ───────────
    public function handle_post(): void {
        if ( ! isset( $_POST['aff_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Forbidden', 'wp-aff-manager' ) );
        }

        $action = sanitize_key( $_POST['aff_action'] );

        switch ( $action ) {
            case 'save_link':
                check_admin_referer( 'aff_save_link' );
                $this->save_link();
                break;
            case 'save_block':
                check_admin_referer( 'aff_save_block' );
                $this->save_block();
                break;
            case 'save_page':
                check_admin_referer( 'aff_save_page' );
                $this->save_page();
                break;
            case 'save_assign':
                check_admin_referer( 'aff_save_assign' );
                $this->save_assign();
                break;
        }
    }

    // ── 各ページディスパッチャー ──────────────────────────────────────
    public function page_links(): void  { $this->dispatch( 'links' ); }
    public function page_blocks(): void { $this->dispatch( 'blocks' ); }
    public function page_pages(): void  { $this->dispatch( 'pages' ); }
    public function page_assigns(): void{ $this->dispatch( 'assigns' ); }

    private function dispatch( string $entity ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Forbidden', 'wp-aff-manager' ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        // DELETE アクション（GET リンク＋nonce）
        if ( $action === 'delete' ) {
            $id = absint( $_GET['id'] ?? 0 );
            check_admin_referer( "aff_delete_{$entity}_{$id}" );
            $this->delete_row( $entity, $id );
            wp_safe_redirect( remove_query_arg( [ 'action', 'id', '_wpnonce' ] ) );
            exit;
        }

        $view_file = AFF_PLUGIN_DIR . "admin/views/{$entity}-" . ( $action === 'edit' ? 'edit' : 'list' ) . '.php';
        if ( file_exists( $view_file ) ) {
            require $view_file;
        }
    }

    // ── 削除 ──────────────────────────────────────────────────────────
    private function delete_row( string $entity, int $id ): void {
        global $wpdb;
        $table_map = [
            'links'   => Aff_DB::table('links'),
            'blocks'  => Aff_DB::table('blocks'),
            'pages'   => Aff_DB::table('pages'),
            'assigns' => Aff_DB::table('assignments'),
        ];
        if ( isset( $table_map[ $entity ] ) && $id > 0 ) {
            $wpdb->delete( $table_map[ $entity ], [ 'id' => $id ], [ '%d' ] );
        }
    }

    // ── SAVE: リンク ──────────────────────────────────────────────────
    private function save_link(): void {
        global $wpdb;
        $id   = absint( $_POST['id'] ?? 0 );
        $data = [
            'link_name'       => sanitize_text_field( $_POST['link_name'] ?? '' ),
            'url'             => esc_url_raw( $_POST['url'] ?? '' ),
            'advertiser'      => sanitize_text_field( $_POST['advertiser'] ?? '' ),
            'link_text'       => sanitize_text_field( $_POST['link_text'] ?? '' ),
            'banner_url'      => esc_url_raw( $_POST['banner_url'] ?? '' ),
            'unit_price'      => (float) ( $_POST['unit_price'] ?? 0 ),
            'commission_type' => sanitize_key( $_POST['commission_type'] ?? 'cpa' ),
            'status'          => sanitize_key( $_POST['status'] ?? 'active' ),
            'valid_from'      => sanitize_text_field( $_POST['valid_from'] ?? '' ) ?: null,
            'valid_until'     => sanitize_text_field( $_POST['valid_until'] ?? '' ) ?: null,
        ];
        $fmt = [ '%s','%s','%s','%s','%s','%f','%s','%s','%s','%s' ];
        if ( $id ) {
            $wpdb->update( Aff_DB::table('links'), $data, [ 'id' => $id ], $fmt, [ '%d' ] );
        } else {
            $wpdb->insert( Aff_DB::table('links'), $data, $fmt );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&saved=1' ) );
        exit;
    }

    // ── SAVE: ブロック ────────────────────────────────────────────────
    private function save_block(): void {
        global $wpdb;
        $id   = absint( $_POST['id'] ?? 0 );
        $data = [
            'block_name'     => sanitize_text_field( $_POST['block_name'] ?? '' ),
            'block_slug'     => sanitize_key( $_POST['block_slug'] ?? '' ),
            'placement_type' => sanitize_key( $_POST['placement_type'] ?? 'shortcode' ),
            'hook_name'      => sanitize_text_field( $_POST['hook_name'] ?? '' ),
            'max_links'      => absint( $_POST['max_links'] ?? 3 ),
            'display_format' => sanitize_key( $_POST['display_format'] ?? 'text_link' ),
            'template'       => wp_kses_post( $_POST['template'] ?? '' ),
            'css_class'      => sanitize_html_class( $_POST['css_class'] ?? '' ),
            'status'         => sanitize_key( $_POST['status'] ?? 'active' ),
        ];
        $fmt = [ '%s','%s','%s','%s','%d','%s','%s','%s','%s' ];
        if ( $id ) {
            $wpdb->update( Aff_DB::table('blocks'), $data, [ 'id' => $id ], $fmt, [ '%d' ] );
        } else {
            $wpdb->insert( Aff_DB::table('blocks'), $data, $fmt );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-blocks&saved=1' ) );
        exit;
    }

    // ── SAVE: ページ条件 ──────────────────────────────────────────────
    private function save_page(): void {
        global $wpdb;
        $id   = absint( $_POST['id'] ?? 0 );
        $data = [
            'page_label'  => sanitize_text_field( $_POST['page_label'] ?? '' ),
            'target_type' => sanitize_key( $_POST['target_type'] ?? 'all' ),
            'post_type'   => sanitize_key( $_POST['post_type'] ?? '' ),
            'post_id'     => absint( $_POST['post_id'] ?? 0 ) ?: null,
            'term_id'     => absint( $_POST['term_id'] ?? 0 ) ?: null,
            'url_pattern' => sanitize_text_field( $_POST['url_pattern'] ?? '' ),
            'priority'    => (int) ( $_POST['priority'] ?? 0 ),
        ];
        $fmt = [ '%s','%s','%s','%d','%d','%s','%d' ];
        if ( $id ) {
            $wpdb->update( Aff_DB::table('pages'), $data, [ 'id' => $id ], $fmt, [ '%d' ] );
        } else {
            $wpdb->insert( Aff_DB::table('pages'), $data, $fmt );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-pages&saved=1' ) );
        exit;
    }

    // ── SAVE: 割り当て ────────────────────────────────────────────────
    private function save_assign(): void {
        global $wpdb;
        $id   = absint( $_POST['id'] ?? 0 );
        $data = [
            'block_id'      => absint( $_POST['block_id'] ?? 0 ),
            'link_id'       => absint( $_POST['link_id'] ?? 0 ),
            'page_id'       => absint( $_POST['page_id'] ?? 0 ) ?: null,
            'display_order' => absint( $_POST['display_order'] ?? 0 ),
            'override_text' => sanitize_text_field( $_POST['override_text'] ?? '' ),
            'is_active'     => isset( $_POST['is_active'] ) ? 1 : 0,
            'start_date'    => sanitize_text_field( $_POST['start_date'] ?? '' ) ?: null,
            'end_date'      => sanitize_text_field( $_POST['end_date'] ?? '' ) ?: null,
        ];
        $fmt = [ '%d','%d','%d','%d','%s','%d','%s','%s' ];
        if ( $id ) {
            $wpdb->update( Aff_DB::table('assignments'), $data, [ 'id' => $id ], $fmt, [ '%d' ] );
        } else {
            $wpdb->insert( Aff_DB::table('assignments'), $data, $fmt );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-assigns&saved=1' ) );
        exit;
    }

    // ── 共通ヘルパー: 削除リンク生成 ──────────────────────────────────
    public static function delete_link( string $entity, int $id, string $page ): string {
        $nonce = wp_create_nonce( "aff_delete_{$entity}_{$id}" );
        $url   = admin_url( "admin.php?page={$page}&action=delete&id={$id}&_wpnonce={$nonce}" );
        return sprintf(
            '<a href="%s" class="aff-delete-link" onclick="return confirm(\'削除しますか？\')">削除</a>',
            esc_url( $url )
        );
    }

    /** 保存完了メッセージ */
    public static function saved_notice(): void {
        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>';
        }
    }
}
