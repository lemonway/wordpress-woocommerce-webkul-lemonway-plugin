<?php
/*
 Plugin Name: Lemonway Marketplace (webkul)
 Plugin URI: http://www.sirateck.com
 Description: Secured payment solutions for Internet marketplaces, eCommerce, and crowdfunding. Payment API. BackOffice management. Compliance. Regulatory reporting.
 Version: 1.0.2
 Author: Kassim Belghait <kassim@sirateck.com>
 Author URI: http://www.sirateck.com
 License: GPL2
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

final class Lemonwaymkt {
    
    
    /**
     * @var Lemonway The single instance of the class
     */
    protected static $_instance = null;
    protected $name = "Secured payment solutions for Internet marketplaces with plugin WEBKUL Marketplace.";
    protected $slug = 'lemonwaymkt';
    
    /**
     * Pointer to gateway making the request.
     * @var WC_Gateway_Lemonway
     */
    protected $gateway;
     
    const DB_VERSION = '1.0.0';
     
     /**
      * Constructor
      */
     public function __construct(){
     
        // Define constants
        $this->define_constants();
        
        // Check plugin requirements
        $this->check_requirements();
        
        register_activation_hook( __FILE__, array($this,'lwmkt_install') );
        
        $this->includes();
        
        //fitler widget webkul to add lemonway link
        add_filter('widget_output', array($this,'filter_webkul_widget'), 10, 4);
        
        add_action( 'init', array( $this, 'calling_pages' ),100 );
        
        //When role is changed to wk_marketplace_seller
        add_action( 'set_user_role', array( $this, 'role_changed' ),999 ,3);
        
        add_action( 'wp_ajax_marketplace_mp_make_payment',array($this,'marketplace_mp_make_payment'),10);
        
        add_action( 'plugins_loaded', array( $this, 'init_gateway' ), 0 );
        
        //seller approvement
        //add_action( 'wp_ajax_nopriv_wk_admin_seller_approve',array($this,'wk_admin_seller_approve'),-10 );
        //add_action( 'wp_ajax_wk_admin_seller_approve',array($this,'wk_admin_seller_approve'),-10);
        // selller approvement end
        
        
        
        //add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

        $this->load_plugin_textdomain();
        
     }
     
     /**
      * Init Gateway
      */
     public function init_gateway()
     {
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }
         
        // Includes
        include_once( 'includes/class-wc-gateway-lemonway.php' );
        $this->gateway = new WC_Gateway_Lemonway();
     }
     
     public function marketplace_mp_make_payment()
     {
        global $wpdb;
        
        $id=$_POST['seller_acc'];
        $remain=$_POST['remain'];
        $pay=$_POST['pay'];
        if (empty($pay) || ($remain < $pay)) {
            $pay = $remain;
            $_POST['pay'] = $remain;
        }
        
        
        $query = "select * from {$wpdb->prefix}mpcommision where seller_id=$id";
        $seller_data = $wpdb->get_results($query);
    
        //Send only commision seller percent to wallet SC 
        $comToSendToSC = ($seller_data[0]->commision_on_seller/100) * $pay;

        $w = new WC_Lemonwaymkt_Wallet();

        $params = array(
                "debitWallet"   => $this->gateway->get_option('merchant_id'),
                "creditWallet"  => $w->getWalletByUser($id)->id_lw_wallet,
                "amount"        => number_format((float)$pay, 2, '.', ''),
                "message"       => sprintf(__('Send payment of %d for seller %s', LEMONWAYMKT_TEXT_DOMAIN),$pay,$id),
                "scheduledDate" => "",
                "privateData"   => "",
        );
        
        $kit = $this->gateway->getDirectkit();

        try {
            //Send seller amount
            $kit->SendPayment($params);
            
            //Send marketplace commision        
            /*$params = array(
                    "debitWallet"   => $this->gateway->get_option('merchant_id'),
                    "creditWallet"  => "SC",
                    "amount"        => number_format((float)$comToSendToSC, 2, '.', ''),
                    "message"       => __('Send payment commision', LEMONWAYMKT_TEXT_DOMAIN),
                    "scheduledDate" => "",
                    "privateData"   => "",
            );

            $kit->SendPayment($params);*/
        } catch (DirectkitException $de) {
            die($de->getMessage());
            
        } catch (Exception $e) {
            die($e->getMessage());
        }
        
     }
     
     public function set_user_role($userId, $role, $oldRole)
     {
        global $wpdb;
        
        if ($role == 'wk_marketplace_seller') {
            $w = new WC_Lemonwaymkt_Wallet ();
            try {
                $data=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}mpsellerinfo  WHERE user_id = " . (int)$userId . "");
                $w->registerWallet ( new WP_User($userId), $data['seller_id'] );
                //wc_add_notice ( __ ( "Wallet created.", LEMONWAYMKT_TEXT_DOMAIN ) );
            } catch ( Exception $e ) {
                //wc_add_notice ( $e->getMessage (), 'error' );
            }
        }
     }
     
     public function wk_admin_seller_approve()
     {
        global $wpdb;
        $sel_val = 1;
        $seller_id = explode('_mp',$_POST['seller_app']);
        $userId = $seller_id[1];
        if ($seller_id[2] == 1) {
            $sel_val=0;
        }
        
        if ($sel_val) {
            $w = new WC_Lemonwaymkt_Wallet ();
            try {
                $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}mpsellerinfo WHERE user_id = '" . $userId . "'");
                $w->registerWallet ( new WP_User($userId), $data['seller_id'] );
                //wc_add_notice ( __ ( "Wallet created.", LEMONWAYMKT_TEXT_DOMAIN ) );
            } catch ( Exception $e ) {
                //wc_add_notice ( $e->getMessage (), 'error' );
            }
        }
     }
     
     public function calling_pages()
     {
        global $current_user,$wpdb;
        
        $current_user = wp_get_current_user();
        $seller_id = $wpdb->get_var("SELECT seller_id FROM " . $wpdb->prefix . "mpsellerinfo WHERE user_id = '" . $current_user->ID . "'");
        if (isset($_GET['page'])) {
            $action = "";
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
            }

            //Wallet manager
            $wm = new WC_Lemonwaymkt_Wallet();

            
            if (($_GET['page']=="lemonway" ) && ($current_user->ID || $seller_id>0)) {
                switch($action) {
                    case "createWallet":
                        try{
                            
                            $wm->registerWallet($current_user,$seller_id);
                            wc_add_notice(__("Wallet created.", LEMONWAYMKT_TEXT_DOMAIN));
                        }
                        catch (Exception $e){
                            wc_add_notice($e->getMessage(),'error');
                        }
                        //wp_redirect(get_permalink().'?page=lemonway');
                        wp_redirect(add_query_arg( array(
                                                    'page' => 'lemonway'
                                                ), get_permalink() ));
                        exit();
                        break;
                    default:
                        break;
                }
                
                include 'front/dashboard.php';
                add_shortcode('marketplace','dashboard');
            }
            elseif (($_GET['page']=="lemonway-add-iban" ) && ($current_user->ID || $seller_id>0)) {
                
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST) && ($data = $_POST['iban_data'])) {
                    
                    if (!isset($data['holder']) || empty($data['holder'])) {
                        wc_add_notice(__('Holder name is required', LEMONWAYMKT_TEXT_DOMAIN),'error');
                    }
                    elseif (!isset($data['iban']) || empty($data['iban'])) {
                        wc_add_notice(__('Holder name is required', LEMONWAYMKT_TEXT_DOMAIN),'error');
                    }
                    else {
                        
                        $ibanManager = new WC_Lemonwaymkt_Iban();

                        try {
                            $_wallet = $wm->getWalletByUser($current_user->ID);
                            $ibanManager->registerIban($data, $_wallet->id_lw_wallet);
                            wc_add_notice(__("Iban created.", LEMONWAYMKT_TEXT_DOMAIN));
                            //wp_redirect(get_permalink().'?page=lemonway');
                            wp_redirect(add_query_arg( array(
                                                    'page' => 'lemonway'
                                                ), get_permalink() ));
                            exit();
                        }
                        catch (DirectkitException $de){
                            wc_add_notice($de->getMessage(),'error');
                        }
                        catch (Exception $e){
                            wc_add_notice($e->getMessage(),'error');
                        }
                        
                    }

                }
                
                include 'front/add_iban.php';
                add_shortcode('marketplace','formIban');
            }
            elseif (($_GET['page']=="lemonway-do-moneyout" ) && ($current_user->ID || $seller_id>0)) {  
                $_wallet = $wm->getWalletByUser($current_user->ID);
                
                include 'front/do_moneyout.php';
                add_shortcode('marketplace','formMoneyout');
            }
        }
     }
     
     public function displayFormMoneyout($walletId)
     {
        try {
            /** @var $wallet Wallet **/
            $wallet = LW()->getWalletDetails($walletId);
        } catch (Exception $e) {
            echo $e->getMessage();
            return;
        }
            
        if (isset($_POST['amountToPay'])) {
            $amountToPay = (float)str_replace(",", ".", $_POST['amountToPay']);
                
            if ($amountToPay > $wallet->BAL) {
                $message = sprintf(__("You can't paid amount upper of your balance amount: %s",LEMONWAY_TEXT_DOMAIN),wc_price($wallet->BAL));
                wc_add_notice($message,'error');
            } elseif ($amountToPay <= 0) {
                $message = __("Amount must be greater than 0",LEMONWAY_TEXT_DOMAIN);
                wc_add_notice($message,'error');
     
            } else {
                $ibanId = 0;
                    
                if (isset($_POST['iban_id']) && is_array($_POST['iban_id'])) {
                    $ibanId = current($_POST['iban_id']);
                    $iban = $_POST['iban_' . $ibanId];
                        
                    try {
                        $params = array(
                                "wallet"=>$wallet->ID,
                                "amountTot"=>sprintf("%.2f" ,$amountToPay),
                                "amountCom"=>sprintf("%.2f" ,(float)0),
                                "message"=>__("Moneyout from Wordpress module",LEMONWAY_TEXT_DOMAIN),
                                "ibanId"=>$ibanId,
                                "autoCommission" => 0,
                        );
                            
                        $op = $this->gateway->getDirectkit()->MoneyOut($params);
                            
                        if ($op->STATUS == "3") {
                            //Record moneyout in DB
                            $moneyoutManager = new WC_Lemonwaymkt_Moneyout();
                            $data = array(
                                    'id_lw_wallet'=> $walletId,
                                    'id_customer'=> wp_get_current_user()->ID,
                                    'id_employee' => 0,
                                    'is_admin' => 0,
                                    'id_lw_iban' => $ibanId,
                                    'prev_bal' => $wallet->BAL,
                                    'new_bal' => $wallet->BAL - $amountToPay,
                                    'iban' => $iban,
                                    'amount_to_pay' => $amountToPay,
                                    'date_add' => date('Y-m-d H:i:s'),
                                    'date_upd' => date('Y-m-d H:i:s'),
                            );
                            
                            $moneyoutManager->save($data);
                            
                            $wallet->BAL = $wallet->BAL - $amountToPay;
                            $message = sprintf(__("You paid %s to your Iban %s from your wallet <b>%s</b>",LEMONWAY_TEXT_DOMAIN),wc_price($amountToPay),$iban,$wallet->ID);
                            wc_add_notice($message);
                             
                        }
                        else{
                            $message = __("An error occurred. Please contact support.",LEMONWAY_TEXT_DOMAIN);
                            wc_add_notice($message,'error');
                        }
     
                    } catch (Exception $e) {
                        wc_add_notice($e->getMessage(),'error');
                    }
                }
                else {
                    $message = __('Please select an IBAN at least',LEMONWAY_TEXT_DOMAIN) ;
                    wc_add_notice($message,'error');
                }
            }
        }
        wc_print_notices();
     
        ?>
                      <form method="post" action="">    
                        <div class="card wallet-info" >
                            <h3><?php echo __('Wallet informations',LEMONWAY_TEXT_DOMAIN) ?></h3>
                      <table class="shop_table shop_table_responsive" >
                        <tr>
                            <td class=""><label ><?php echo __('Wallet ID',LEMONWAY_TEXT_DOMAIN)?></label></td>
                            <td class="">
                                <strong><?php echo $wallet->ID ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class=""><label ><?php echo __('Balance',LEMONWAY_TEXT_DOMAIN)?></label></td>
                            <td class="">
                                <strong><?php echo wc_price($wallet->BAL)?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class=""><label ><?php echo __('Owner name',LEMONWAY_TEXT_DOMAIN)?></label></td>
                            <td class="">
                                <strong><?php echo $wallet->NAME ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class=""><label ><?php echo __('Owner email',LEMONWAY_TEXT_DOMAIN)?></label></td>
                            <td class="">
                                <strong><?php echo $wallet->EMAIL ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class=""><label ><?php echo __('Status',LEMONWAY_TEXT_DOMAIN)?></label></td>
                            <td class="">
                                <strong><?php echo $wallet->getStatusLabel() ?></strong>
                            </td>
                        </tr>
                    </table>
                        </div>
                        <div class="card iban-info">
                            <h3><?php echo __('Iban informations',LEMONWAY_TEXT_DOMAIN) ?></h3>
                            <?php if(count($wallet->ibans)) :?>
                            <table class="shop_table shop_table_responsive" >
                            <tr><td colspan="2"><?php echo __('Select an Iban',LEMONWAY_TEXT_DOMAIN) ?></td></tr>
                                <?php foreach ($wallet->ibans as $_iban) : /** @var $_iban Iban */?>
                                <tr>
                                    <td>
                                         <input type="hidden" value="<?php echo $_iban->IBAN ?>" name="iban_<?php echo $_iban->ID ?>" />
                                    </td>
                                    <td class="a-left">
                                        <label for="iban_<?php echo $_iban->ID ?>" >
                                        <input class="required-entry" id="iban_<?php echo $_iban->ID ?>" type="radio" name="iban_id[]" value="<?php echo $_iban->ID ?>" />
                                            <strong><?php echo $_iban->IBAN ?></strong>
                                            <br />
                                            <strong><?php echo $_iban->BIC ?></strong>
                                            <br />
                                          <!--   <?php //echo __('Status',LEMONWAY_TEXT_DOMAIN)?>&nbsp;<strong><?php // echo $_iban->STATUS ?></strong> -->
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            <?php else:?>
                                <div class="box">
                                    <h4><?php echo __("You don't have any Iban!",LEMONWAY_TEXT_DOMAIN)?></h4> 
                                    <?php echo sprintf(__('Please create at least one for wallet <b>%s</b> in <a href="%s">Lemonway BO </a>.',LEMONWAY_TEXT_DOMAIN),$wallet->ID,"https://www.lemonway.fr/MbDev/bo") ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(count($wallet->ibans) && (float)$wallet->BAL > 0) :?>
                        <div class="card moneyout-form" >
                            <h3><?php echo __('Moneyout informations',LEMONWAY_TEXT_DOMAIN) ?></h3>
                            
                                <table class="shop_table shop_table_responsive" >
                                    <tbody>
                                        <tr>
                                            <th scope="row"><?php echo __("Amount to pay",IZIFLUX_TEXT_DOMAIN)?></th>
                                            <td>
                                                <input type="text" id="amountToPay" class="input-text not-negative-amount"  name="amountToPay" value="<?php echo $wallet->BAL?>">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                    <div class="buttons-set">
                                            <a class="edit-link" href="<?php echo add_query_arg( array(
                                            'page' => 'lemonway'), get_permalink() ); ?>"><small>&laquo; </small><?php echo __('Back',LEMONWAYMKT_TEXT_DOMAIN) ?></a>
                                            <button type="submit" class="button" title="<?php echo __('Do moneyout',LEMONWAYMKT_TEXT_DOMAIN) ?>"><span><span><?php echo __('Do moneyout',LEMONWAYMKT_TEXT_DOMAIN) ?></span></span></button>
                                    </div>
                               
                        </div>
                        <?php endif;?>
                        </form>
                        <?php 
        
        }
     
     public function includes(){
        require_once(ABSPATH.'wp-content/plugins/lemonway/includes/services/DirectkitJson.php');
        require_once('includes/class-wc-lemonwaymkt-wallet.php');
        require_once('includes/class-wc-lemonwaymkt-iban.php');
        require_once('includes/class-wc-lemonwaymkt-moneyout.php');
     }
     
     public function filter_webkul_widget($widget_output, $widget_type, $widget_id, $sidebar_id){
        global $wpdb;
        if($widget_type != 'mp_marketplace-widget'){
            return $widget_output;
        }

        $user_id = get_current_user_id();
        $seller_info=$wpdb->get_var("SELECT user_id FROM ".$wpdb->prefix."mpsellerinfo WHERE user_id = '".$user_id ."' and seller_value='1'");      
        $page_name = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title ='".get_option('wkmp_seller_login_page_tile')."'");
        $html = '';
        if((int)$seller_info > 0){          
            $html = '<div class="wk_seller"><h2>'.__( "Lemonway Payment", LEMONWAYMKT_TEXT_DOMAIN ).'</h2><ul class="wk_sellermenu"><li class="selleritem"><a href="'.home_url("?page_id=".$page_name).'&page=lemonway">'.__( "Dashboard", LEMONWAYMKT_TEXT_DOMAIN ).'</a></li></ul></div>';
        }
        return $widget_output . $html;
        
     }
   
     
     /**
      * Load Localisation files.
      *
      * Note: the first-loaded translation file overrides any following ones if
      * the same translation is present.
      *
      * Locales found in:
      *      - WP_LANG_DIR/lemonway/woocommerce-gateway-lemonway-LOCALE.mo
      *      - WP_LANG_DIR/plugins/lemonway-LOCALE.mo
      */
     public function load_plugin_textdomain() {
        $locale = apply_filters( 'plugin_locale', get_locale(), LEMONWAYMKT_TEXT_DOMAIN );
        $dir    = trailingslashit( WP_LANG_DIR );
     
        load_textdomain( LEMONWAYMKT_TEXT_DOMAIN, $dir . 'lemonway/lemonway-' . $locale . '.mo' );
        load_plugin_textdomain( LEMONWAYMKT_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
     }
     
    
     
     /**
      * Add relevant links to plugins page
      * @param  array $links
      * @return array
      */
     public function plugin_action_links( $links ) {

        $plugin_links = array(
                '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_lemonway' ) . '">' . __( 'Settings', LEMONWAYMKT_TEXT_DOMAIN ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
     }
     
     /**
      * Main Lemonway Instance
      *
      * Ensures only one instance of Lemonway is loaded or can be loaded.
      *
      * @static
      * @see LW()
      * @return Lemonway - Main instance
      */
     public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
     }
     
     /**
      * Define Constants
      *
      * @access private
      */
     private function define_constants() {
        define( 'LEMONWAYMKT_NAME', $this->name );
        define( 'LEMONWAYMKT_TEXT_DOMAIN', $this->slug );
     }
     
     
     /**
      * Checks that the WordPress setup meets the plugin requirements.
      *
      * @access private
      * @global string $wp_version
      * @return boolean
      */
     private function check_requirements() {
        //global $wp_version, $woocommerce;
     
        require_once(ABSPATH.'/wp-admin/includes/plugin.php');
     
        //@TODO version compare
     
        if( function_exists( 'is_plugin_active' ) ) {
            
            if ( !is_plugin_active( 'lemonway/lemonway.php' ) ) {
                add_action('admin_notices', array( &$this, 'alert_lw_not_actvie' ) );
                return false;
            }
        }
     
        return true;
     }
     
     /**
      * Display the Lemonway requirement notice.
      *
      * @access static
      */
     static function alert_lw_not_actvie() {
        echo '<div id="message" class="error"><p>';
        echo sprintf( __('Sorry, <strong>%s</strong> requires Lemonway to be installed and activated first. Please install Lemonway plugin first.', LEMONWAYMKT_TEXT_DOMAIN), LEMONWAYMKT_NAME );
        echo '</p></div>';
     }
     
     
     /**
      * Setup SQL
      */
     
     function lwmkt_install(){
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . "lemonwaymkt_wallet_transaction";
        
        $sql = "CREATE TABLE IF NOT EXISTS `".$table_name."` (
              `id_transaction` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Transaction ID',
              `id_order` int(11) NOT NULL COMMENT 'Real Order ID',
              `id_customer` int(11) NOT NULL COMMENT 'Customer ID',
              `seller_id` int(11) NOT NULL COMMENT 'Seller ID',
              `shop_name` varchar(255) NOT NULL COMMENT 'Shop name',
              `amount_total` decimal(20,6) NOT NULL COMMENT 'Total amount to pay',
              `amount_to_pay` decimal(20,6) NOT NULL COMMENT 'Total amount to pay',
              `admin_commission` decimal(20,6) NOT NULL COMMENT 'Total amount to pay',
              `lw_commission` decimal(20,6) DEFAULT 0 COMMENT 'LW commission returned after send payment',
              `status` smallint(2) NOT NULL DEFAULT 0 COMMENT 'Transaction Status',
              `lw_id_send_payment` varchar(255) COMMENT 'Send payment Lemonway ID',
              `debit_wallet` varchar(255) DEFAULT NULL COMMENT 'Wallet debtor',
              `credit_wallet` varchar(255) DEFAULT NULL COMMENT 'Wallet creditor',
              `date_add` datetime NOT NULL COMMENT 'Wallet Creation Time',
              `date_upd` datetime NOT NULL COMMENT 'Wallet Modification Time',
              PRIMARY KEY (`id_transaction`),
              UNIQUE KEY (`id_order`,`id_customer`)
            ) ENGINE=InnoDB ".$charset_collate." COMMENT='Wallet transactions Table' ;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        add_option( 'lwmkt_db_version', self::DB_VERSION);
        
     }
     

     
     
}

function LWMKT(){
    return Lemonwaymkt::instance();
}
LWMKT();


/**
 * Class Widget_Output_Filters
*
* Allows developers to filter the output of any WordPress widget.
*/
class Widget_Output_Filters {

    /**
     * Initializes the functionality by registering actions and filters.
     */
    public function __construct() {

        // Priority of 9 to run before the Widget Logic plugin.
        add_filter( 'dynamic_sidebar_params', array( $this, 'filter_dynamic_sidebar_params' ), 9 );
    }

    /**
     * Replaces the widget's display callback with the Dynamic Sidebar Params display callback, storing the original callback for use later.
     *
     * The $sidebar_params variable is not modified; it is only used to get the current widget's ID.
     *
     * @param array $sidebar_params The sidebar parameters.
     *
     * @return array The sidebar parameters
     */
    public function filter_dynamic_sidebar_params( $sidebar_params ) {

        if ( is_admin() ) {
            return $sidebar_params;
        }

        global $wp_registered_widgets;
        $current_widget_id = $sidebar_params[0]['widget_id'];

        $wp_registered_widgets[ $current_widget_id ]['original_callback'] = $wp_registered_widgets[ $current_widget_id ]['callback'];
        $wp_registered_widgets[ $current_widget_id ]['callback'] = array( $this, 'display_widget' );

        return $sidebar_params;
    }

    /**
     * Execute the widget's original callback function, filtering its output.
     */
    public function display_widget() {

        global $wp_registered_widgets;
        $original_callback_params = func_get_args();

        $widget_id         = $original_callback_params[0]['widget_id'];
        $original_callback = $wp_registered_widgets[ $widget_id ]['original_callback'];

        $widget_id_base = $original_callback[0]->id_base;
        $sidebar_id     = $original_callback_params[0]['id'];

        if ( is_callable( $original_callback ) ) {

            ob_start();
            call_user_func_array( $original_callback, $original_callback_params );
            $widget_output = ob_get_clean();

            /**
             * Filter the widget's output.
             *
             * @param string $widget_output  The widget's output.
             * @param string $widget_id_base The widget's base ID.
             * @param string $widget_id      The widget's full ID.
             * @param string $sidebar_id     The current sidebar ID.
             */
            echo apply_filters( 'widget_output', $widget_output, $widget_id_base, $widget_id, $sidebar_id );
        }
    }
}

new Widget_Output_Filters();