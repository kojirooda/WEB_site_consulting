<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_Query — ページマッチング & アサインメント取得
 *
 * 核心ロジック:
 *  1. 現在のページが wp_aff_pages のどの条件にマッチするか判定（priority 順）
 *  2. マッチした page_id（またはデフォルト NULL）でアサインメントを取得
 *  3. リンクの有効期間・ステータスも JOIN でフィルタリング
 */
class Aff_Query {

    /** ページマッチ結果キャッシュ（false = 未解決, null = マッチなし, int = page_id） */
    private static $matched_page_id = false;

    // ────────────────────────────────────────────────────────────────
    // ページマッチング
    // ────────────────────────────────────────────────────────────────

    /**
     * 現在リクエストにマッチする wp_aff_pages の id を返す。
     * マッチなしの場合は null を返す（NULL = デフォルト割り当てのみ使用）。
     */
    public static function resolve_page_id(): ?int {
        if ( self::$matched_page_id !== false ) {
            return self::$matched_page_id;
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM " . Aff_DB::table('pages') . " ORDER BY priority DESC",
            ARRAY_A
        );

        self::$matched_page_id = null;

        foreach ( $rows as $row ) {
            if ( self::row_matches( $row ) ) {
                self::$matched_page_id = (int) $row['id'];
                break;
            }
        }

        return self::$matched_page_id;
    }

    /** キャッシュをリセット（テスト用） */
    public static function reset_cache(): void {
        self::$matched_page_id = false;
    }

    /** 1行の wp_aff_pages が現在のリクエストにマッチするか */
    private static function row_matches( array $row ): bool {
        switch ( $row['target_type'] ) {
            case 'all':
                return true;

            case 'post_type':
                return is_singular( $row['post_type'] );

            case 'single':
                return is_singular() && (int) get_queried_object_id() === (int) $row['post_id'];

            case 'category':
                if ( is_category( (int) $row['term_id'] ) ) {
                    return true;
                }
                return is_singular() && has_term( (int) $row['term_id'], 'category' );

            case 'tag':
                if ( is_tag( (int) $row['term_id'] ) ) {
                    return true;
                }
                return is_singular() && has_term( (int) $row['term_id'], 'post_tag' );

            case 'url_pattern':
                $request_uri = isset( $_SERVER['REQUEST_URI'] )
                    ? wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
                    : '';
                return fnmatch( $row['url_pattern'], $request_uri );

            default:
                return false;
        }
    }

    // ────────────────────────────────────────────────────────────────
    // アサインメント取得
    // ────────────────────────────────────────────────────────────────

    /**
     * 指定ブロックに表示するリンク一覧を返す。
     * 返り値の各要素は link + assignment の結合行（stdClass）。
     *
     * @param  stdClass $block  wp_aff_blocks の行
     * @return stdClass[]
     */
    public static function get_links_for_block( object $block ): array {
        global $wpdb;

        $page_id  = self::resolve_page_id();
        $now      = current_time( 'mysql' );
        $t_a      = Aff_DB::table('assignments');
        $t_l      = Aff_DB::table('links');
        $max      = (int) $block->max_links;

        // page_id が NULL の行（デフォルト）と、マッチした page_id の行を両方取得。
        // ORDER: page_id が非 NULL（ページ固有）を先に、次にデフォルトを並べて
        // max_links で打ち切ることで「ページ固有が優先」を実現。
        if ( $page_id !== null ) {
            $sql = $wpdb->prepare(
                "SELECT a.id AS assignment_id,
                        a.display_order,
                        a.override_text,
                        a.page_id,
                        l.id        AS link_id,
                        l.url,
                        l.link_text,
                        l.banner_url,
                        l.advertiser,
                        l.commission_type,
                        l.unit_price
                 FROM   {$t_a} a
                 INNER JOIN {$t_l} l ON l.id = a.link_id
                 WHERE  a.block_id  = %d
                   AND  (a.page_id IS NULL OR a.page_id = %d)
                   AND  a.is_active = 1
                   AND  (a.start_date IS NULL OR a.start_date <= %s)
                   AND  (a.end_date   IS NULL OR a.end_date   >= %s)
                   AND  l.status = 'active'
                   AND  (l.valid_from  IS NULL OR l.valid_from  <= %s)
                   AND  (l.valid_until IS NULL OR l.valid_until >= %s)
                 ORDER BY (a.page_id IS NULL) ASC, a.display_order ASC
                 LIMIT %d",
                $block->id, $page_id,
                $now, $now, $now, $now,
                $max
            );
        } else {
            // ページ条件がマッチしなかった場合はデフォルト（page_id = NULL）のみ
            $sql = $wpdb->prepare(
                "SELECT a.id AS assignment_id,
                        a.display_order,
                        a.override_text,
                        a.page_id,
                        l.id        AS link_id,
                        l.url,
                        l.link_text,
                        l.banner_url,
                        l.advertiser,
                        l.commission_type,
                        l.unit_price
                 FROM   {$t_a} a
                 INNER JOIN {$t_l} l ON l.id = a.link_id
                 WHERE  a.block_id  = %d
                   AND  a.page_id IS NULL
                   AND  a.is_active = 1
                   AND  (a.start_date IS NULL OR a.start_date <= %s)
                   AND  (a.end_date   IS NULL OR a.end_date   >= %s)
                   AND  l.status = 'active'
                   AND  (l.valid_from  IS NULL OR l.valid_from  <= %s)
                   AND  (l.valid_until IS NULL OR l.valid_until >= %s)
                 ORDER BY a.display_order ASC
                 LIMIT %d",
                $block->id,
                $now, $now, $now, $now,
                $max
            );
        }

        return $wpdb->get_results( $sql ) ?: [];
    }

    // ────────────────────────────────────────────────────────────────
    // 単体取得ヘルパー
    // ────────────────────────────────────────────────────────────────

    public static function get_block_by_slug( string $slug ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Aff_DB::table('blocks') . " WHERE block_slug = %s AND status = 'active' LIMIT 1",
            $slug
        ) ) ?: null;
    }

    public static function get_block_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Aff_DB::table('blocks') . " WHERE id = %d LIMIT 1",
            $id
        ) ) ?: null;
    }

    public static function get_blocks_by_placement( string $placement_type ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . Aff_DB::table('blocks') . " WHERE placement_type = %s AND status = 'active'",
            $placement_type
        ) ) ?: [];
    }

    public static function get_assignment_for_tracker( int $assignment_id ): ?object {
        global $wpdb;
        $t_a = Aff_DB::table('assignments');
        $t_l = Aff_DB::table('links');
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT a.id AS assignment_id, l.id AS link_id, l.url, l.status AS link_status
             FROM {$t_a} a
             INNER JOIN {$t_l} l ON l.id = a.link_id
             WHERE a.id = %d AND a.is_active = 1 AND l.status = 'active'
             LIMIT 1",
            $assignment_id
        ) ) ?: null;
    }
}
