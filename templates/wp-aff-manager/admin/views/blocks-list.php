<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aff-wrap">
<h1 class="wp-heading-inline">広告ブロック管理</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-blocks&action=edit' ) ); ?>" class="page-title-action">新規追加</a>
<?php Aff_Admin::saved_notice(); ?>

<?php
global $wpdb;
$rows = $wpdb->get_results( "SELECT * FROM " . Aff_DB::table('blocks') . " ORDER BY id DESC" );
?>
<table class="widefat aff-table">
<thead>
<tr>
  <th>ID</th><th>ブロック名</th><th>スラッグ</th><th>配置タイプ</th>
  <th>最大数</th><th>表示形式</th><th>ステータス</th><th>ショートコード</th><th>操作</th>
</tr>
</thead>
<tbody>
<?php if ( $rows ) : foreach ( $rows as $r ) :
    $edit_url = admin_url( 'admin.php?page=wp-aff-manager-blocks&action=edit&id=' . $r->id );
?>
<tr class="aff-status-<?php echo esc_attr( $r->status ); ?>">
  <td><?php echo (int) $r->id; ?></td>
  <td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $r->block_name ); ?></a></td>
  <td><code><?php echo esc_html( $r->block_slug ); ?></code></td>
  <td><?php echo esc_html( $r->placement_type ); ?></td>
  <td><?php echo (int) $r->max_links; ?></td>
  <td><?php echo esc_html( $r->display_format ); ?></td>
  <td><span class="aff-badge aff-badge--<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $r->status ); ?></span></td>
  <td><code>[aff_block slug="<?php echo esc_attr( $r->block_slug ); ?>"]</code></td>
  <td>
    <a href="<?php echo esc_url( $edit_url ); ?>">編集</a> |
    <?php echo Aff_Admin::delete_link( 'blocks', $r->id, 'wp-aff-manager-blocks' ); ?>
  </td>
</tr>
<?php endforeach; else : ?>
<tr><td colspan="9">ブロックが登録されていません。</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
