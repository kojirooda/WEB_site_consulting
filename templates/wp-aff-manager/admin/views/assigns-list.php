<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aff-wrap">
<h1 class="wp-heading-inline">割り当て管理</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-assigns&action=edit' ) ); ?>" class="page-title-action">新規割り当て</a>
<?php Aff_Admin::saved_notice(); ?>

<?php
global $wpdb;
$t_a = Aff_DB::table('assignments');
$t_b = Aff_DB::table('blocks');
$t_l = Aff_DB::table('links');
$t_p = Aff_DB::table('pages');

// ブロックでフィルタリング
$filter_block = absint( $_GET['block_id'] ?? 0 );
$where        = $filter_block ? $wpdb->prepare( "WHERE a.block_id = %d", $filter_block ) : '';

$rows = $wpdb->get_results(
    "SELECT a.*,
            b.block_name, b.block_slug,
            l.link_name, l.advertiser,
            p.page_label
     FROM {$t_a} a
     LEFT JOIN {$t_b} b ON b.id = a.block_id
     LEFT JOIN {$t_l} l ON l.id = a.link_id
     LEFT JOIN {$t_p} p ON p.id = a.page_id
     {$where}
     ORDER BY b.block_name ASC, a.display_order ASC"
);

// ブロック一覧（フィルター用）
$blocks = $wpdb->get_results( "SELECT id, block_name FROM {$t_b} ORDER BY block_name" );
?>

<div class="aff-filter">
  <label>ブロックで絞り込み:
    <select onchange="location.href='<?php echo esc_url( admin_url('admin.php?page=wp-aff-manager-assigns') ); ?>&block_id='+this.value">
      <option value="0">— すべて —</option>
      <?php foreach ( $blocks as $b ) : ?>
      <option value="<?php echo (int)$b->id; ?>" <?php selected($filter_block, $b->id); ?>><?php echo esc_html($b->block_name); ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</div>

<table class="widefat aff-table">
<thead>
<tr>
  <th>ID</th><th>ブロック</th><th>ページ条件</th><th>リンク</th><th>広告主</th>
  <th>順番</th><th>有効</th><th>掲載期間</th><th>操作</th>
</tr>
</thead>
<tbody>
<?php if ( $rows ) : foreach ( $rows as $r ) :
    $edit_url = admin_url( 'admin.php?page=wp-aff-manager-assigns&action=edit&id=' . $r->id );
?>
<tr class="<?php echo $r->is_active ? '' : 'aff-inactive'; ?>">
  <td><?php echo (int) $r->id; ?></td>
  <td><code><?php echo esc_html( $r->block_slug ); ?></code><br><small><?php echo esc_html( $r->block_name ); ?></small></td>
  <td><?php echo $r->page_label ? esc_html( $r->page_label ) : '<em>デフォルト（全ページ）</em>'; ?></td>
  <td><?php echo esc_html( $r->link_name ); ?></td>
  <td><?php echo esc_html( $r->advertiser ); ?></td>
  <td><?php echo (int) $r->display_order; ?></td>
  <td><?php echo $r->is_active ? '✓' : '—'; ?></td>
  <td>
    <?php echo $r->start_date ? esc_html( substr($r->start_date,0,10) ) : ''; ?>
    <?php echo ( $r->start_date && $r->end_date ) ? ' 〜 ' : ''; ?>
    <?php echo $r->end_date ? esc_html( substr($r->end_date,0,10) ) : ''; ?>
    <?php echo ( ! $r->start_date && ! $r->end_date ) ? '無期限' : ''; ?>
  </td>
  <td>
    <a href="<?php echo esc_url( $edit_url ); ?>">編集</a> |
    <?php echo Aff_Admin::delete_link( 'assigns', $r->id, 'wp-aff-manager-assigns' ); ?>
  </td>
</tr>
<?php endforeach; else : ?>
<tr><td colspan="9">割り当てが登録されていません。</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
