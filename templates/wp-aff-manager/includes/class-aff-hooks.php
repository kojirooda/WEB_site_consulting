<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_Hooks — WordPress フック経由の自動挿入 & ショートコード
 */
class Aff_Hooks {

    public function register(): void {
        // 本文への自動挿入
        add_filter( 'the_content', [ $this, 'inject_content' ], 20 );

        // ナビゲーションへの挿入
        add_filter( 'wp_nav_menu_items', [ $this, 'inject_nav' ], 20, 2 );

        // ヘッダー / フッター
        add_action( 'wp_head',   [ $this, 'inject_header' ], 99 );
        add_action( 'wp_footer', [ $this, 'inject_footer' ], 99 );

        // フロントエンド CSS
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    // ── 本文への挿入 ──────────────────────────────────────────────────
    public function inject_content( string $content ): string {
        if ( ! is_singular() || is_admin() ) {
            return $content;
        }

        $blocks = Aff_Query::get_blocks_by_placement( 'content' );
        foreach ( $blocks as $block ) {
            $html = Aff_Renderer::render_block( $block );
            if ( $html === '' ) {
                continue;
            }
            switch ( $block->hook_name ) {
                case 'before_content':
                    $content = $html . $content;
                    break;
                case 'after_content':
                default:
                    $content .= $html;
                    break;
            }
        }

        return $content;
    }

    // ── ナビゲーションへの挿入 ──────────────────────────────────────
    public function inject_nav( string $items, object $args ): string {
        $blocks = Aff_Query::get_blocks_by_placement( 'navigation' );
        foreach ( $blocks as $block ) {
            // hook_name にメニューの theme_location を指定して絞り込む
            if ( $block->hook_name !== '' && $block->hook_name !== ( $args->theme_location ?? '' ) ) {
                continue;
            }
            $html = Aff_Renderer::render_block( $block );
            if ( $html ) {
                $items .= '<li class="menu-item aff-nav-item">' . $html . '</li>';
            }
        }
        return $items;
    }

    // ── ヘッダー ──────────────────────────────────────────────────────
    public function inject_header(): void {
        $blocks = Aff_Query::get_blocks_by_placement( 'header' );
        foreach ( $blocks as $block ) {
            echo Aff_Renderer::render_block( $block ); // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    // ── フッター ──────────────────────────────────────────────────────
    public function inject_footer(): void {
        $blocks = Aff_Query::get_blocks_by_placement( 'footer' );
        foreach ( $blocks as $block ) {
            echo Aff_Renderer::render_block( $block ); // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    // ── アセット ──────────────────────────────────────────────────────
    public function enqueue_assets(): void {
        $css = AFF_PLUGIN_URL . 'assets/css/frontend.css';
        wp_enqueue_style( 'wp-aff-manager', $css, [], AFF_VERSION );
    }

    // ── ショートコード: [aff_block slug="xxx"] ────────────────────────
    public function shortcode_handler( array $atts ): string {
        $atts  = shortcode_atts( [ 'slug' => '' ], $atts, 'aff_block' );
        $slug  = sanitize_key( $atts['slug'] );
        if ( ! $slug ) {
            return '';
        }
        $block = Aff_Query::get_block_by_slug( $slug );
        if ( ! $block ) {
            return '';
        }
        return Aff_Renderer::render_block( $block );
    }

    // ── ショートコード: [aff_link id="N"] ────────────────────────────
    public function shortcode_link_handler( array $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0, 'text' => '' ], $atts, 'aff_link' );
        $id   = absint( $atts['id'] );
        if ( ! $id ) {
            return '';
        }

        global $wpdb;
        $link = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Aff_DB::table('links') . " WHERE id = %d AND status = 'active' LIMIT 1",
            $id
        ) );
        if ( ! $link ) {
            return '';
        }

        // [aff_link] はアサインメントを介さず直接リンクを出力するため
        // クリックURLはリンクIDベースの簡易エンドポイントを使用
        $text      = $atts['text'] !== '' ? $atts['text'] : $link->link_text;
        $track_url = esc_url( add_query_arg( 'link_id', $id, home_url( '/aff-click/' ) ) );
        return sprintf(
            '<a href="%s" class="aff-link" target="_blank" rel="noopener noreferrer nofollow">%s</a>',
            $track_url,
            esc_html( $text )
        );
    }
}
