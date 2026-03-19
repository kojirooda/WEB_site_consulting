<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$id  = absint( $_GET['id'] ?? 0 );
$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . Aff_DB::table('pages') . " WHERE id=%d", $id ) ) : null;
$v   = fn( $col, $default = '' ) => $row ? esc_attr( $row->$col ) : $default;
?>
<div class="wrap aff-wrap">
<h1><?php echo $id ? 'ページ条件編集' : 'ページ条件新規追加'; ?></h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-pages' ) ); ?>">&larr; 一覧へ戻る</a>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aff-form">
<?php wp_nonce_field( 'aff_save_page' ); ?>
<input type="hidden" name="aff_action" value="save_page">
<input type="hidden" name="id" value="<?php echo (int) $id; ?>">

<table class="form-table">
<tr>
  <th><label for="page_label">ラベル <span class="required">*</span></label></th>
  <td><input type="text" id="page_label" name="page_label" value="<?php echo $v('page_label'); ?>" class="regular-text" required>
      <p class="description">例: 全記事（デフォルト）/ 転職カテゴリ記事 / LP_転職おすすめ</p></td>
</tr>
<tr>
  <th><label for="target_type">条件タイプ <span class="required">*</span></label></th>
  <td>
    <select id="target_type" name="target_type" onchange="affToggleFields(this.value)">
      <?php $tts = ['all'=>'全ページ','post_type'=>'投稿タイプ','single'=>'個別ページ','category'=>'カテゴリ','tag'=>'タグ','url_pattern'=>'URL パターン'];
      foreach ( $tts as $val => $label ) : ?>
      <option value="<?php echo esc_attr($val); ?>" <?php selected( $v('target_type','all'), $val ); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr id="field_post_type" style="display:none">
  <th><label for="post_type">投稿タイプ</label></th>
  <td><input type="text" id="post_type" name="post_type" value="<?php echo $v('post_type'); ?>" class="regular-text">
      <p class="description">例: post / page / custom_post_type</p></td>
</tr>
<tr id="field_post_id" style="display:none">
  <th><label for="post_id">投稿 ID</label></th>
  <td><input type="number" id="post_id" name="post_id" value="<?php echo $v('post_id','0'); ?>" min="0" class="small-text">
      <p class="description">WordPress 管理画面の投稿 URL で確認できる数値 ID</p></td>
</tr>
<tr id="field_term_id" style="display:none">
  <th><label for="term_id">カテゴリ / タグ ID</label></th>
  <td><input type="number" id="term_id" name="term_id" value="<?php echo $v('term_id','0'); ?>" min="0" class="small-text">
      <p class="description">WordPress 管理画面のカテゴリ / タグ編集 URL で確認できる数値 ID</p></td>
</tr>
<tr id="field_url_pattern" style="display:none">
  <th><label for="url_pattern">URL パターン</label></th>
  <td><input type="text" id="url_pattern" name="url_pattern" value="<?php echo $v('url_pattern'); ?>" class="regular-text">
      <p class="description">glob 形式。例: /tensyoku/* （ワイルドカード * 使用可）</p></td>
</tr>
<tr>
  <th><label for="priority">優先度</label></th>
  <td><input type="number" id="priority" name="priority" value="<?php echo $v('priority','0'); ?>" class="small-text">
      <p class="description">数値が大きい方が優先されます。推奨値 → 全ページ: 0 / カテゴリ: 10 / 個別ページ: 20</p></td>
</tr>
</table>

<p class="submit">
  <button type="submit" class="button button-primary">保存</button>
  <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-pages' ) ); ?>" class="button">キャンセル</a>
</p>
</form>

<script>
function affToggleFields(type) {
    var fields = { post_type: ['post_type'], single: ['post_id'], category: ['term_id'], tag: ['term_id'], url_pattern: ['url_pattern'] };
    ['post_type','post_id','term_id','url_pattern'].forEach(function(f){ document.getElementById('field_'+f).style.display='none'; });
    if (fields[type]) { fields[type].forEach(function(f){ document.getElementById('field_'+f).style.display=''; }); }
}
affToggleFields('<?php echo esc_js( $v('target_type','all') ); ?>');
</script>
</div>
