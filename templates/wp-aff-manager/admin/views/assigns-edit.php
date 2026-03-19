<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$id  = absint( $_GET['id'] ?? 0 );
$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . Aff_DB::table('assignments') . " WHERE id=%d", $id ) ) : null;
$v   = fn( $col, $default = '' ) => Aff_Admin::row_val( $row, $col, $default );

$blocks = $wpdb->get_results( "SELECT id, block_name, block_slug FROM " . Aff_DB::table('blocks') . " ORDER BY block_name" );
$links  = $wpdb->get_results( "SELECT id, link_name, advertiser, status FROM " . Aff_DB::table('links') . " ORDER BY link_name" );
$pages  = $wpdb->get_results( "SELECT id, page_label, priority FROM " . Aff_DB::table('pages') . " ORDER BY priority DESC, page_label" );
?>
<div class="wrap aff-wrap">
<h1><?php echo $id ? '割り当て編集' : '新規割り当て'; ?></h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-assigns' ) ); ?>">&larr; 一覧へ戻る</a>

<div class="aff-assign-guide">
  <strong>割り当ての仕組み:</strong>
  「どのブロックに」×「どのページで」×「どのリンクを」の 3 点を選んで登録します。<br>
  ページ条件を「指定なし（デフォルト）」にするとすべてのページで表示されます。
</div>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aff-form">
<?php wp_nonce_field( 'aff_save_assign' ); ?>
<input type="hidden" name="aff_action" value="save_assign">
<input type="hidden" name="id" value="<?php echo (int) $id; ?>">

<table class="form-table">
<tr>
  <th><label for="block_id">ブロック <span class="required">*</span></label></th>
  <td>
    <select id="block_id" name="block_id" required>
      <option value="">— 選択してください —</option>
      <?php foreach ( $blocks as $b ) : ?>
      <option value="<?php echo (int)$b->id; ?>" <?php selected( $v('block_id',0), $b->id ); ?>>
        <?php echo esc_html( $b->block_name ); ?> (<?php echo esc_html($b->block_slug); ?>)
      </option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr>
  <th><label for="page_id">ページ条件</label></th>
  <td>
    <select id="page_id" name="page_id">
      <option value="0" <?php selected( (int)$v('page_id',0), 0 ); ?>>— 指定なし（デフォルト／全ページ） —</option>
      <?php foreach ( $pages as $p ) : ?>
      <option value="<?php echo (int)$p->id; ?>" <?php selected( (int)$v('page_id',0), $p->id ); ?>>
        [優先度<?php echo (int)$p->priority; ?>] <?php echo esc_html( $p->page_label ); ?>
      </option>
      <?php endforeach; ?>
    </select>
    <p class="description">ページ固有の設定はデフォルト設定より優先されます。</p>
  </td>
</tr>
<tr>
  <th><label for="link_id">リンク <span class="required">*</span></label></th>
  <td>
    <select id="link_id" name="link_id" required>
      <option value="">— 選択してください —</option>
      <?php foreach ( $links as $l ) : ?>
      <option value="<?php echo (int)$l->id; ?>" <?php selected( $v('link_id',0), $l->id ); ?>>
        <?php echo esc_html( $l->link_name ); ?><?php echo $l->advertiser ? ' (' . esc_html($l->advertiser) . ')' : ''; ?>
        <?php echo $l->status !== 'active' ? ' [' . esc_html($l->status) . ']' : ''; ?>
      </option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr>
  <th><label for="display_order">表示順</label></th>
  <td><input type="number" id="display_order" name="display_order" value="<?php echo $v('display_order','0'); ?>" min="0" class="small-text">
      <p class="description">同一ブロック内での順番（小さい方が先）</p></td>
</tr>
<tr>
  <th><label for="override_text">テキスト上書き</label></th>
  <td><input type="text" id="override_text" name="override_text" value="<?php echo $v('override_text'); ?>" class="regular-text">
      <p class="description">このページ・ブロック固有のリンクテキスト。空欄の場合はリンク登録時のデフォルトテキストを使用。</p></td>
</tr>
<tr>
  <th>有効 / 無効</th>
  <td>
    <label>
      <input type="checkbox" name="is_active" value="1" <?php checked( $v('is_active','1'), '1' ); ?>>
      この割り当てを有効にする
    </label>
  </td>
</tr>
<tr>
  <th>掲載期間</th>
  <td>
    <label>開始: <input type="datetime-local" name="start_date" value="<?php echo Aff_Admin::to_datetime_local( $row->start_date ?? null ); ?>"></label>
    &nbsp;
    <label>終了: <input type="datetime-local" name="end_date" value="<?php echo Aff_Admin::to_datetime_local( $row->end_date ?? null ); ?>"></label>
    <p class="description">空欄 = 無期限（リンク側の掲載期間も適用されます）</p>
  </td>
</tr>
</table>

<p class="submit">
  <button type="submit" class="button button-primary">保存</button>
  <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-assigns' ) ); ?>" class="button">キャンセル</a>
</p>
</form>
</div>
