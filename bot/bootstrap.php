<?php
/**
 * Bale Bot module bootstrap (integrated into SportClub)
 */
if (!defined('ABSPATH')) {
    exit;
}

define('SC_BOT_DIR', SC_PLUGIN_DIR . 'bot/');
define('SC_BOT_INCLUDES_DIR', SC_BOT_DIR . 'includes/');
define('SC_BOT_COMMANDS_DIR', SC_BOT_DIR . 'commands/');
define('SC_BOT_PUBLIC_DIR', SC_BOT_DIR . 'public/');

require_once SC_BOT_INCLUDES_DIR . 'config.php';
require_once SC_BOT_INCLUDES_DIR . 'api.php';
require_once SC_BOT_INCLUDES_DIR . 'data.php';
require_once SC_BOT_INCLUDES_DIR . 'message-functions.php';
require_once SC_BOT_INCLUDES_DIR . 'presenters.php';
require_once SC_BOT_INCLUDES_DIR . 'webhook.php';
require_once SC_BOT_INCLUDES_DIR . 'router.php';
require_once SC_BOT_INCLUDES_DIR . 'database.php';
require_once SC_BOT_INCLUDES_DIR . 'helper.php';
require_once SC_BOT_INCLUDES_DIR . 'menu.php';
require_once SC_BOT_INCLUDES_DIR . 'admin-ajax.php';
require_once SC_BOT_PUBLIC_DIR . 'my-account.php';
