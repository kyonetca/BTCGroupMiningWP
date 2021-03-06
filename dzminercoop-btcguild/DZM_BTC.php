<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 14/10/13
 * Time: 12:48 PM
 * To change this template use File | Settings | File Templates.
 */

class DZM_BTC {
    private static $instance;

    public $accounts_table;
    public $workers_table;
    public $spot_price_option;
    public $pools;

    private function __construct()
    {
        global $wpdb;
        $this->accounts_table = $wpdb->prefix . 'dzm_btcguild_accounts';
        $this->workers_table = $wpdb->prefix . 'dzm_btcguild_miners';
        $this->spot_price_option = 'bitstamp_spot_price';
        $this->pools = array(
            'DZM_BTC_BTCGuild_Pool_Adapter' => 'BTC Guild',
            'DZM_BTC_Elgius_Pool_Adapter' =>  'Elgius'
        );
    }

    public static function current()
    {
        if ( is_null( self::$instance ) )
        {
            self::$instance = new self();
        }
        return self::$instance;
    }
}