<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aff_Sheets — Google スプレッドシート連携
 *
 * 公開設定のスプレッドシートを CSV エクスポート URL 経由で取得し、
 * Aff_CSV のバリデーション・インポートロジックを再利用して取り込む。
 *
 * 前提条件:
 *   - スプレッドシートは「リンクを知っている全員が閲覧可」に設定されていること
 *   - 列構成が CSV テンプレートと同じであること（1行目はヘッダー行）
 *
 * URL 変換例:
 *   入力: https://docs.google.com/spreadsheets/d/{ID}/edit#gid=0
 *   出力: https://docs.google.com/spreadsheets/d/{ID}/export?format=csv&gid=0
 */
class Aff_Sheets {

    /** WordPress option キー */
    const OPTION_URL = 'aff_sheets_url';

    // ── URL 管理 ──────────────────────────────────────────────────────

    /** 保存されている Google Sheets URL を返す */
    public static function get_url(): string {
        return (string) get_option( self::OPTION_URL, '' );
    }

    /**
     * Google Sheets URL を保存する。
     * Google Sheets の URL 以外は false を返す（空文字は「未設定」として保存）。
     */
    public static function save_url( string $url ): bool {
        if ( $url !== '' && ! preg_match( '#^https://docs\.google\.com/spreadsheets/#', $url ) ) {
            return false;
        }
        update_option( self::OPTION_URL, sanitize_url( $url ) );
        return true;
    }

    /**
     * Google Sheets 共有 URL を CSV エクスポート URL に変換する。
     *
     * 対応フォーマット:
     *   https://docs.google.com/spreadsheets/d/{ID}/edit#gid={GID}
     *   https://docs.google.com/spreadsheets/d/{ID}/edit?usp=sharing
     *   https://docs.google.com/spreadsheets/d/{ID}/pub?gid={GID}&...
     *
     * @return string  変換後 URL。ID が取得できない場合は空文字。
     */
    public static function to_csv_url( string $url ): string {
        if ( ! preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m ) ) {
            return '';
        }
        $sheet_id = $m[1];

        // gid パラメータ（シートタブ番号）— デフォルトは 0
        $gid = 0;
        if ( preg_match( '#[?&#]gid=(\d+)#', $url, $mg ) ) {
            $gid = (int) $mg[1];
        }

        return "https://docs.google.com/spreadsheets/d/{$sheet_id}/export?format=csv&gid={$gid}";
    }

    // ── 取り込み ──────────────────────────────────────────────────────

    /**
     * スプレッドシートから CSV を取得してバリデーション・インポートを実行する。
     *
     * @return array{
     *   valid_rows: array[],
     *   errors:     array[],
     *   total:      int,
     *   skipped:    int,
     *   imported:   int,
     *   error?:     string,   // 致命的エラー時のみ設定
     * }
     */
    public static function fetch_and_import(): array {
        $url = self::get_url();
        if ( $url === '' ) {
            return [ 'error' => 'スプレッドシート URL が設定されていません。' ];
        }

        $csv_url = self::to_csv_url( $url );
        if ( $csv_url === '' ) {
            return [ 'error' => 'スプレッドシート URL の形式が正しくありません。' ];
        }

        // ── CSV 取得 ──
        $response = wp_remote_get( $csv_url, [
            'timeout'    => 30,
            'user-agent' => 'WP-Aff-Manager/' . AFF_VERSION,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => '通信エラー: ' . $response->get_error_message() ];
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            return [
                'error' => sprintf(
                    'スプレッドシートの取得に失敗しました（HTTP %d）。'
                    . 'シートが「リンクを知っている全員が閲覧可」に設定されているか確認してください。',
                    $http_code
                ),
            ];
        }

        $csv_body = wp_remote_retrieve_body( $response );
        if ( trim( $csv_body ) === '' ) {
            return [ 'error' => 'スプレッドシートが空です。' ];
        }

        // ── バリデーション（Aff_CSV 再利用） ──
        $result = Aff_CSV::parse_and_validate_from_string( $csv_body );

        // ── インポート ──
        $imported = 0;
        if ( ! empty( $result['valid_rows'] ) ) {
            $imported = Aff_CSV::import_rows( $result['valid_rows'] );
        }
        $result['imported'] = $imported;

        return $result;
    }
}
