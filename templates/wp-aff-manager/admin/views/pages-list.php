<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aff-wrap">
<h1 class="wp-heading-inline">ページ条件管理</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aff-manager-pages&action=edit' ) ); ?>" class="page-title-action">新規追加</a>
<?php Aff_Admin::saved_notice(); ?>

<?php
global $wpdb;
$rows = $wpdb->get_results( "SELECT * FROM " . Aff_DB::table('pages') . " ORDER BY priority DESC, id DESC" );
?>
<table class="widefat aff-table">
<thead>
<tr>
  <th>ID</th><th>ラベル</th><th>条件タイプ</th><th>条件値</th><th>優先度</th><th>操作</th>
</tr>
</thead>
<tbody>
<?php if ( $rows ) : foreach ( $rows as $r ) :
    $edit_url  = admin_url( 'admin.php?page=wp-aff-manager-pages&action=edit&id=' . $r->id );
    $condition = '';
    switch ( $r->target_type ) {
        case 'all':         $condition = '— 全ページ共通 —'; break;
        case 'post_type':   $condition = '投稿タイプ: ' . $r->post_type; break;
        case 'single':      $condition = '個別ページ ID: ' . $r->post_id; break;
        case 'category':    $condition = 'カテゴリ ID: ' . $r->term_id; break;
        case 'tag':         $condition = 'タグ ID: ' . $r->term_id; break;
        case 'url_pattern': $condition = 'URL パターン: ' . $r->url_pattern; break;
    }
?>
<tr>
  <td><?php echo (int) $r->id; ?></td>
  <td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $r->page_label ); ?></a></td>
  <td><?php echo esc_html( $r->target_type ); ?></td>
  <td><?php echo esc_html( $condition ); ?></td>
  <td><strong><?php echo (int) $r->priority; ?></strong></td>
  <td>
    <a href="<?php echo esc_url( $edit_url ); ?>">編集</a> |
    <?php echo Aff_Admin::delete_link( 'pages', $r->id, 'wp-aff-manager-pages' ); ?>
  </td>
</tr>
<?php endforeach; else : ?>
<tr><td colspan="6">ページ条件が登録されていません。</td></tr>
<?php endif; ?>
</tbody>
</table>
<p class="description">優先度（数値が大きい方が優先）: 個別ページ=20 > カテゴリ=10 > 投稿タイプ=5 > 全ページ=0 が目安です。</p>
</div>
