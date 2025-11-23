<?php
global $courses_list_table;
?>
<div class="wrap">
    <h1 class="wp-heading-inline">لیست دوره‌ها</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-course'); ?>" class="page-title-action">افزودن دوره</a>
</div>
<?php
echo '<div class="wrap">';
    echo '<form Method="get">';
        echo '<input type="hidden" name="page" value="sc-courses">';
        $courses_list_table->search_box('جستجو دوره', 'search_course');
        $courses_list_table->views();
        $courses_list_table->display();
    echo '</form>';
echo '</div>';
?>





