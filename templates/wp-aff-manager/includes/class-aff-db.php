<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_DB — テーブル作成・スキーマ管理
 */
class Aff_DB {

    /** テーブル名を返す静的ヘルパー */
    public static function table( string $key ): string {
        global $wpdb;
        $map = [
            'links'       => $wpdb->prefix . 'aff_links',
            'blocks'      => $wpdb->prefix . 'aff_blocks',
            'pages'       => $wpdb->prefix . 'aff_pages',
            'assignments' => $wpdb->prefix . 'aff_assignments',
        ];
        return $map[ $key ] ?? '';
    }

    /** プラグイン有効化 or バージョンアップ時に呼ばれる */
    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── DB1: アフィリエイトリンク ──────────────────────────────────
        $sql = "CREATE TABLE " . self::table('links') . " (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            link_name       VARCHAR(255)    NOT NULL DEFAULT '',
            url             TEXT            NOT NULL,
            advertiser      VARCHAR(255)    NOT NULL DEFAULT '',
            link_text       VARCHAR(500)    NOT NULL DEFAULT '',
            banner_url      TEXT            NOT NULL DEFAULT '',
            unit_price      DECIMAL(10,2)   NOT NULL DEFAULT 0,
            commission_type ENUM('cpa','cpc','cpm','fixed') NOT NULL DEFAULT 'cpa',
            status          ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
            valid_from      DATETIME        DEFAULT NULL,
            valid_until     DATETIME        DEFAULT NULL,
            click_count     INT UNSIGNED    NOT NULL DEFAULT 0,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY valid_until (valid_until)
        ) $charset;";
        dbDelta( $sql );

        // ── DB2: 広告ブロック ──────────────────────────────────────────
        $sql = "CREATE TABLE " . self::table('blocks') . " (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            block_name      VARCHAR(255)    NOT NULL DEFAULT '',
            block_slug      VARCHAR(100)    NOT NULL DEFAULT '',
            placement_type  ENUM('content','navigation','sidebar','header','footer','widget','shortcode') NOT NULL DEFAULT 'shortcode',
            hook_name       VARCHAR(255)    NOT NULL DEFAULT '',
            max_links       TINYINT UNSIGNED NOT NULL DEFAULT 3,
            display_format  ENUM('text_link','banner','list','button') NOT NULL DEFAULT 'text_link',
            template        TEXT            NOT NULL DEFAULT '',
            css_class       VARCHAR(255)    NOT NULL DEFAULT '',
            status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY block_slug (block_slug),
            KEY status (status)
        ) $charset;";
        dbDelta( $sql );

        // ── DB3: 配信対象ページ ────────────────────────────────────────
        $sql = "CREATE TABLE " . self::table('pages') . " (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_label      VARCHAR(255)    NOT NULL DEFAULT '',
            target_type     ENUM('all','post_type','single','category','tag','url_pattern') NOT NULL DEFAULT 'all',
            post_type       VARCHAR(100)    NOT NULL DEFAULT '',
            post_id         BIGINT UNSIGNED DEFAULT NULL,
            term_id         BIGINT UNSIGNED DEFAULT NULL,
            url_pattern     VARCHAR(500)    NOT NULL DEFAULT '',
            priority        INT             NOT NULL DEFAULT 0,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY priority (priority)
        ) $charset;";
        dbDelta( $sql );

        // ── DB4: 3者連携（割り当て） ───────────────────────────────────
        $sql = "CREATE TABLE " . self::table('assignments') . " (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            block_id        BIGINT UNSIGNED NOT NULL,
            link_id         BIGINT UNSIGNED NOT NULL,
            page_id         BIGINT UNSIGNED DEFAULT NULL,
            display_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
            override_text   VARCHAR(500)    NOT NULL DEFAULT '',
            is_active       TINYINT(1)      NOT NULL DEFAULT 1,
            start_date      DATETIME        DEFAULT NULL,
            end_date        DATETIME        DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY block_page_link (block_id, page_id, link_id),
            KEY block_id (block_id),
            KEY page_id (page_id),
            KEY is_active (is_active)
        ) $charset;";
        dbDelta( $sql );

        update_option( 'aff_db_version', AFF_DB_VERSION );

        // パーマリンク（クリックトラッカー用）のリセット
        flush_rewrite_rules();
    }

    /** プラグイン削除時（uninstall.php から呼ぶ） */
    public static function uninstall(): void {
        global $wpdb;
        foreach ( [ 'assignments', 'pages', 'blocks', 'links' ] as $key ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . self::table( $key ) ); // phpcs:ignore
        }
        delete_option( 'aff_db_version' );
    }
}
