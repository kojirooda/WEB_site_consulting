<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_CSV — アフィリエイトリンクの CSV インポート / エクスポート
 *
 * ワークフロー:
 *  1. 管理画面からテンプレート CSV をダウンロード（ヘッダー行 + サンプル行付き）
 *  2. Excel / スプレッドシートで編集・保存
 *  3. 管理画面からアップロード → バリデーション → 結果プレビュー
 *  4. 問題なければインポート確定
 *
 * 文字コード: ダウンロードは UTF-8 BOM（Excel 互換）。
 *             アップロードは UTF-8 / Shift-JIS を自動判定して UTF-8 に変換。
 */
class Aff_CSV {

    // ── CSV カラム定義 ────────────────────────────────────────────────

    /** テンプレートのヘッダー行（順序固定） */
    const HEADERS = [
        'link_name',
        'url',
        'advertiser',
        'link_text',
        'banner_url',
        'unit_price',
        'commission_type',
        'status',
        'valid_from',
        'valid_until',
    ];

    /** 必須カラム */
    const REQUIRED = [ 'link_name', 'url' ];

    /** 許可 ENUM 値 */
    const ENUMS = [
        'commission_type' => [ 'cpa', 'cpc', 'cpm', 'fixed' ],
        'status'          => [ 'active', 'inactive', 'expired' ],
    ];

    /** 最大ファイルサイズ（バイト） */
    const MAX_FILE_SIZE = 2097152; // 2MB

    // ── テンプレートダウンロード ──────────────────────────────────────

    /**
     * テンプレート CSV をブラウザに送信して終了する。
     */
    public static function download_template(): void {
        $filename = 'aff-links-template-' . gmdate( 'Ymd' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );

        // UTF-8 BOM（Excel が文字化けせず開くために必要）
        fwrite( $out, "\xEF\xBB\xBF" );

        // ── ヘッダー行 ──
        fputcsv( $out, self::HEADERS );

        // ── 説明行（# で始まる行はインポート時にスキップ） ──
        fputcsv( $out, [
            '# 説明',
            '# アフィリエイトURL（必須）',
            '# 広告主名',
            '# 表示テキスト',
            '# バナー画像URL',
            '# 単価（数値）',
            '# cpa/cpc/cpm/fixed',
            '# active/inactive/expired',
            '# YYYY-MM-DD（空=即時）',
            '# YYYY-MM-DD（空=無期限）',
        ] );

        // ── サンプル行 ──
        fputcsv( $out, [
            'A8_転職ドラフト_CPA',
            'https://px.a8.net/svt/ejp?a8mat=XXXXXXXX',
            '転職ドラフト',
            '転職ドラフトに無料登録する',
            'https://example.com/banner.png',
            '3000',
            'cpa',
            'active',
            '',
            '2026-12-31',
        ] );

        fputcsv( $out, [
            'もっと転職_バナー_CPC',
            'https://example-asp.com/click/abc123',
            '株式会社〇〇',
            'バナー広告クリック',
            'https://example.com/banner2.gif',
            '50',
            'cpc',
            'active',
            '2026-04-01',
            '2026-06-30',
        ] );

        fclose( $out );
        exit;
    }

    // ── CSV パース & バリデーション ───────────────────────────────────

    /**
     * アップロードされた CSV ファイルをパース・バリデートする。
     *
     * @param  array $file  $_FILES['csv_file'] の要素
     * @return array{
     *   valid_rows: array[],
     *   errors: array[],
     *   total: int,
     *   skipped: int,
     * }
     */
    public static function parse_and_validate( array $file ): array {
        $result = [
            'valid_rows' => [],
            'errors'     => [],
            'total'      => 0,
            'skipped'    => 0,
        ];

        // ── ファイル自体のバリデーション ──
        $file_errors = self::validate_file( $file );
        if ( $file_errors ) {
            $result['errors'][] = [ 'row' => '—', 'field' => 'ファイル', 'messages' => $file_errors ];
            return $result;
        }

        // ── 内容を読み込み・エンコーディング変換 ──
        $content = self::read_and_convert( $file['tmp_name'] );
        if ( $content === false ) {
            $result['errors'][] = [ 'row' => '—', 'field' => 'エンコーディング', 'messages' => [ 'ファイルの文字コードを判定できませんでした（UTF-8 または Shift-JIS で保存してください）。' ] ];
            return $result;
        }

        // ── 行ごとにパース ──
        $lines  = self::csv_to_rows( $content );
        $header = null;

        // 既存 link_name 一覧（重複チェック用）
        global $wpdb;
        $existing_names = $wpdb->get_col( "SELECT link_name FROM " . Aff_DB::table('links') );
        $existing_set   = array_flip( $existing_names );

        // CSV 内での link_name 重複チェック用
        $seen_names = [];

        $data_row_num = 0; // ヘッダー・コメント行を除いたデータ行番号

        foreach ( $lines as $line_num => $row ) {
            // 空行スキップ
            if ( count( $row ) === 1 && trim( $row[0] ) === '' ) {
                continue;
            }

            // コメント行スキップ（#で始まる）
            if ( isset( $row[0] ) && strpos( ltrim( $row[0] ), '#' ) === 0 ) {
                continue;
            }

            // ── ヘッダー行の検出・検証 ──
            if ( $header === null ) {
                $normalized = array_map( 'trim', $row );
                if ( $normalized !== self::HEADERS ) {
                    // ヘッダーが違う → インポート中止
                    $result['errors'][] = [
                        'row'      => $line_num + 1,
                        'field'    => 'ヘッダー',
                        'messages' => [
                            'ヘッダー行が一致しません。テンプレート CSV を使用してください。',
                            '期待値: ' . implode( ', ', self::HEADERS ),
                            '実際値: ' . implode( ', ', $normalized ),
                        ],
                    ];
                    return $result;
                }
                $header = $normalized;
                continue;
            }

            $result['total']++;
            $data_row_num++;
            $display_row = "データ行 {$data_row_num}（CSV {$line_num}行目）";

            // カラム数チェック
            if ( count( $row ) !== count( self::HEADERS ) ) {
                $result['errors'][] = [
                    'row'      => $display_row,
                    'field'    => 'カラム数',
                    'messages' => [ sprintf( 'カラム数が正しくありません（期待: %d, 実際: %d）。', count( self::HEADERS ), count( $row ) ) ],
                ];
                $result['skipped']++;
                continue;
            }

            $raw      = array_combine( self::HEADERS, array_map( 'trim', $row ) );
            $messages = self::validate_row( $raw, $existing_set, $seen_names );

            if ( $messages ) {
                $result['errors'][] = [
                    'row'      => $display_row,
                    'field'    => $raw['link_name'] ?: '（link_name 未入力）',
                    'messages' => $messages,
                ];
                $result['skipped']++;
                continue;
            }

            // バリデーション通過
            $seen_names[ $raw['link_name'] ] = true;
            $result['valid_rows'][]          = self::sanitize_row( $raw );
        }

        if ( $header === null ) {
            $result['errors'][] = [
                'row'      => '—',
                'field'    => 'ファイル',
                'messages' => [ 'ヘッダー行が見つかりませんでした。空のファイルか、形式が正しくありません。' ],
            ];
        }

        return $result;
    }

    // ── 1行バリデーション ─────────────────────────────────────────────

    /** @return string[]  エラーメッセージ配列（空 = OK） */
    private static function validate_row( array $raw, array $existing_set, array $seen_names ): array {
        $msgs = [];

        // 必須チェック
        foreach ( self::REQUIRED as $col ) {
            if ( $raw[ $col ] === '' ) {
                $msgs[] = "`{$col}` は必須項目です。";
            }
        }

        // URL 形式
        if ( $raw['url'] !== '' && ! filter_var( $raw['url'], FILTER_VALIDATE_URL ) ) {
            $msgs[] = "`url` が有効な URL ではありません: {$raw['url']}";
        }

        // バナー URL
        if ( $raw['banner_url'] !== '' && ! filter_var( $raw['banner_url'], FILTER_VALIDATE_URL ) ) {
            $msgs[] = "`banner_url` が有効な URL ではありません: {$raw['banner_url']}";
        }

        // 単価
        if ( $raw['unit_price'] !== '' && ( ! is_numeric( $raw['unit_price'] ) || (float) $raw['unit_price'] < 0 ) ) {
            $msgs[] = "`unit_price` は 0 以上の数値を入力してください: {$raw['unit_price']}";
        }

        // ENUM チェック
        foreach ( self::ENUMS as $col => $allowed ) {
            if ( $raw[ $col ] !== '' && ! in_array( $raw[ $col ], $allowed, true ) ) {
                $msgs[] = "`{$col}` の値が正しくありません（使用可能: " . implode( '/', $allowed ) . "）: {$raw[$col]}";
            }
        }

        // 日時形式
        foreach ( [ 'valid_from', 'valid_until' ] as $col ) {
            if ( $raw[ $col ] !== '' && ! self::is_valid_date( $raw[ $col ] ) ) {
                $msgs[] = "`{$col}` は YYYY-MM-DD または YYYY-MM-DD HH:MM:SS 形式で入力してください: {$raw[$col]}";
            }
        }

        // valid_until > valid_from
        if ( $raw['valid_from'] !== '' && $raw['valid_until'] !== '' ) {
            if ( strtotime( $raw['valid_until'] ) <= strtotime( $raw['valid_from'] ) ) {
                $msgs[] = "`valid_until` は `valid_from` より後の日時を指定してください。";
            }
        }

        // CSV 内重複チェック
        if ( $raw['link_name'] !== '' && isset( $seen_names[ $raw['link_name'] ] ) ) {
            $msgs[] = "`link_name` が CSV 内で重複しています: {$raw['link_name']}";
        }

        // DB との重複チェック（警告として追記するが通過させない — 完全重複は上書きリスクがあるため）
        if ( $raw['link_name'] !== '' && isset( $existing_set[ $raw['link_name'] ] ) ) {
            $msgs[] = "`link_name` が既に登録済みです（重複登録は許可されていません）: {$raw['link_name']}";
        }

        // link_name 長さ
        if ( mb_strlen( $raw['link_name'] ) > 255 ) {
            $msgs[] = "`link_name` は 255 文字以内にしてください。";
        }

        // link_text 長さ
        if ( mb_strlen( $raw['link_text'] ) > 500 ) {
            $msgs[] = "`link_text` は 500 文字以内にしてください。";
        }

        return $msgs;
    }

    // ── サニタイズ ────────────────────────────────────────────────────

    /** バリデーション通過後の行を WordPress 挿入用にサニタイズ */
    private static function sanitize_row( array $raw ): array {
        return [
            'link_name'       => sanitize_text_field( $raw['link_name'] ),
            'url'             => esc_url_raw( $raw['url'] ),
            'advertiser'      => sanitize_text_field( $raw['advertiser'] ),
            'link_text'       => sanitize_text_field( $raw['link_text'] ),
            'banner_url'      => esc_url_raw( $raw['banner_url'] ),
            'unit_price'      => $raw['unit_price'] !== '' ? (float) $raw['unit_price'] : 0.0,
            'commission_type' => $raw['commission_type'] !== '' ? $raw['commission_type'] : 'cpa',
            'status'          => $raw['status'] !== '' ? $raw['status'] : 'active',
            'valid_from'      => self::normalize_datetime( $raw['valid_from'] ),
            'valid_until'     => self::normalize_datetime( $raw['valid_until'] ),
        ];
    }

    // ── DB 挿入 ───────────────────────────────────────────────────────

    /**
     * バリデーション済み行を一括挿入する。
     *
     * @param  array[] $rows  sanitize_row() の返り値の配列
     * @return int  挿入成功件数
     */
    public static function import_rows( array $rows ): int {
        global $wpdb;
        $count  = 0;
        $table  = Aff_DB::table('links');
        $format = [ '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ];

        foreach ( $rows as $row ) {
            $inserted = $wpdb->insert( $table, $row, $format );
            if ( $inserted ) {
                $count++;
            }
        }

        return $count;
    }

    // ── ファイルバリデーション ────────────────────────────────────────

    /** @return string[] エラーメッセージ配列（空 = OK） */
    private static function validate_file( array $file ): array {
        $msgs = [];

        if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
            $msgs[] = 'ファイルのアップロードに失敗しました（エラーコード: ' . ( $file['error'] ?? '不明' ) . '）。';
            return $msgs;
        }

        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            $msgs[] = sprintf( 'ファイルサイズが上限（%sMB）を超えています。', number_format( self::MAX_FILE_SIZE / 1048576, 0 ) );
        }

        // 拡張子チェック
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            $msgs[] = '.csv ファイルのみアップロード可能です（アップロードされたファイル: ' . esc_html( $file['name'] ) . '）。';
        }

        // MIME タイプチェック（念のため）
        $allowed_mimes = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ];
        $finfo         = finfo_open( FILEINFO_MIME_TYPE );
        $mime          = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        if ( ! in_array( $mime, $allowed_mimes, true ) ) {
            $msgs[] = 'CSV ファイルとして認識できません（MIME: ' . esc_html( $mime ) . '）。';
        }

        return $msgs;
    }

    // ── ユーティリティ ────────────────────────────────────────────────

    /**
     * ファイル内容を読み込み、UTF-8 に変換して返す。
     * 失敗時は false を返す。
     */
    private static function read_and_convert( string $tmp_path ) {
        $content = file_get_contents( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( $content === false ) {
            return false;
        }

        // UTF-8 BOM を除去
        if ( str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
            $content = substr( $content, 3 );
        }

        // エンコーディング検出
        $encoding = mb_detect_encoding( $content, [ 'UTF-8', 'SJIS', 'EUC-JP', 'JIS' ], true );
        if ( $encoding === false ) {
            return false;
        }

        if ( $encoding !== 'UTF-8' ) {
            $content = mb_convert_encoding( $content, 'UTF-8', $encoding );
        }

        return $content;
    }

    /**
     * 文字列を CSV 行配列に変換する（改行コード正規化済み）。
     *
     * @return array[]
     */
    private static function csv_to_rows( string $content ): array {
        // 改行コード正規化
        $content = str_replace( [ "\r\n", "\r" ], "\n", $content );

        $rows   = [];
        $handle = fopen( 'data://text/plain;base64,' . base64_encode( $content ), 'r' );
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $rows[] = $row;
        }
        fclose( $handle );

        return $rows;
    }

    /**
     * 日付文字列を `YYYY-MM-DD HH:MM:SS` に正規化する。
     * 入力が空の場合は null を返す。
     */
    private static function normalize_datetime( string $val ): ?string {
        if ( $val === '' ) {
            return null;
        }
        // YYYY-MM-DD のみの場合は時刻を補完
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
            $val .= ' 00:00:00';
        }
        return $val;
    }

    /** 日付文字列が有効かチェック */
    private static function is_valid_date( string $val ): bool {
        // YYYY-MM-DD または YYYY-MM-DD HH:MM:SS
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $val ) ) {
            return strtotime( $val ) !== false;
        }
        return false;
    }
}
