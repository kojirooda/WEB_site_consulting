<?php
defined( 'ABSPATH' ) || exit;

/** @var array|null $import_result  handle_post() が設定する処理結果 */
$result = $GLOBALS['aff_import_result'] ?? null;
?>
<div class="wrap aff-wrap">
<h1 class="wp-heading-inline">リンク CSV インポート</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager' ) ); ?>">&larr; リンク一覧へ戻る</a>

<!-- ── 手順ガイド ── -->
<div class="aff-import-guide">
  <h2>📋 インポート手順</h2>
  <ol>
    <li>
      <strong>テンプレート CSV をダウンロード</strong><br>
      下のボタンからテンプレートを取得し、Excel またはスプレッドシートで開きます。
    </li>
    <li>
      <strong>データを入力・編集して保存</strong><br>
      サンプル行と説明行（# で始まる行）は削除しても、残したままでもどちらでも構いません。<br>
      ファイルは <code>UTF-8</code> または <code>Shift-JIS</code> で保存してください。
    </li>
    <li>
      <strong>CSV をアップロードしてエラーチェック</strong><br>
      下のフォームからファイルを選択してアップロードすると、内容が検証されます。
    </li>
    <li>
      <strong>エラーがなければインポート確定</strong><br>
      問題のある行はスキップされます。エラー一覧を確認し、修正後に再アップロードしてください。
    </li>
  </ol>
</div>

<!-- ── テンプレートダウンロード ── -->
<div class="aff-import-section">
  <h2>STEP 1 — テンプレートダウンロード</h2>
  <?php
  $dl_nonce = wp_create_nonce( 'aff_csv_download' );
  $dl_url   = admin_url( "admin.php?page=wp-aff-manager-import&aff_csv_dl=1&_wpnonce={$dl_nonce}" );
  ?>
  <a href="<?php echo esc_url( $dl_url ); ?>" class="button button-secondary aff-dl-btn">
    ⬇ テンプレート CSV をダウンロード
  </a>
  <p class="description">ファイル名: <code>aff-links-template-YYYYMMDD.csv</code>（UTF-8 BOM 付き）</p>

  <details class="aff-csv-spec">
    <summary>📄 CSV カラム仕様</summary>
    <table class="widefat aff-spec-table">
      <thead><tr><th>カラム名</th><th>必須</th><th>型・形式</th><th>説明</th></tr></thead>
      <tbody>
        <tr><td><code>link_name</code></td>  <td>✓</td><td>文字列（255文字以内）</td><td>管理用の名前（例: A8_転職ドラフト_CPA）</td></tr>
        <tr><td><code>url</code></td>        <td>✓</td><td>URL</td>              <td>アフィリエイトトラッキング URL</td></tr>
        <tr><td><code>advertiser</code></td> <td></td> <td>文字列</td>           <td>広告主名（例: 転職ドラフト株式会社）</td></tr>
        <tr><td><code>link_text</code></td>  <td></td> <td>文字列（500文字以内）</td><td>デフォルト表示テキスト</td></tr>
        <tr><td><code>banner_url</code></td> <td></td> <td>URL</td>              <td>バナー画像の URL（任意）</td></tr>
        <tr><td><code>unit_price</code></td> <td></td> <td>数値（≥0）</td>       <td>報酬単価（円）</td></tr>
        <tr><td><code>commission_type</code></td><td></td><td>cpa / cpc / cpm / fixed</td><td>報酬タイプ（空欄 = cpa）</td></tr>
        <tr><td><code>status</code></td>     <td></td> <td>active / inactive / expired</td><td>ステータス（空欄 = active）</td></tr>
        <tr><td><code>valid_from</code></td> <td></td> <td>YYYY-MM-DD</td>       <td>掲載開始日（空欄 = 即時）</td></tr>
        <tr><td><code>valid_until</code></td><td></td> <td>YYYY-MM-DD</td>       <td>掲載終了日（空欄 = 無期限）</td></tr>
      </tbody>
    </table>
  </details>
</div>

<!-- ── アップロードフォーム ── -->
<div class="aff-import-section">
  <h2>STEP 2 — CSV アップロード</h2>
  <form method="POST" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aff-form">
    <?php wp_nonce_field( 'aff_import_links_csv' ); ?>
    <input type="hidden" name="aff_action" value="import_links_csv">

    <table class="form-table">
      <tr>
        <th><label for="csv_file">CSV ファイル <span class="required">*</span></label></th>
        <td>
          <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
          <p class="description">ファイル形式: .csv / 最大サイズ: 2MB / 文字コード: UTF-8 または Shift-JIS</p>
        </td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" class="button button-primary">アップロードしてチェック</button>
    </p>
  </form>
</div>

<?php if ( $result !== null ) : ?>
<!-- ── インポート結果 ── -->
<div class="aff-import-section">
  <h2>STEP 3 — チェック結果</h2>

  <!-- サマリーバッジ -->
  <div class="aff-result-summary">
    <span class="aff-result-badge aff-result-badge--total">
      合計: <?php echo (int) $result['total']; ?> 行
    </span>
    <span class="aff-result-badge aff-result-badge--success">
      ✓ インポート済: <?php echo (int) $result['imported']; ?> 件
    </span>
    <?php if ( $result['skipped'] > 0 ) : ?>
    <span class="aff-result-badge aff-result-badge--error">
      ✗ スキップ: <?php echo (int) $result['skipped']; ?> 行
    </span>
    <?php endif; ?>
  </div>

  <?php if ( $result['imported'] > 0 ) : ?>
  <div class="notice notice-success inline">
    <p><?php echo (int) $result['imported']; ?> 件のリンクをインポートしました。
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager' ) ); ?>">リンク一覧で確認する →</a>
    </p>
  </div>
  <?php endif; ?>

  <!-- エラー詳細 -->
  <?php if ( ! empty( $result['errors'] ) ) : ?>
  <div class="notice notice-warning inline">
    <p>以下の行でエラーが発生しました。CSV を修正して再アップロードしてください。</p>
  </div>
  <table class="widefat aff-table aff-error-table">
    <thead>
      <tr><th>行</th><th>link_name / 対象</th><th>エラー内容</th></tr>
    </thead>
    <tbody>
      <?php foreach ( $result['errors'] as $err ) : ?>
      <tr>
        <td class="aff-error-row"><?php echo esc_html( $err['row'] ); ?></td>
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

  <?php if ( $result['total'] === 0 && empty( $result['errors'] ) ) : ?>
  <p class="description">データ行が見つかりませんでした。ヘッダー行とデータ行を含む CSV を用意してください。</p>
  <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- .wrap -->
