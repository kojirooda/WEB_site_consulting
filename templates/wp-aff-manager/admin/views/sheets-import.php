<?php
defined( 'ABSPATH' ) || exit;

$current_url    = Aff_Sheets::get_url();
$csv_url        = $current_url ? Aff_Sheets::to_csv_url( $current_url ) : '';
$sheets_result  = $GLOBALS['aff_sheets_result'] ?? null;
?>
<div class="wrap aff-wrap">
<h1>Google Sheets 連携</h1>

<?php Aff_Admin::saved_notice(); ?>

<?php if ( $sheets_result && isset( $sheets_result['error'] ) ) : ?>
<div class="notice notice-error"><p><strong>取り込みエラー:</strong> <?php echo esc_html( $sheets_result['error'] ); ?></p></div>
<?php endif; ?>

<!-- ===== STEP 1: 事前準備ガイド ===== -->
<div class="aff-import-section">
  <h2>① スプレッドシートの準備</h2>
  <ol class="aff-import-guide">
    <li>
      <strong>CSV テンプレートの列構成でシートを作成してください。</strong><br>
      1行目をヘッダー行（<code>link_name,url,advertiser,...</code>）にして、2行目以降にデータを入力します。<br>
      <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp-aff-manager-import&aff_csv_dl=1' ), 'aff_csv_download' ) ); ?>">
        ↓ CSV テンプレートをダウンロードして列構成を確認
      </a>
    </li>
    <li>
      <strong>スプレッドシートを公開設定にしてください。</strong><br>
      右上の「共有」ボタン → 「リンクを知っている全員」→「閲覧者」に設定 → リンクをコピー
    </li>
    <li>
      <strong>コピーしたリンクを下の URL 欄に貼り付けて保存してください。</strong>
    </li>
  </ol>

  <details class="aff-import-section" style="margin-top:8px;">
    <summary style="cursor:pointer;font-weight:600;">CSV 列仕様を確認する</summary>
    <table class="aff-csv-spec widefat striped" style="margin-top:8px;">
      <thead><tr><th>列名</th><th>必須</th><th>説明</th><th>例</th></tr></thead>
      <tbody>
        <tr><td><code>link_name</code></td>  <td>✓</td><td>管理用リンク名（一意）</td>       <td>A8_転職ドラフト_CPA</td></tr>
        <tr><td><code>url</code></td>         <td>✓</td><td>アフィリエイト URL</td>           <td>https://px.a8.net/...</td></tr>
        <tr><td><code>advertiser</code></td>  <td></td> <td>広告主名</td>                     <td>転職ドラフト</td></tr>
        <tr><td><code>link_text</code></td>   <td></td> <td>デフォルト表示テキスト</td>       <td>無料登録はこちら</td></tr>
        <tr><td><code>banner_url</code></td>  <td></td> <td>バナー画像 URL</td>               <td>https://example.com/b.png</td></tr>
        <tr><td><code>unit_price</code></td>  <td></td> <td>単価（数値）</td>                 <td>3000</td></tr>
        <tr><td><code>commission_type</code></td><td></td><td>cpa / cpc / cpm / fixed</td>  <td>cpa</td></tr>
        <tr><td><code>status</code></td>      <td></td> <td>active / inactive / expired</td><td>active</td></tr>
        <tr><td><code>valid_from</code></td>  <td></td> <td>掲載開始日 YYYY-MM-DD</td>       <td>2026-04-01</td></tr>
        <tr><td><code>valid_until</code></td> <td></td> <td>掲載終了日 YYYY-MM-DD</td>       <td>2026-12-31</td></tr>
      </tbody>
    </table>
  </details>
</div>

<!-- ===== STEP 2: URL 登録フォーム ===== -->
<div class="aff-import-section">
  <h2>② スプレッドシート URL を登録</h2>
  <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
    <?php wp_nonce_field( 'aff_save_sheets_url' ); ?>
    <input type="hidden" name="aff_action" value="save_sheets_url">
    <table class="form-table">
      <tr>
        <th><label for="sheets_url">スプレッドシート URL</label></th>
        <td>
          <input type="url" id="sheets_url" name="sheets_url"
                 value="<?php echo esc_attr( $current_url ); ?>"
                 class="large-text"
                 placeholder="https://docs.google.com/spreadsheets/d/...">
          <p class="description">
            Google スプレッドシートの共有 URL を貼り付けてください。<br>
            例: <code>https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5.../edit#gid=0</code>
          </p>
          <?php if ( $csv_url ) : ?>
          <p class="description">
            取得先 CSV URL:
            <a href="<?php echo esc_url( $csv_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $csv_url ); ?></a>
          </p>
          <?php endif; ?>
        </td>
      </tr>
    </table>
    <p class="submit">
      <button type="submit" class="button button-secondary">URL を保存</button>
    </p>
  </form>
</div>

<!-- ===== STEP 3: 今すぐ取り込む ===== -->
<div class="aff-import-section">
  <h2>③ 今すぐ取り込む</h2>
  <?php if ( $current_url ) : ?>
  <p>
    登録済みシート:
    <a href="<?php echo esc_url( $current_url ); ?>" target="_blank" rel="noopener">
      <?php echo esc_html( $current_url ); ?>
    </a>
  </p>
  <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
    <?php wp_nonce_field( 'aff_import_from_sheets' ); ?>
    <input type="hidden" name="aff_action" value="import_from_sheets">
    <p>
      <button type="submit" class="button button-primary">
        ↓ 今すぐスプレッドシートから取り込む
      </button>
    </p>
    <p class="description">
      スプレッドシートのデータをバリデーションして、問題がなければデータベースに追加します。<br>
      すでに登録済みの <code>link_name</code> は重複エラーとなりスキップされます（上書きはされません）。
    </p>
  </form>
  <?php else : ?>
  <p class="description">先に「② スプレッドシート URL を登録」を完了してください。</p>
  <?php endif; ?>
</div>

<?php if ( $sheets_result && ! isset( $sheets_result['error'] ) ) : ?>
<!-- ===== 取り込み結果 ===== -->
<div class="aff-import-section">
  <h2>取り込み結果</h2>
  <div class="aff-result-badges">
    <span class="aff-result-badge aff-result-badge-total">
      合計 <strong><?php echo (int) $sheets_result['total']; ?></strong> 行
    </span>
    <span class="aff-result-badge aff-result-badge-imported">
      取り込み <strong><?php echo (int) $sheets_result['imported']; ?></strong> 件
    </span>
    <span class="aff-result-badge aff-result-badge-skipped">
      スキップ <strong><?php echo (int) $sheets_result['skipped']; ?></strong> 件
    </span>
  </div>

  <?php if ( (int) $sheets_result['imported'] > 0 ) : ?>
  <div class="notice notice-success inline">
    <p><?php echo (int) $sheets_result['imported']; ?> 件のリンクを取り込みました。
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager' ) ); ?>">リンク一覧で確認 &rarr;</a>
    </p>
  </div>
  <?php endif; ?>

  <?php if ( ! empty( $sheets_result['errors'] ) ) : ?>
  <h3>エラー詳細</h3>
  <table class="aff-error-table widefat striped">
    <thead>
      <tr><th>行</th><th>項目</th><th>エラー内容</th></tr>
    </thead>
    <tbody>
      <?php foreach ( $sheets_result['errors'] as $err ) : ?>
      <tr>
        <td><?php echo esc_html( $err['row'] ); ?></td>
        <td><?php echo esc_html( $err['field'] ); ?></td>
        <td>
          <ul class="aff-error-list">
            <?php foreach ( $err['messages'] as $msg ) : ?>
            <li><?php echo esc_html( $msg ); ?></li>
            <?php endforeach; ?>
          </ul>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- .wrap -->
