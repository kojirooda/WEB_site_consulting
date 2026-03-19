<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aff-wrap">
<h1 class="wp-heading-inline">アフィリエイトリンク管理</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager&action=edit' ) ); ?>" class="page-title-action">新規追加</a>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-import' ) ); ?>" class="page-title-action">⬆ CSV インポート</a>
<?php Aff_Admin::saved_notice(); ?>

<?php
global $wpdb;
// SELECT * を避けてリスト表示に必要なカラムのみ取得
$rows = $wpdb->get_results(
    "SELECT id, link_name, advertiser, commission_type, unit_price, status, click_count, valid_until
     FROM " . Aff_DB::table('links') . " ORDER BY id DESC"
);
?>
<table class="widefat aff-table">
<thead>
<tr>
  <th>ID</th><th>リンク名</th><th>広告主</th><th>報酬タイプ</th>
  <th>単価</th><th>ステータス</th><th>クリック数</th>
  <th>有効期限</th><th>操作</th>
</tr>
</thead>
<tbody>
<?php if ( $rows ) : foreach ( $rows as $r ) :
    $edit_url = admin_url( 'admin.php?page=wp-aff-manager&action=edit&id=' . $r->id );
?>
<tr class="aff-status-<?php echo esc_attr( $r->status ); ?>">
  <td><?php echo (int) $r->id; ?></td>
  <td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $r->link_name ); ?></a></td>
  <td><?php echo esc_html( $r->advertiser ); ?></td>
  <td><?php echo esc_html( strtoupper( $r->commission_type ) ); ?></td>
  <td>¥<?php echo number_format( (float) $r->unit_price ); ?></td>
  <td><span class="aff-badge aff-badge--<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $r->status ); ?></span></td>
  <td><?php echo number_format( (int) $r->click_count ); ?></td>
  <td><?php echo Aff_Admin::to_date_str( $r->valid_until ); ?></td>
  <td>
    <a href="<?php echo esc_url( $edit_url ); ?>">編集</a> |
    <?php echo Aff_Admin::delete_link( 'links', $r->id, 'wp-aff-manager' ); ?>
  </td>
</tr>
<?php endforeach; else : ?>
<tr><td colspan="9">リンクが登録されていません。</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
