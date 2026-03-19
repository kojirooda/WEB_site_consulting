<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_Renderer — ブロックの HTML 生成
 */
class Aff_Renderer {

    /**
     * ブロック全体を HTML 文字列として返す。
     *
     * @param  object $block  wp_aff_blocks 行
     * @return string  出力 HTML（リンクなし = 空文字）
     */
    public static function render_block( object $block ): string {
        $links = Aff_Query::get_links_for_block( $block );
        if ( empty( $links ) ) {
            return '';
        }

        $items_html = '';
        foreach ( $links as $row ) {
            $items_html .= self::render_link( $block, $row );
        }

        // list フォーマットは <li> の集合なので <ul> でラップする（valid HTML）
        if ( $block->display_format === 'list' && $block->template === '' ) {
            $items_html = '<ul class="aff-list">' . $items_html . '</ul>';
        }

        // ブロックラッパー
        $css  = $block->css_class ? esc_attr( $block->css_class ) : '';
        $slug = esc_attr( $block->block_slug );
        return sprintf(
            '<div class="aff-block aff-block--%s %s" data-block-id="%d">%s</div>',
            $slug,
            $css,
            (int) $block->id,
            $items_html
        );
    }

    /**
     * 1件のリンクアイテムを HTML 文字列として返す。
     */
    private static function render_link( object $block, object $row ): string {
        // 表示テキスト（override_text > link_text の優先順）
        $text       = $row->override_text !== '' ? $row->override_text : $row->link_text;
        $text       = esc_html( $text );
        $track_url  = esc_url( self::tracking_url( (int) $row->assignment_id ) );
        $banner_url = esc_url( $row->banner_url );
        $advertiser = esc_attr( $row->advertiser );

        // カスタムテンプレートが設定されている場合はそちらを優先
        if ( $block->template !== '' ) {
            return str_replace(
                [ '{{url}}',    '{{text}}', '{{banner}}',  '{{advertiser}}' ],
                [ $track_url,   $text,      $banner_url,   $advertiser       ],
                $block->template
            );
        }

        // display_format に基づくデフォルトレンダリング
        switch ( $block->display_format ) {
            case 'banner':
                if ( $banner_url ) {
                    return sprintf(
                        '<a href="%s" class="aff-banner" target="_blank" rel="noopener noreferrer nofollow">'
                        . '<img src="%s" alt="%s" loading="lazy"></a>',
                        $track_url, $banner_url, esc_attr( $row->link_text )
                    );
                }
                // バナー URL がない場合はテキストリンクにフォールスルー
                // no break

            case 'button':
                return sprintf(
                    '<a href="%s" class="aff-btn" target="_blank" rel="noopener noreferrer nofollow">%s</a>',
                    $track_url, $text
                );

            case 'list':
                return sprintf(
                    '<li class="aff-list-item"><a href="%s" target="_blank" rel="noopener noreferrer nofollow">%s</a></li>',
                    $track_url, $text
                );

            case 'text_link':
            default:
                return sprintf(
                    '<a href="%s" class="aff-link" target="_blank" rel="noopener noreferrer nofollow">%s</a>',
                    $track_url, $text
                );
        }
    }

    /**
     * クリック計測用リダイレクト URL を返す。
     * 例: https://example.com/aff-click/?id=42
     */
    public static function tracking_url( int $assignment_id ): string {
        return add_query_arg( 'id', $assignment_id, home_url( '/aff-click/' ) );
    }
}
