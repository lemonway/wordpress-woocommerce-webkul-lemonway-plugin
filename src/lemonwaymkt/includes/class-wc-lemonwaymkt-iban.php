<?php

class WC_Lemonwaymkt_Iban {
	
	/**
	 * 
	 * @var WC_Gateway_Lemonway
	 */
	protected $gateway;
	
	protected $table_name;
	
	public function __construct(){
		global $wpdb;
		$this->table_name = $wpdb->prefix.'lemonway_iban';
		$this->gateway = new WC_Gateway_Lemonway();
	}
	
	public function hasIban($userId){
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE id_customer=".(int)$userId ) > 0;
	}
	
	/**
	 * 
	 * @param array $ibanData
	 * @param string $walletId
	 * @throws Exception
	 */
	public function registerIban(array $ibanData,$walletId){
		
		
		
			$ibanData['wallet'] = $walletId;
			$ibanData['comment'] = "";
			$ibanData['ignoreIbanFormat'] = 0;

			$kit = $this->gateway->getDirectkit();
			
			try {
					
				$res = $kit->RegisterIBAN($ibanData);
				
				$ibanData['id_customer'] = wp_get_current_user()->ID;
				$ibanData['id_wallet'] = $walletId;
				$ibanData['id_lw_iban'] = $res->ID;
				$ibanData['id_status'] = $res->STATUS;
				
				//UNSET unknow datas for db
				unset($ibanData['wallet']);
				unset($ibanData['ignoreIbanFormat']);
				
				$this->save($ibanData);
					
					
			} catch (DirectkitException $de) {
			
				throw $de;
				
			
			} catch (Exception $e) {
					
				throw $e;
		
			}
		
		
	}
	
	
	/**
	 * 
	 * @param array $ibanData
	 */
	public function save($ibanData){
		global $wpdb;
			
		return $wpdb->insert($this->table_name, $ibanData);
		
	}
	
	public function delete($ibanId){

	}
	
	public function getIbansByUser($userId){
		global $wpdb;
		
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id_customer=%d", (int)$userId));
	}
	

	
}