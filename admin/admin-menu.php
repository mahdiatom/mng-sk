<?php 

/**
 * ============================
 * Admin Menu
 * ============================
 */
add_action('admin_menu', 'sc_register_admin_menu');

function sc_register_admin_menu() {

    // Main menu
   add_menu_page(
        'SportClub Manager',        // Page title
        'SportClub Manager',        // Menu title
        'manage_options',           // Capability
        'sc-dashboard',             // Menu slug
        'sc_admin_dashboard_page',  // Callback
        'dashicons-universal-access-alt', // Icon
        26                          // Position
    );

    // Members list
    add_submenu_page(
        'sc-dashboard',
        'Members',
        'Members',
        'manage_options',
        'sc-members',
        'sc_admin_members_list_page'
    );

    // Add Member
    $add_member_sufix =  add_submenu_page(
        'sc-dashboard',
        'Add Member',
        'Add Member',
        'manage_options',
        'sc-add-member',
        'sc_admin_add_member_page'
    );

    add_action('load-'. $add_member_sufix , 'callback_add_member_sufix');
}

/**
 * Placeholder functions for admin pages
 */
function sc_admin_dashboard_page() {
    echo "<h1>SportClub Manager Dashboard</h1>";
}

function sc_admin_members_list_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'members-list.php';
}

function sc_admin_add_member_page() {
    global $wpdb ;
            $table_name = $wpdb->prefix . 'sc_members';
            $player=false;
                if( isset($_GET['player_id'] ) ){
                    $player_id = absint($_GET['player_id']);
                    if($player_id){
                        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",[$player_id]);
                    $player = $wpdb->get_row( $sql
                        
                    );
                }
            }
    include SC_TEMPLATES_ADMIN_DIR . 'member-add.php';
}
//for save data in new member -> wpdb
function callback_add_member_sufix(){
    if(isset($_GET['page']) && $_GET['page'] == 'sc-add-member' && isset($_POST['submit_player'])) {
       global $wpdb;
       $table_name = $wpdb->prefix . 'sc_members';
       $data = [
        'first_name'           => sanitize_text_field($_POST['first_name']),
        'last_name'            => sanitize_text_field($_POST['last_name']),
        'father_name'          => sanitize_text_field($_POST['father_name']),
        'national_id'          => sanitize_text_field($_POST['national_id']),
        'player_phone'         => sanitize_text_field($_POST['player_phone']),
        'father_phone'         => sanitize_text_field($_POST['father_phone']),
        'mother_phone'         => sanitize_text_field($_POST['mother_phone']),
        'landline_phone'       => sanitize_text_field($_POST['landline_phone']),
        'birth_date_shamsi'    => sanitize_text_field($_POST['birth_date_shamsi']),
        'birth_date_gregorian' => sanitize_text_field($_POST['birth_date_gregorian']),
        'personal_photo'        => isset($_POST['personal_photo']) ? esc_url_raw($_POST['personal_photo']) : '',
        'id_card_photo'         => isset($_POST['id_card_photo']) ? esc_url_raw($_POST['id_card_photo']) : '',
        'sport_insurance_photo' => isset($_POST['sport_insurance_photo']) ? esc_url_raw($_POST['sport_insurance_photo']) : '',
        'medical_condition'    => sanitize_textarea_field($_POST['medical_condition']),
        'sports_history'       => sanitize_textarea_field($_POST['sports_history']),
        'health_verified'      => isset($_POST['health_verified']) ? 1 : 0,
        'info_verified'        => isset($_POST['info_verified']) ? 1 : 0,
        'is_active'            => isset($_POST['is_active']) ? 1 : 0,
        'additional_info'      => sanitize_textarea_field($_POST['additional_info']),
        'created_at'           => current_time('mysql'),
        'updated_at'           => current_time('mysql'),
              ];
                    
        $player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;

        // بروزرسانی
        if ($player_id) {
            $updated = $wpdb->update(
                $table_name,
                $data,
                ['id' => $player_id]
            );

            if ($updated !== false) {
                $data['updated_at'] = current_time('mysql');
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=updated&player_id=' . $player_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=update_error&player_id=' . $player_id));
                exit;
            }
        } 
        // اضافه کردن جدید
        else {
            //$data['created_at'] = current_time('mysql');
            $inserted = $wpdb->insert($table_name, $data);

            if ($inserted) {
                $insert_id = $wpdb->insert_id;
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_true&player_id=' . $insert_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
                exit;
            }
        }
    }

}

