<?php

class WC_Lemonwaymkt_Wallet {
	
	/**
	 * 
	 * @var WC_Gateway_Lemonway
	 */
	protected $gateway;
	
	protected $table_name;
	
	public function __construct(){
		global $wpdb;
		$this->table_name = $wpdb->prefix.'lemonway_wallet';
		$this->gateway = new WC_Gateway_Lemonway();
	}
	
	public function hasWallet($userId){
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE id_customer=" .(int)$userId ) > 0;
	}
	
	/**
	 * 
	 * @param WP_User $user
	 * @param array $sellerInfo
	 * @throws Exception
	 */
	public function registerWallet($user,$sellerId){
		
		if(!$sellerId){			
			throw new Exception(__("Seller infos not found!",LEMONWAYMKT_TEXT_DOMAIN));
		}
		
			$wallet = array();
			$wallet['is_admin'] = false;
			$wallet['is_default'] = true;
			$wallet['id_customer'] = $user->ID;
			
			$params = array();
		
			$params['wallet'] = "wallet-" .$sellerId. "-" . $user->ID;
			$wallet['id_lw_wallet'] = $params['wallet'];
		
			$params['clientMail'] = $user->user_email;
			$wallet['customer_email'] = $params['clientMail'];
			
			//No prefix or gender by default
			$wallet['customer_prefix'] = '';
			$params['clientTitle'] = '';
			
		
			$params['clientFirstName'] = $user->user_firstname;
			$wallet['customer_firstname'] = $params['clientFirstName'];
		
			$params['clientLastName'] = $user->user_lastname;
			$wallet['customer_lastname'] = $params['clientLastName'];
		
			$params['street'] = ''; //@TODO maybe get customer address because shop address is all(street,postcode,city...) in one field
			$params['postCode'] = '';
			$params['city'] = '';
			$params['ctry'] = '';
		
			//No birthdate by default
			$params['birthdate'] = "";
			
			//No phone by default
			$params['phoneNumber'] = '';
			$wallet['billing_address_phone'] = $params['phoneNumber'];
			
			//No mobile by default
			$params['mobileNumber'] = "";
			$wallet['billing_address_mobile'] = $params['mobileNumber'];
			
			$params['isCompany'] = 1;
			$wallet['is_company'] = 1;
		
			$params['companyName'] = get_user_meta($user->ID,'shop_name',true);
			$wallet['company_name'] = $params['companyName'] ;
		
			$params['companyWebsite'] = '';
			$wallet['company_website'] = $params['companyWebsite'];
		
			$params['companyDescription'] = '';
			$wallet['company_description'] = '';

			$params['companyIdentificationNumber'] = "";
			$wallet['company_id_number'] = $params['companyIdentificationNumber'];
			
		
			$params['isDebtor'] = 0;
			$wallet['is_debtor'] = $params['isDebtor'] ;
		
			$params['nationality'] = '';
			$params['birthcity'] = '';
			$params['birthcountry'] = '';
			$params['payerOrBeneficiary'] = 2; //1 for payer, 2 for beneficiary
			$wallet['payer_or_beneficiary'] = $params['payerOrBeneficiary'] ;
		
			$params['isOneTimeCustomer'] = 0;
			$wallet['is_onetime_customer'] = $params['isOneTimeCustomer'] ;
			
			$kit = $this->gateway->getDirectkit();
			
			try {
					
				$kit->RegisterWallet($params);
		
				$this->save($wallet);
					
					
			} catch (DirectkitException $de) {
				
				//Wallet already exists on Lemonway but not in db
				//So, we re-save the wallet
				if((int)$de->getCode() == 152){ 
					$this->save($wallet);
				}
				else{				
					throw $de;
				}
			
			} catch (Exception $e) {
					
				throw $e;
		
			}
		
			return $wallet;
		
	}
	
	/**
	 * 
	 * @param string $walletId
	 * @throws Exception
	 * @return Wallet
	 */
	public function getWalletDetails($walletId){
		
		$kit = $this->gateway->getDirectkit();
		
		try {
				
			return $kit->GetWalletDetails(array('wallet'=>$walletId,'email'=>''));
				
		} catch (Exception $e) {
				
			throw $e;
		
		}
	}
	
	/**
	 * 
	 * @param array $wallet
	 */
	public function save($wallet){
		global $wpdb;
			
		return $wpdb->insert($this->table_name, $wallet);
		
	}
	
	public function delete($walletId){

	}
	
	public function getWalletByUser($userId){
		global $wpdb;
		
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id_customer=%d", (int)$userId));
	}
	

	
}