<?php
global $dzm_btcguild_db_version;
$dzm_btcguild_db_version = "1.2";

register_activation_hook( __FILE__, 'dzm_btcguild_install' );
add_action( 'plugins_loaded', 'dzm_update_db_check');

function dzm_btcguild_install() {
    global $wpdb;
    global $dzm_btcguild_db_version;
    $table_name = $wpdb->prefix . "dzm_btcguild_accounts";
    $update_table_name = $wpdb->prefix . "dzm_btcguild_miners";
    $version_option = 'dzm_btcguild_db_version';
    $installed_ver = get_option($version_option);

    if ( $installed_ver != $dzm_btcguild_db_version ) {
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