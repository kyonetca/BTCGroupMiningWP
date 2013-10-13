<?php
/**
 * @package DZMinerCoop
 */
/*
Plugin Name: BTC Group Mining Summary 
Description: Grab stats from BTCGuild using API Key. Use shortcode [btcguild id=x] to display an account summary.
Version: 2.1.1
Plugin URI: http://mootinator.com/groupbuy-summary/
Author: Mootinator
Author URI: http://mootinator.com/
License: GPLv2 or later
*/
global $dzm_btcguild_db_version;
$dzm_btcguild_db_version = "1.2";

register_activation_hook( __FILE__, 'dzm_btcguild_install' );
register_activation_hook( __FILE__, 'dz_setup_schedule' );
register_deactivation_hook(__FILE__, 'dzm_deactivator');
add_action( 'admin_menu', 'dz_plugin_menu');
add_action( 'dzm_update_accounts_hook', 'dzm_update_accounts');
add_action( 'plugins_loaded', 'dzm_update_db_check');
add_action( 'wp_enqueue_scripts', 'dzm_plugin_styles');
add_filter( 'cron_schedules', 'cron_add_tenminutes' );
add_shortcode('btcguild', 'btcguild_func');

function dzm_plugin_styles() {
    wp_register_style( 'dzminercoop-btcguild', plugins_url( 'dzminercoop-btcguild/css/plugin.css'));
}

function cron_add_tenminutes($schedules) {
  $schedules['tenminutes'] = array(
                        'interval' => 600,
                        'display' => __( 'Once Every 10 Minutes' )
            );
  return $schedules;
}

function dz_setup_schedule() {
    wp_schedule_event( current_time('timestamp'), 'tenminutes', 'dzm_update_accounts_hook' );
}

function dzm_deactivator() {
  wp_clear_scheduled_hook('dzm_update_accounts_hook');
}

function dzm_update_accounts() {
  global $wpdb;
  $account_table = $wpdb->prefix . "dzm_btcguild_accounts";
  $worker_table = $wpdb->prefix . "dzm_btcguild_miners";
  $rows = $wpdb->get_results("SELECT * FROM $account_table");
  foreach ($rows as $row) {
      $curl = curl_init('https://www.btcguild.com/api.php?api_key=' . $row->api_key);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
      curl_setopt( $curl, CURLOPT_TIMEOUT, 15 );
      $result = curl_exec($curl);
      $res = json_decode($result, true);

      $payout_inbound = 0;
      $payout_outbound = 0;
 
      if (!empty($row->payout_address)) {
        $curl = curl_init('http://blockchain.info/address/' . $row->payout_address . '?format=json&limit=0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 15 );
        curl_setopt( $handle, CURLOPT_TIMEOUT, 15 );
        $result = curl_exec($curl);
        $wallet = json_decode($result, true);
        if (isset($wallet['address'])) {
          $payout_inbound = $wallet['total_received'] / 100000000;
          $payout_outbound = $wallet['total_sent'] / 100000000;
        }
      }
      $ts = date('Y-m-d H:i:s', time());
      if (isset($res['user'])) {
            $obj = $res['user'];
            $wpdb->update($account_table, array(
                                'total' => $obj['total_rewards'],
                                'balance' => $obj['unpaid_rewards'],
                                'last_24' => $obj['past_24h_rewards'],
                                'nmc_total' => $obj['total_rewards_nmc'],
                                'nmc_balance' => $obj['unpaid_rewards_nmc'],
                                'payout_inbound' => $payout_inbound,
                                'payout_outbound' => $payout_outbound,
                                'last_update' => $ts
                            ),
                        array('id' => $row->id),
                        array('%f','%f','%f','%f','%f','%f','%f','%s'),
                        '%d'
                
        );
        if (isset($res['workers'])) {
          foreach($res['workers'] as $worker) {
            $wpdb->insert($worker_table, array(
                        'account_id' => $row->id,
                        'worker_name' => $worker['worker_name'],
                        'hashrate' => $worker['hash_rate'],
                        'created_time' => $ts
                    ),
                    array('%d', '%s', '%f', '%s')
            );
          }
        }
      } 
  }

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

function btcguild_func($atts) {
  global $wpdb;
  $accounts_table = $wpdb->prefix . 'dzm_btcguild_accounts';
  $workers_table = $wpdb->prefix . 'dzm_btcguild_miners';

  wp_enqueue_style('dzminercoop-btcguild');
  extract(shortcode_atts( array(
    'id' => ''
    ), $atts));

    $account = $wpdb->get_row($wpdb->prepare("SELECT * FROM $accounts_table WHERE id = %d", $id));
    
    if(empty($account)) {
      return 'Invalid pool account ID';
    }
    $res = json_decode($result, true);
    $obj = $res['user'];
    $pool = $res['pool'];
    $total = (empty($account->payout_address) ? $account->total : ($account->balance + $account->payout_inbound));
    $net_revenue = $total - $account->fees;
    $eps = ($total - $account->fees) / $account->shares;
    $profit = $eps - $account->cost;
    $profit_class = ($profit < 0) ? ' class="negative" ' : '';
    $roi = round(($eps - $account->cost) / $account->cost * 100, 2);

    $time_diff = secs_to_h(time() - strtotime($account->last_update));
  
    $text = "<table class=\"dzm-btcguild\">";
    $text .= "<tr><th colspan=\"3\" class=\"dzm-top\">Returns</th></tr>";
    $text .= "<tr><th>Gross Revenue</th><td>&nbsp;</td><td>$total</td></tr>";
    if ($account->fees <> 0) {
      $text .= "<tr><th>Fees Applied</th><td>-</td><td>$account->fees</td></tr>";
      $text .= "<tr><th>Net Revenue</th><td>=</td><td>$net_revenue</td></tr>";
    }
    $text .= "<tr><th>Shares</th><td>&divide;<td>$account->shares</td></tr>";
    $text .= "<tr><th>Net Revenue Per Share</th><td>=</td><td>$eps</td>";
    $text .= "<tr><th>Share Cost</th><td>-</td><td>$account->cost</td>";
    $text .= "<tr class=\"dzm-summary\"><th>Net Profit Per Share</th><td>=</td><td $profit_class>$profit</td>";
    $text .= "<tr><th>Current ROI</th><td>=</td><td $profit_class>" . $roi . "%</td>";
    $text .= "<tr><th colspan=\"3\" class=\"dzm-top\">Other</th></tr>";
    $text .= "<tr><th>24 Hour BTC</th><td>&nbsp;</td><td>$account->last_24</td></tr>";
    $text .= "<tr><th>Earned NMC</th><td>&nbsp;</td><td>$account->nmc_total</td></tr>";

    $rows = $wpdb->get_results("SELECT * FROM $workers_table WHERE account_id = $account->id AND created_time = '$account->last_update'");
          foreach ($rows as $idx => $worker) {
            $text .= "<tr><th>Worker " . ($idx + 1) . " Hash Rate</th><td>&nbsp;</td><td>" . round($worker->hashrate/1000, 3) . " Gh/s</td></tr>";
      }
    $text .="<tr><td class=\"credit\" colspan =\"3\">Last Update: $time_diff ago | plugin by <a href=\"http://mootinator.com\">mootinator</a></td></tr>";
    $text .="</table>";
    return $text;
}

function dzm_btcguild_install() {
  global $wpdb;
  global $dzm_btcguild_db_version;
  $table_name = $wpdb->prefix . "dzm_btcguild_accounts";
  $update_table_name = $wpdb->prefix . "dzm_btcguild_miners";
  $version_option = 'dzm_btcguild_db_version';
  $installed_ver = get_option($version_option);

  if ( $instaled_ver != $dzm_btcguild_db_version ) {
  $sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    api_key varchar(64) NOT NULL,
    shares int NOT NULL DEFAULT 1,
    cost DECIMAL(16,8),
    total DECIMAL(16,8),
    balance DECIMAL(16, 8),
    nmc_total DECIMAL(16, 8),
    nmc_balance DECIMAL(16, 8),
    last_24 DECIMAL(16, 8),
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_update TIMESTAMP,
    payout_address varchar(34),
    payout_inbound decimal(16, 8),
    payout_outbound decimal(16,8),
    fees decimal(16, 8) NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
   );";

  $sql2 = "CREATE TABLE $update_table_name (
    id int NOT NULL AUTO_INCREMENT,
    account_id mediumint(9) NOT NULL,
    worker_name varchar(255),
    hashrate DECIMAL(16,3),
    created_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
  );";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php' );
  dbDelta($sql);
  dbDelta($sql2);

  update_option($version_option, $dzm_btcguild_db_version);
  }
}

function dzm_update_db_check(){
  global $dzm_btcguild_db_version;
  if (get_option('dzm_btcguild_db_version') != $dzm_btcguild_db_version){
    dzm_btcguild_install();
  }
}
function secs_to_h($secs)
{
        $units = array(
                "week"   => 7*24*3600,
                "day"    =>   24*3600,
                "hour"   =>      3600,
                "minute" =>        60,
                "second" =>         1,
        );

    // specifically handle zero
        if ( $secs == 0 ) return "0 seconds";

        $s = "";

        foreach ( $units as $name => $divisor ) {
                if ( $quot = intval($secs / $divisor) ) {
                        $s .= "$quot $name";
                        $s .= (abs($quot) > 1 ? "s" : "") . ", ";
                        $secs -= $quot * $divisor;
                        break;
                }
        }

        return substr($s, 0, -2);
}
?>
