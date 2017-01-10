<?php

class WC_Lemonwaymkt_Moneyout
{
    /**
     * 
     * @var WC_Gateway_Lemonway
     */
    protected $gateway;
    
    protected $table_name;
    
    public function __construct(){
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lemonway_moneyout';
        $this->gateway = new WC_Gateway_Lemonway();
    }
    
    public function getMoneyouts($userId){
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE id_customer=" .(int)$userId );
    }
    
    public function doMoneyout($walletId,$amountToPay,$ibanId){
        try {
            $params = array(
                    "wallet" => $walletId,
                    "amountTot" => sprintf("%.2f" ,$amountToPay),
                    "amountCom" => sprintf("%.2f" ,(float)0),
                    "message" => get_bloginfo( 'name' ) . " - " . __("Moneyout from Wordpress module", LEMONWAY_TEXT_DOMAIN),
                    "ibanId" => $ibanId,
                    "autoCommission" => 0,
            );
        
            $op = $this->gateway->getDirectkit()->MoneyOut($params);
        
            if ($op->STATUS == "3") {
                $message = sprintf(__("You paid %s from your wallet <b>%s</b>",LEMONWAY_TEXT_DOMAIN), wc_price($amountToPay), $walletId);
                echo '<div id="message" class="updated notice is-dismissible"><p>' . $message . '</p></div>';
                 
            } else {
                $message = __("An error occurred. Please contact support.",LEMONWAY_TEXT_DOMAIN);
                echo '<div id="message" class="error notice-error is-dismissible"><p>' . $message . '</p></div>';
            }
        } catch (Exception $e) {
            echo '<div id="message" class="error notice-error is-dismissible"><p>' . $e->getMessage() . '</p></div>';
        }
    }
    
    /**
     * 
     * @param array $moneyout
     */
    public function save($moneyout)
    {
        global $wpdb;
            
        return $wpdb->insert($this->table_name, $moneyout);
    }
    
    public function getMoneyout($moneyoutId)
    {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id_moneyout=%d", (int)$moneyoutId));
    }
}