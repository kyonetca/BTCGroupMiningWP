<?php

add_shortcode('btcguild', 'btcguild_func');
add_shortcode('dzm_btc_chart', 'dzm_chart_func');

function dzm_chart_func($atts) {
  global $wpdb;
  wp_enqueue_script('amcharts');
  wp_enqueue_script('amstock');
  wp_enqueue_script('dzminercoop-chart');

  extract(shortcode_atts(array(
      'id' => ''
  ), $atts));

    $workers_table = DZM_BTC::current()->workers_table;

    $rows = $wpdb->get_results($wpdb->prepare("SELECT worker_name, created_time, hashrate FROM $workers_table WHERE account_id = %d ORDER BY worker_name, created_time", $id));

    $dataSets = array();
    $i = -1;
    $last_worker = '';
    foreach($rows as $row) {
        if ($row->worker_name != $last_worker) {
            $i++;
            $dataSets[] = array();
            $last_worker = $row->worker_name;
        }
        $dataSets[$i][] = (object) array('date' => strtotime($row->created_time) * 1000, 'value' => $row->hashrate / 1000);
    }

    $output = "<script type=\"text/javascript\" language=\"javascript\">dz_hashRateData = ";
    $output .= json_encode($dataSets);
    $output .=";</script><div id=\"hashratechartdiv\" style=\"height:300px; width:100%\"></div>";
    return $output;
}

function btcguild_func($atts) {
    global $wpdb;
    $accounts_table = DZM_BTC::current()->accounts_table;
    $workers_table = DZM_BTC::current()->workers_table;

    wp_enqueue_style('dzminercoop-btcguild');
    extract(shortcode_atts( array(
        'id' => ''
    ), $atts));

    $account = $wpdb->get_row($wpdb->prepare("SELECT * FROM $accounts_table WHERE id = %d", $id));

    if(empty($account)) {
        return 'Invalid pool account ID';
    }

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