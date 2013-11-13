<?php
class DZM_BTC_Elgius_Pool_Adapter extends DZM_BTC_Abstract_Pool_Adapter {
    protected function decodeUpdate($response) {
        /* Give the elgius balances request scope */
        global $elgiusbalancesjsondec;
        $balance_api_page = 'http://eligius.st/~luke-jr/raw/7/balances.json';
        if(empty($elgiusbalancesjsondec)) {
            $curl = curl_init($balance_api_page);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
            curl_setopt( $curl, CURLOPT_TIMEOUT, 15 );
            $balance = curl_exec($curl);
            $elgiusbalancesjsondec = json_decode($balance, true);
        }
        $mybal = $elgiusbalancesjsondec[$this->payout_address];

        $hashRate = json_decode($response, true);

        //TODO: Get 24 hour reward or keep track of history in local db history

        return array('user' => array(
            'total_rewards' =>  ($mybal['everpaid'] + (isset($mybal['balance']) ? $mybal['balance'] : 0)) / 100000000,
            'unpaid_rewards' => $mybal['balance'] / 100000000,
            'past_24h_rewards' => 0,
            'total_rewards_nmc' => 0,
            'unpaid_rewards_nmc' => 0
        ),
            'workers' => array(
                array(
                    'worker_name' => $hashRate['output']['username'],
                    'hash_rate' => $hashRate['output']['av256']['numeric'] / 1000
                )
            )
        );
    }

    protected function getURL() {
        return 'http://eligius.st/~wizkid057/newstats/api.php?username=' . $this->payout_address . '&format=json&cmd=gethashrate';
    }

}