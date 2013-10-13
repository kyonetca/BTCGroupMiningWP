<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 12/10/13
 * Time: 11:48 PM
 * To change this template use File | Settings | File Templates.
 */

abstract class DZM_BTC_Abstract_Pool_Adapter {
    abstract protected function getURL();
    abstract protected function decodeUpdate($response);

    protected $api_key;
    protected $payout_address;

    function __construct($api_key, $payout_address) {
        $this->api_key = $api_key;
        $this->payout_address = $payout_address;
    }

    public function getUpdate() {
        $curl = curl_init($this->getURL());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
        curl_setopt( $curl, CURLOPT_TIMEOUT, 15 );
        $result = curl_exec($curl);
        return $this->decodeUpdate($result);
    }
}