<?php
if ( ! defined('ABSPATH') ) exit;
global $title, $coaches_list_table;
if (!isset($coaches_list_table)) {
    $coaches_list_table = new Coaches_List_Table();
    $coaches_list_table->prepare_items();
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">لیست مربیان</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-coach'); ?>" class="page-title-action">افزودن مربی</a>
</div>

<?php
echo '<div class="wrap">';
    echo '<form method="get">';
        echo '<input type="hidden" name="page" value="sc-coaches">';
        $coaches_list_table->search_box('جستجو مربی', 'search_coach');
        $coaches_list_table->views();
        $coaches_list_table->display();
    echo '</form>';
echo '</div>';
?>

