<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 13/10/13
 * Time: 12:02 AM
 * To change this template use File | Settings | File Templates.
 */

class DZM_BTC_BTCGuild_Pool_Adapter extends DZM_BTC_Abstract_Pool_Adapter {
    protected function decodeUpdate($response) {
        return json_decode($response, true);
    }

    protected function getURL() {
        return 'https://www.btcguild.com/api.php?api_key=' . $this->api_key;
    }

}