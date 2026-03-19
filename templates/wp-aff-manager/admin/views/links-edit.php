<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$id   = absint( $_GET['id'] ?? 0 );
$row  = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . Aff_DB::table('links') . " WHERE id=%d", $id ) ) : null;
$v    = fn( $col, $default = '' ) => Aff_Admin::row_val( $row, $col, $default );
?>
<div class="wrap aff-wrap">
<h1><?php echo $id ? 'リンク編集' : 'リンク新規追加'; ?></h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager' ) ); ?>">&larr; 一覧へ戻る</a>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aff-form">
<?php wp_nonce_field( 'aff_save_link' ); ?>
<input type="hidden" name="aff_action" value="save_link">
<input type="hidden" name="id" value="<?php echo (int) $id; ?>">

<table class="form-table">
<tr>
  <th><label for="link_name">リンク名 <span class="required">*</span></label></th>
  <td><input type="text" id="link_name" name="link_name" value="<?php echo $v('link_name'); ?>" class="regular-text" required>
      <p class="description">例: A8.net_〇〇転職_CPA（管理用）</p></td>
</tr>
<tr>
  <th><label for="url">アフィリエイト URL <span class="required">*</span></label></th>
  <td><input type="url" id="url" name="url" value="<?php echo $v('url'); ?>" class="large-text" required>
      <p class="description">ASP の管理画面からコピーしたトラッキング URL</p></td>
</tr>
<tr>
  <th><label for="advertiser">広告主名</label></th>
  <td><input type="text" id="advertiser" name="advertiser" value="<?php echo $v('advertiser'); ?>" class="regular-text"></td>
</tr>
<tr>
  <th><label for="link_text">表示テキスト（デフォルト）</label></th>
  <td><input type="text" id="link_text" name="link_text" value="<?php echo $v('link_text'); ?>" class="regular-text">
      <p class="description">例: 無料登録はこちら（割り当てで上書き可能）</p></td>
</tr>
<tr>
  <th><label for="banner_url">バナー画像 URL</label></th>
  <td><input type="url" id="banner_url" name="banner_url" value="<?php echo $v('banner_url'); ?>" class="large-text"></td>
</tr>
<tr>
  <th><label for="unit_price">単価（円）</label></th>
  <td><input type="number" id="unit_price" name="unit_price" value="<?php echo $v('unit_price', '0'); ?>" min="0" step="0.01" class="small-text"></td>
</tr>
<tr>
  <th><label for="commission_type">報酬タイプ</label></th>
  <td>
    <select id="commission_type" name="commission_type">
      <?php foreach ( ['cpa'=>'CPA','cpc'=>'CPC','cpm'=>'CPM','fixed'=>'固定'] as $val => $label ) : ?>
      <option value="<?php echo esc_attr($val); ?>" <?php selected( $v('commission_type','cpa'), $val ); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr>
  <th><label for="status">ステータス</label></th>
  <td>
    <select id="status" name="status">
      <?php foreach ( ['active'=>'有効','inactive'=>'無効','expired'=>'期限切れ'] as $val => $label ) : ?>
      <option value="<?php echo esc_attr($val); ?>" <?php selected( $v('status','active'), $val ); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr>
  <th>掲載期間</th>
  <td>
    <label>開始: <input type="datetime-local" name="valid_from" value="<?php echo Aff_Admin::to_datetime_local( $row->valid_from ?? null ); ?>"></label>
    &nbsp;
    <label>終了: <input type="datetime-local" name="valid_until" value="<?php echo Aff_Admin::to_datetime_local( $row->valid_until ?? null ); ?>"></label>
    <p class="description">空欄 = 無期限</p>
  </td>
</tr>
<?php if ( $row ) : ?>
<tr>
  <th>クリック数</th>
  <td><strong><?php echo number_format( (int) $row->click_count ); ?></strong> 回</td>
</tr>
<?php endif; ?>
</table>

<p class="submit">
  <button type="submit" class="button button-primary">保存</button>
  <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager' ) ); ?>" class="button">キャンセル</a>
</p>
</form>
</div>
