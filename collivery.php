<?php
/**
 * @package MDS Collivery
 * @version 0.1
 */
/*
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.coffeecode.co.za/
 *
 * Description: Plugin to add support for MDS Collivery to WooCommerce
 * Author: Bernhard Breytenbach
 * Version: 0.1
 * Author URI: http://www.coffeecode.co.za/
 */

add_action('plugins_loaded', 'init_mds_collivery', 0);

function init_mds_collivery() {
  // Check if 'WC_Shipping_Method' class is loaded, else exit.
  if ( ! class_exists( 'WC_Shipping_Method' ) ) return;
  
  include_once( 'checkout_fields.php' ); //Seperate file with large arrays.
  require_once( 'mds-admin.php' ); //Admin Scripts
  
  add_action('wp_enqueue_scripts', 'load_js');

  //Load JS file
  function load_js() {
	wp_register_script(
			'mds_js',
			plugins_url('script.js', __FILE__),
			array('jquery')
	);
	wp_enqueue_script( 'mds_js' );
  }

  class WC_MDS_Collivery extends WC_Shipping_Method
  {
	function __construct()
	{
		$this -> id = 'mds_collivery';
		$this -> method_title = __('MDS Collivery', 'woocommerce');
		
		$this->admin_page_heading 		= __( 'MDS Collivery', 'woocommerce' );
		$this->admin_page_description 	= __( 'Seamlessly integrate your website with MDS Collivery', 'woocommerce' );
		
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );
		
		$this->init();
	}
	
	function init() {
		// Load the form fields.
		$this->init_form_fields();
	
		// Load the settings.
		$this->init_settings();
		
		$this->enabled			= $this->settings['enabled'];
		$this->title			= $this->settings['title'];
		
		// MDS Specific Values
		$this->mds_user			= $this->settings['mds_user'];
		$this->mds_pass			= $this->settings['mds_pass'];
		$this->markup			= $this->settings['markup'];
	}
	
	// Setup Soap Connection if not already active
	private function soap_init(){
		// Check if soap session exists
		if (!$this->soap){
			// Start Soap Client
			$this->soap = new SoapClient("http://www.collivery.co.za/webservice.php?wsdl");
			// Prevent caching of the wsdl
			ini_set("soap.wsdl_cache_enabled", "0");
			// Authenticate
			$authenticate = $this->soap->Authenticate($this->mds_user, $this->mds_pass, $_SESSION['token']);
			// Save Authentication token in session to identify the user again later
			$_SESSION['token'] = $authenticate['token'];
		
			if(!$authenticate['token']) {
				exit("Authentication Error : ".$authenticate['access']);
			}
			// Make authentication publically accessible
			$this->authenticate=$authenticate;
		}
	}
	
	/*
	 * Plugin Settings
	 */
	function init_form_fields() {
		global $woocommerce;
		$this->form_fields = array(
			'enabled' => array(
				'title'			=> __( 'Enabled?', 'woocommerce' ),
				'type'			=> 'checkbox',
				'label'			=> __( 'Enable this shipping method', 'woocommerce' ),
				'default'		=> 'yes',
			),
			'title' => array(
				'title' 		=> __( 'Method Title', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'		=> __( 'MDS Collivery', 'woocommerce' ),
			),
			'mds_user' => array(
				'title' 		=> "MDS ". __( 'Username', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( 'Email address associated with your MDS account.', 'woocommerce' ),
				'default'		=> "demo@collivery.co.za",
			),
			'mds_pass' => array(
				'title' 		=> "MDS ". __( 'Password', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( 'The password used when logging in to MDS.', 'woocommerce' ),
				'default'		=> "demo",
			),
			'markup' => array(
				'title' 		=> __( 'Markup', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( 'Charge clients x% more for shipping. Negative (-) numbers will decrease the cost.', 'woocommerce' ),
				'default'		=> 10,
			),
		);
	}
	
	function calculate_shipping($package = array())
	{
		$this->soap_init();
		
		// Capture the correct Town and CPType
		if (isset($_POST['post_data'])){
			parse_str($_POST['post_data'], $post_data);
			if ($post_data['shiptobilling']==TRUE){
				if ($post_data['billing_state']!='NA')
					$town_label = $post_data['billing_state'];
				if ($post_data['billing_cptypes']!='NA')
					$cptypes_label = $post_data['billing_cptypes'];
			} else {
				if ($post_data['shipping_state']!='NA')
					$town_label = $post_data['shipping_state'];
				if ($post_data['shipping_cptypes']!='NA')
					$cptypes_label = $post_data['shipping_cptypes'];
			}
		} else if (isset($_POST['shiptobilling'])){
			if ($_POST['shiptobilling']==TRUE){
				if ($_POST['billing_state']!='NA')
					$town_label = $_POST['billing_state'];
				if ($_POST['billinging_cptypes']!='NA')
					$cptypes_label = $post_data['shipping_cptypes'];
			} else {
				if ($_POST['shipping_state']!='NA')
					$town_label = $_POST['shipping_state'];
				if ($_POST['shipping_cptypes']!='NA')
					$cptypes_label = $post_data['shipping_cptypes'];
			}
		}
		
		if (isset($town_label)&&$this->get_code($this->get_towns(),$town_label)!=FALSE){
			$town_brief = $this->get_code($this->get_towns(),$town_label);
		} else {
			$my_address = $this->my_address();
			$town_brief=$my_address['results']['TownBrief'];
		}
		
		if (isset($cptypes_label)&&$this->get_code($this->get_cptypes(),$cptypes_label)!=FALSE)
			$town_type = $this->get_code($this->get_cptypes(),$cptypes_label);
		
		$services = $this->get_available_services();
		
		$cart = $this->get_cart_content($package);
		
		date_default_timezone_set('Africa/Johannesburg');
		
		$collection_time = 0;
		$delivery_time = 0;
		
		$weight = $cart['max_weight'];
		
		// Get pricing for each service
		foreach ($services as $id => $title) {
			$rate = array(
				'id' => $this->id .'_'. $id,
				'label' => $this->title .' - '. $title,
				'cost' => ($this->get_shipping_estimate(
						$town_brief,
						$town_type,
						$id,
						$weight,
						$collection_time,
						$delivery_time
					)*(1+($this->markup/100))),
			);
			if ($rate['cost']>0) $this->add_rate( $rate ); //Only add shipping if it has a value
		}
	}
	
	function get_cart_content($package){
		if (sizeof($package['contents'])>0){
			//Reset array to defaults
			$this->cart = array(
					'count' => 0,
					'weight' => 0,
					'max_weight' => 0,
					'products' => Array()
				);
			
			foreach ($package['contents'] as $item_id => $values){
				
				$_product = $values['data']; // = WC_Product class
				$qty = $values['quantity'];
				
				$this->cart['count'] += $qty;
				$this->cart['weight'] += $_product->get_weight() * $qty;
				
				// Work out Volumetric Weight based on MDS's calculations
				$vol_weight = (($_product->length * $_product->width * $_product->height) / 4000);
				
				if ($vol_weight>$_product->get_weight())
					$this->cart['max_weight'] += $vol_weight * $qty;
				else
					$this->cart['max_weight'] += $_product->get_weight() * $qty;
				
				for ($i=0; $i<$qty; $i++)
					$this->cart['products'][] = array(
							'length' => $_product->length,
							'width' => $_product->width,
							'height' => $_product->height,
							'weight' => $_product->get_weight()
						);
			}
		}
		
		return $this->cart;
	}
	
	/*
	 * Get a shipping estimate from MDS based on current data.
	 */
	function get_shipping_estimate($town_brief, $town_type, $service_type, $weight, $collection_time, $delivery_time){
		$my_address = $this->my_address();
		$data = array (
				'from_town_brief' => $my_address['results']['TownBrief'],
				'from_town_type' => $my_address['results']['CP_Type'],
				'to_town_brief' => $town_brief,
				'service_type' => $service_type,
				'mds_cover' => true,
				'weight' => $weight,
			);
		
		if ((isset($town_type)) && ($town_type!="NA"))
			$data['to_town_type'] = $town_type;
		
		if ((isset($collection_time)) && ($collection_time!=0))
			$data['collection_time'] = $collection_time;
		if ((isset($delivery_time)) && ($delivery_time!=0))
			$data['delivery_time'] = $delivery_time;
		
		$this->soap_init();
		
		$pricing = $this->soap->GetPricing($data,$_SESSION['token']);
		
		return $pricing['results']['Total'];
	}
	/*
	 * Get available Services from MDS
	 */
	
	function get_available_services(){
		$services = $this->soap->getServices($this->authenticate['token']);
		
		return $services['results'];
	}
	
	/*
	 * Get list of Towns from MDS
	 */
	public function get_towns(){
		if (!isset($this->towns))
		{
			$this->soap_init();
			$this->towns = $this->soap->getTowns(null,$this->authenticate['token']);
		}
		return $this->towns['results'];
	}
	
	/*
	 * Get list of Suburbs from MDS
	 */
	public function get_subs($town){
		$town_code = $this->get_code($this->get_towns(),$town);
		
		if (!isset($this->subs[$town_code]))
		{
			$this->soap_init();
			$this->subs[$town_code] = $this->soap->getSuburbs(null,$town_code,$this->authenticate['token']);
		}
		return $this->subs[$town_code]['results'];
	}
	
	/*
	 * Get list of CPTypes from MDS
	 */
	public function get_cptypes(){
		if (!isset($this->cptypes))
		{
			$this->soap_init();
			$this->cptypes = $this->soap->getCPTypes($this->authenticate['token']);
		}
		return $this->cptypes['results'];
	}
	
	/*
	 * Get Town and CPTypes for Checkout Dropdown's from MDS
	 */
	public function get_field_defaults(){
		
		$towns = $this->get_towns();
		foreach ($towns as $value) {
			$my_towns[$value] = $value;
		}
		$cpTypes = $this->get_cptypes();
		foreach ($cpTypes as $value) {
			$my_cpTypes[$value] = $value;
		}
		return Array('towns' => $my_towns, 'cptypes' => $my_cpTypes);
	}
	
	/*
	 * Get array key from label
	 */
	function get_code($array, $label){
		foreach($array as $key=>$value){
			if($label == $value){
				return $key;
			}
		}
		return false;
	}
	
	/*
	 * Bunch of MDS Functions
	 */
	
	public function addAddress($address)
	{
		$this->soap_init();
		return $this->soap->AddAddress($address,$this->authenticate['token']);
	}
	
	public function addContact($contact)
	{
		$this->soap_init();
		return $ctid = $this->soap->AddContact($contact,$this->authenticate['token']);
	}
	
	public function validate($data)
	{
		$this->soap_init();
		$validation = $this->soap->CheckColliveryData($data,$this->authenticate['token']);
		$validation['pricing'] = $this->soap->GetPricing($validation['results'],$this->authenticate['token']);
		return $validation;
	}
	
	public function register_shipping($data)
	{
		$this->soap_init();
		$new_collivery = $this->soap->AddCollivery($data,$this->authenticate['token']);
		if($new_collivery['results']) {
			$collivery_id = $new_collivery['results']['collivery_id'];
			$send_emails = 1;
			$this->soap->AcceptCollivery($collivery_id,$send_emails,$this->authenticate['token']);
		}
		return $new_collivery;
	}
	
	public function my_address()
	{
		if (!isset($this->my_address)){
			$default_address_id = $this->authenticate['DefaultAddressID'];
			$this->my_address = $this->get_client_address($default_address_id);
			$this->my_address['address_id'] = $default_address_id;
		}
		return $this->my_address;
	}
	
	public function my_contact()
	{
		if (!isset($this->my_contact)){
			$address = $this->my_address();
			$this->my_contact = $this->get_client_contact($address['address_id']);
			$first_contact_id = each($this->my_contact['results']);
			$this->my_contact['contact_id'] = $first_contact_id[0];
		}
		return $this->my_contact;
	}
	
	public function get_client_address($cpid)
	{
		if (!isset($this->client_address[$cpid])){
			$this->soap_init();
			$this->client_address[$cpid] = $this->soap->getClientAddresses(null,null,$cpid,null,$this->authenticate['token']);
			$this->client_address[$cpid]['address_id']=$this->client_address[$cpid]['results']['colliveryPoint_PK'];
		}
		return $this->client_address[$cpid];
	}
	
	public function get_client_contact($ctid)
	{
		if (!isset($this->client_contact[$ctid])){
			$this->soap_init();
			$this->client_contact[$ctid] = $this->soap->getCpContacts($ctid,$this->authenticate['token']);
		}
		return $this->client_contact[$ctid];
	}
  } // End MDS_Collivery Class
} // End init_mds_collivery()

/*
 * Register Plugin with WooCommerce
 */
function add_MDS_Collivery_method($methods)
{
	$methods[] = 'WC_MDS_Collivery';
	return $methods;
}
add_filter('woocommerce_shipping_methods', 'add_MDS_Collivery_method');

/*
 * WooCommerce caches pricing information.
 * This adds cptypes to the hash to update pricing cache when changed.
 */
function mds_collivery_cart_shipping_packages($packages)
{
	if (isset($_POST['post_data'])){
		parse_str($_POST['post_data'], $post_data);
		$cptypes = $post_data['billing_cptypes'] . $post_data['shipping_cptypes'];
	} else if (isset($_POST['billing_cptypes']) || isset($_POST['shipping_cptypes'])){
		$cptypes = $_POST['billing_cptypes'] . $_POST['shipping_cptypes'];
	} else {
		//Bad Practice... But incase cptypes isn't set, do not cache the order!
		//@TODO: Find a way to fix this
		$cptypes = rand(0,999999999999999999) . rand(0,999999999999999999) . rand(0,999999999999999999) . rand(0,999999999999999999);
	}
	$packages[0]['destination']['cptypes'] = $cptypes;
	return $packages;
}
add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');

/*
 * Save custom shipping fields to order
 */
function mds_collivery_checkout_update_order_meta( $order_id ) {
	if ($_POST['shiptobilling']==true){
		if ($_POST['billing_cptypes']) update_post_meta( $order_id, 'mds_cptypes', esc_attr($_POST['billing_cptypes']));
		if ($_POST['billing_building_details']) update_post_meta( $order_id, 'mds_building', esc_attr($_POST['billing_building_details']));
	} else {
		if ($_POST['shipping_cptypes']) update_post_meta( $order_id, 'mds_cptypes', esc_attr($_POST['shipping_cptypes']));
		if ($_POST['shipping_building_details']) update_post_meta( $order_id, 'mds_building', esc_attr($_POST['shipping_building_details']));
	}
	
}
add_action('woocommerce_checkout_update_order_meta', 'mds_collivery_checkout_update_order_meta');
