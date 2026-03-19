<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$id  = absint( $_GET['id'] ?? 0 );
$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . Aff_DB::table('blocks') . " WHERE id=%d", $id ) ) : null;
$v   = fn( $col, $default = '' ) => Aff_Admin::row_val( $row, $col, $default );
?>
<div class="wrap aff-wrap">
<h1><?php echo $id ? 'ブロック編集' : 'ブロック新規追加'; ?></h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-blocks' ) ); ?>">&larr; 一覧へ戻る</a>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aff-form">
<?php wp_nonce_field( 'aff_save_block' ); ?>
<input type="hidden" name="aff_action" value="save_block">
<input type="hidden" name="id" value="<?php echo (int) $id; ?>">

<table class="form-table">
<tr>
  <th><label for="block_name">ブロック名 <span class="required">*</span></label></th>
  <td><input type="text" id="block_name" name="block_name" value="<?php echo $v('block_name'); ?>" class="regular-text" required>
      <p class="description">例: 記事下CTA、グローバルナビ右端（管理用）</p></td>
</tr>
<tr>
  <th><label for="block_slug">スラッグ <span class="required">*</span></label></th>
  <td><input type="text" id="block_slug" name="block_slug" value="<?php echo $v('block_slug'); ?>" class="regular-text" required pattern="[a-z0-9\-]+">
      <p class="description">例: article-bottom（英小文字・数字・ハイフンのみ。ショートコードで使用）</p>
      <?php if ( $row ) : ?>
      <p class="description">ショートコード: <code>[aff_block slug="<?php echo esc_attr($row->block_slug); ?>"]</code></p>
      <?php endif; ?></td>
</tr>
<tr>
  <th><label for="placement_type">配置タイプ</label></th>
  <td>
    <select id="placement_type" name="placement_type">
      <?php $pts = ['content'=>'本文内','navigation'=>'ナビゲーション','sidebar'=>'サイドバー','header'=>'ヘッダー','footer'=>'フッター','widget'=>'ウィジェット','shortcode'=>'ショートコード（手動）'];
      foreach ( $pts as $val => $label ) : ?>
      <option value="<?php echo esc_attr($val); ?>" <?php selected( $v('placement_type','shortcode'), $val ); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description">「ショートコード」以外は WordPress フックで自動挿入されます。</p>
  </td>
</tr>
<tr>
  <th><label for="hook_name">フック名 / メニュー位置</label></th>
  <td><input type="text" id="hook_name" name="hook_name" value="<?php echo $v('hook_name'); ?>" class="regular-text">
      <p class="description">例: after_content（本文後）/ before_content（本文前）/ primary（ナビ名）</p></td>
</tr>
<tr>
  <th><label for="max_links">最大表示リンク数</label></th>
  <td><input type="number" id="max_links" name="max_links" value="<?php echo $v('max_links','3'); ?>" min="1" max="10" class="small-text"></td>
</tr>
<tr>
  <th><label for="display_format">表示形式</label></th>
  <td>
    <select id="display_format" name="display_format">
      <?php foreach ( ['text_link'=>'テキストリンク','banner'=>'バナー','list'=>'リスト','button'=>'ボタン'] as $val => $label ) : ?>
      <option value="<?php echo esc_attr($val); ?>" <?php selected( $v('display_format','text_link'), $val ); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description">カスタムテンプレートを設定した場合はそちらが優先されます。</p>
  </td>
</tr>
<tr>
  <th><label for="template">カスタム HTML テンプレート</label></th>
  <td>
    <textarea id="template" name="template" rows="5" class="large-text"><?php echo $row ? esc_textarea( $row->template ) : ''; ?></textarea>
    <p class="description">
      使用可能なプレースホルダー: <code>{{url}}</code> <code>{{text}}</code> <code>{{banner}}</code> <code>{{advertiser}}</code><br>
      例: <code>&lt;a href="{{url}}" class="my-btn"&gt;{{text}}&lt;/a&gt;</code>
    </p>
  </td>
</tr>
<tr>
  <th><label for="css_class">CSS クラス</label></th>
  <td><input type="text" id="css_class" name="css_class" value="<?php echo $v('css_class'); ?>" class="regular-text">
      <p class="description">ブロックラッパー div に付与する追加クラス</p></td>
</tr>
<tr>
  <th><label for="status">ステータス</label></th>
  <td>
    <select id="status" name="status">
      <option value="active"   <?php selected( $v('status','active'), 'active' ); ?>>有効</option>
      <option value="inactive" <?php selected( $v('status','active'), 'inactive' ); ?>>無効</option>
    </select>
  </td>
</tr>
</table>

<p class="submit">
  <button type="submit" class="button button-primary">保存</button>
  <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-blocks' ) ); ?>" class="button">キャンセル</a>
</p>
</form>
</div>
