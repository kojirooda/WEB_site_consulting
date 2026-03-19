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

// ── ページネーション ──────────────────────────────────────────────
$per_page    = 50;
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset      = ( $current_page - 1 ) * $per_page;

// SELECT * を避けて必要カラムのみ取得（効率化）
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT a.id, a.block_id, a.link_id, a.page_id,
            a.display_order, a.override_text, a.is_active,
            a.start_date, a.end_date,
            b.block_name, b.block_slug,
            l.link_name, l.advertiser,
            p.page_label
     FROM {$t_a} a
     LEFT JOIN {$t_b} b ON b.id = a.block_id
     LEFT JOIN {$t_l} l ON l.id = a.link_id
     LEFT JOIN {$t_p} p ON p.id = a.page_id
     {$where}
     ORDER BY b.block_name ASC, a.display_order ASC
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
) ); // phpcs:ignore WordPress.DB.PreparedSQL

// 総件数（ページネーション計算用）
$total_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_a} a {$where}" ); // phpcs:ignore
$total_pages = max( 1, (int) ceil( $total_count / $per_page ) );

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
  <span class="aff-total-count">全 <?php echo number_format( $total_count ); ?> 件</span>
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

<?php if ( $total_pages > 1 ) : ?>
<div class="aff-pagination">
  <?php
  $base_url = admin_url( 'admin.php?page=wp-aff-manager-assigns' )
              . ( $filter_block ? '&block_id=' . $filter_block : '' );

  if ( $current_page > 1 ) {
      printf( '<a href="%s" class="button">&laquo; 前へ</a> ',
              esc_url( $base_url . '&paged=' . ( $current_page - 1 ) ) );
  }

  for ( $i = max( 1, $current_page - 2 ); $i <= min( $total_pages, $current_page + 2 ); $i++ ) {
      printf(
          '<a href="%s" class="button %s">%d</a> ',
          esc_url( $base_url . '&paged=' . $i ),
          $i === $current_page ? 'button-primary' : '',
          $i
      );
  }

  if ( $current_page < $total_pages ) {
      printf( '<a href="%s" class="button">次へ &raquo;</a>',
              esc_url( $base_url . '&paged=' . ( $current_page + 1 ) ) );
  }
  ?>
</div>
<?php endif; ?>

</div>
