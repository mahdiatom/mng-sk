<?php
global $title ,$player_list_table;

            ?>
            <div class="wrap">
            <h1 class="wp-heading-inline">لیست بازیکن ها</h1>
            <a href="<?php echo admin_url('admin.php?page=sc-add-member'); ?>" class="page-title-action">افزودن بازیکن</a>
            </div>
            <?php
            echo '<div class="wrap">';
                echo '<form  Method="get" >';
                    echo '<input type="hidden" name="page" value="sc-members">';
                    $player_list_table->search_box('جستجو بازیکن' , 'search_player');
                    $player_list_table->views();
                    $player_list_table->display();
                echo '</form>';
            echo '</div>';




?>

<!-- The Modal -->
<div id="myModal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close">&times;</span>
    <p class="sk-modal-content"></p>
  </div>

</div>



    <?php

