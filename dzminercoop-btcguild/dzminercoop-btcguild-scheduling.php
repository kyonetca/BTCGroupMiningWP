<?php

add_action( 'dzm_update_accounts_hook', 'dzm_update_accounts');
add_filter( 'cron_schedules', 'cron_add_tenminutes' );

function dz_setup_schedule() {
    wp_schedule_event( current_time('timestamp'), 'tenminutes', 'dzm_update_accounts_hook' );
}

function dzm_deactivator() {
    wp_clear_scheduled_hook('dzm_update_accounts_hook');
}

function cron_add_tenminutes($schedules) {
    $schedules['tenminutes'] = array(
        'interval' => 600,
        'display' => __( 'Once Every 10 Minutes' )
    );
    return $schedules;
}

function dzm_update_spot_price() {
            $curl = curl_init('https://www.bitstamp.net/api/ticker/');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
            curl_setopt( $curl, CURLOPT_TIMEOUT, 15 );
            $result = curl_exec($curl);
            $xrate = json_decode($result, true);

            if (isset ($xrate['last'])) {
                update_option(DZM_BTC::current()->spot_price_option, $xrate['last']);
            }
}

function dzm_update_accounts() {
    global $wpdb;
    $account_table = $wpdb->prefix . "dzm_btcguild_accounts";
    $worker_table = $wpdb->prefix . "dzm_btcguild_miners";

    dzm_update_spot_price();

    $rows = $wpdb->get_results("SELECT * FROM $account_table");
    foreach ($rows as $row) {
        $payout_inbound = 0;
        $payout_outbound = 0;

        if (!empty($row->payout_address)) {
            $curl = curl_init('http://blockchain.info/address/' . $row->payout_address . '?format=json&limit=0');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
            curl_setopt( $curl, CURLOPT_TIMEOUT, 15 );
            $result = curl_exec($curl);
            $wallet = json_decode($result, true);
            if (isset($wallet['address'])) {
                $payout_inbound = $wallet['total_received'] / 100000000;
                $payout_outbound = $wallet['total_sent'] / 100000000;
            }
        }
        $reflection = new ReflectionClass($row->pool_classname);
        $adapter = $reflection->newInstanceArgs(array('api_key' => $row->api_key, 'payout_address' => $row->payout_address));
        $res = $adapter->getUpdate();

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