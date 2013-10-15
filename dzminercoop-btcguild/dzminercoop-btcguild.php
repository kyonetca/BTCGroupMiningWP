<?php
/**
 * @package DZMinerCoop
 */
/*
Plugin Name: BTC Group Mining Summary 
Description: Grab stats from BTCGuild using API Key. Use shortcode [btcguild id=x] to display an account summary.
Version: 2.2.0
Plugin URI: http://mootinator.com/groupbuy-summary/
Author: Mootinator
Author URI: http://mootinator.com/
License: GPLv2 or later
*/

register_activation_hook( __FILE__, 'dzm_btcguild_install' );
register_activation_hook( __FILE__, 'dz_setup_schedule' );
register_deactivation_hook(__FILE__, 'dzm_deactivator');

include( plugin_dir_path( __FILE__ ) . 'dzminercoop-btcguild-dbschema.php');
include( plugin_dir_path( __FILE__ ) . 'dzminercoop-btcguild-scheduling.php');
include( plugin_dir_path( __FILE__ ) . 'dzminercoop-btcguild-shortcodes.php');

//TODO: Autoload?
include( plugin_dir_path( __FILE__ ) . 'DZM_BTC_Abstract_Pool_Adapter.php');
include( plugin_dir_path( __FILE__ ) . 'DZM_BTC_BTCGuild_Pool_Adapter.php');
include( plugin_dir_path( __FILE__ ) . 'DZM_BTC.php');


add_action( 'admin_menu', 'dz_plugin_menu');
add_action( 'wp_enqueue_scripts', 'dzm_plugin_styles');

function dzm_plugin_styles() {
    wp_register_script('amcharts', '//cdnjs.cloudflare.com/ajax/libs/amcharts/2.11.3/amcharts.js');
    wp_register_script('amstock', '//cdnjs.cloudflare.com/ajax/libs/amstockchart/2.11.3/amstock.js', array('amcharts'));
    wp_register_script('dzminercoop-chart', plugins_url('dzminercoop-btcguild/js/chart.js'), array('amcharts', 'amstock'));
    wp_register_style('dzminercoop-btcguild', plugins_url( 'dzminercoop-btcguild/css/plugin.css'));
}

function dz_plugin_menu() {
  add_submenu_page('options-general.php', 'BTCGuild Settings', 'BTC Group Summary', 'manage_options', 'dzm_btcguild_options', 'dzm_btcguild_options_func');
}

function dzm_btcguild_options_func() {
global $wpdb;

$account_table = $wpdb->prefix . 'dzm_btcguild_accounts';
$worker_table = $wpdb->prefix . 'dzm_btcguild_miners';

if (isset($_POST['api_key']) && empty($_POST['id'])) {
  $insert_res = $wpdb->insert( $account_table,
            array(
                'api_key' => $_POST['api_key'],
                'shares' => $_POST['shares'],
                'cost' => $_POST['price'],
                'update_interval' => $_POST['update_interval'],
                'payout_address' => $_POST['payout_address'],
                'fees' => $_POST['fees']),
            array(
                '%s',
                '%d',
                '%f',
                '%d',
                '%s',
                '%f'
            ));
  if ($insert_res) {
    dzm_update_accounts();
  }
}
elseif (isset($_POST['api_key'])) {
  if (isset($_POST['action']) && $_POST['action'] == 'Delete') {
    $wpdb->delete( $account_table, array('id' => $_POST['id']), '%d');
    $wpdb->delete( $worker_table, array('account_id' => $_POST['id']), '%d');
  }
  else {
    $update_res = $wpdb->update ( $account_table,
              array(
                  'api_key' => $_POST['api_key'],
                  'shares' => $_POST['shares'],
                  'cost' => $_POST['price'],
                  'update_interval' => $_POST['update_interval'],
                  'payout_address' => $_POST['payout_address'],
                  'fees' => $_POST['fees']),
              array('id' => $_POST['id']),
              array('%s','%d','%f','%d', '%s','%f'),
              array('%d'));
    if ($update_res) {
      dzm_update_accounts();
    }
  }
}
$rows = $wpdb->get_results("SELECT * FROM $account_table");
foreach ($rows as $row) {
  $html_old = <<<EOD
<form method="POST">
<label>ID: $row->id</label>
<input type="hidden" name="id" value="$row->id">
<br><label>API Key</label><br><input type="text" name="api_key" value="$row->api_key">
<br><label>Payout Address</label><br><input type="text" name="payout_address" value="$row->payout_address">
<br><label># Shares</label><br><input type="text" name="shares" value="$row->shares">
<br><label>Price</label><br><input type="text" name="price" value="$row->cost">
<br><label>Mgmt Fees</label><br><input type="text" name="fees" value="$row->fees">
<br><label>Update Interval (seconds)</label><br><input type="text" name="update_interval" value="$row->update_interval">
<br><input type="submit" value="Edit"> <input type="submit" name="action" value="Delete" onclick="return confirm('Are you sure? This can not be undone.');">
</form>

EOD;
print $html_old;
}

$html_new = <<<EOD
<fieldset><legend>Add Account</legend>
<form method="POST">
<label>API Key</label><br><input type="text" name="api_key">
<br><label>Payout Address</label><br><input type="text" name="payout_address">
<br><label># Shares</label><br><input type="text" name="shares" value="1">
<br><label>Price</label><br><input type="text" name="price" value="1">
<br><label>Mgmt Fees</label><br><input type="text" name="price" value="0.00000000">
<br><label>Update Interval (seconds)</label><br><input type="text" name="update_interval" value="600">
<br><input type="submit" value="Add">
</form>
</fieldset>
EOD;

print $html_new;
}

?>
