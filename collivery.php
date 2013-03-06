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
  
  include_once 'checkout_fields.php'; //Seperate file with large arrays.
  
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

/*
 * Add a button to order page to register shipping with MDS
 */
function mds_order_actions( $post_id ) {
	?>
	<li><input type="submit" class="button tips" name="mds_confirm_shipping" value="<?php _e('Confirm Shipping', 'woocommerce'); ?>" data-tip="<?php _e('Register Shipping with MDS Collivery.', 'woocommerce'); ?>" /></li>
	<?php
}
add_action('woocommerce_order_actions', 'mds_order_actions');

/*
 * Redirect Admin to plugin page to register the Collivery
 */
function mds_process_order_meta( $post_id, $post) {	
	if ( isset( $_POST['mds_confirm_shipping'] ) && $_POST['mds_confirm_shipping'] ) {
		wp_redirect(home_url() . '/wp-admin/edit.php?page=mds_register&post_id='. $post_id); die;
	}
}
add_action('woocommerce_process_shop_order_meta', 'mds_process_order_meta', 20, 2);

/*
 * WordPress Backend to Register Collivery
 */
function mds_add_options() {
	add_submenu_page( null, 'Register Collivery', null, 8, 'mds_register', 'mds_register_collivery' );
}
add_action('admin_menu', 'mds_add_options');

function mds_register_collivery() {
	global $woocommerce, $woocommerce_errors;
	
	$order = new WC_Order( $_GET['post_id'] );
	$custom_fields= $order->order_custom_fields;

	$mds = new WC_MDS_Collivery;
	
	if ($custom_fields['_mds_status'][0]==1)
		echo "<h1 class=\"red\">Error. Shipping has already been registered with MDS.</h1>";
	
	else if ($order->status!='processing'){
		switch ($order->status) {
			case 'on-hold':
				echo "<h1 class=\"red\">Error. Order Status \"on-hold\". Payment might not have yet been processed!</h1>";
				break;
			
			case 'completed':
				echo "<h1 class=\"red\">Error. Order Status \"completed\". Shipping has already been processed!</h1>";
				break;
			
			default:
				echo "<h1 class=\"red\">Error. Order Status must be set to \"processing\" before shipping can be processed.</h1>";
				break;
		}		
	} else if ( isset( $_POST['mds_confirm_shipping_info'] ) && $_POST['mds_confirm_shipping_info'] ) {
		$validation = stripslashes($_POST['validation']);
		$data=json_decode($validation, TRUE);
		$new_col = $mds->register_shipping($data);
		$order->update_status('completed', 'MDS Shipping registered successfully.');
		update_post_meta( $order->id, '_mds_status', 1 );
		echo "<a href=\"". home_url() ."/wp-admin/post.php?post=$_GET[post_id]&action=edit\">Shipping registered successfully. Return to Order.</a>";
	} else {
		
		$cptypes = $mds->get_cptypes();
		
		$cptype = $mds->get_code($cptypes, $custom_fields['mds_cptypes'][0]);
		$town = $mds->get_code($mds->get_towns(), $order->shipping_state);
		$suburb = $mds->get_code($mds->get_subs($order->shipping_state), $order->shipping_city);
		
		$address=array(
			'companyName'=>$order->shipping_company,
			'CP_Type'=>$cptype,
			'TownBrief'=>$town,
			'mapID'=>$suburb,
			'building'=>$custom_fields['mds_building'][0],
			'streetnum'=>$order->shipping_address_1,
			'street'=>$order->shipping_address_2,
			);
		$contact=array(
			'fname'=>$order->shipping_first_name .' '. $order->shipping_last_name,
			'cellNo'=>$custom_fields['_shipping_phone'][0],
			'emailAddr'=>$custom_fields['_shipping_email'][0],
			);
		$my_address=$mds->my_address();
		$my_contact=$mds->my_contact();
		?>
		<style>
			.green {
				color: green;
			}
			.red {
				color: red;
			}
		</style>
		<h1>Confirm Shipping Information</h1>
		<div style="float:left; width: 50%">
			<h2>Client Information</h2>
			<table>
				<tr>
					<td>Name</td>
					<td><?php echo $contact['fname']; ?></td>
				</tr>
				<tr>
					<td>Number</td>
					<td><?php echo $contact['cellNo']; ?></td>
				</tr>
				<tr>
					<td>Email Address</td>
					<td><?php echo $contact['emailAddr']; ?></td>
				</tr>
				<tr>
					<td>Company Name</td>
					<td><?php echo $order->shipping_company; ?></td>
				</tr>
				<tr>
					<td>Location Type</td>
					<td><?php echo $custom_fields['mds_cptypes'][0]; ?></td>
				</tr>
				<tr>
					<td>Town</td>
					<td><?php echo $order->shipping_state; ?></td>
				</tr>
				<tr>
					<td>Suburb</td>
					<td><?php echo $order->shipping_city; ?></td>
				</tr>
				<tr>
					<td>Building Details</td>
					<td><?php echo $custom_fields['mds_building'][0]; ?></td>
				</tr>
				<tr>
					<td>Street Number</td>
					<td><?php echo $order->shipping_address_1; ?></td>
				</tr>
				<tr>
					<td>Street Name</td>
					<td><?php echo $order->shipping_address_2; ?></td>
				</tr>
			</table>
			<?php
				if (!isset( $_POST['mds_confirm_shipping_info'])&&!isset( $_POST['mds_add_client'])){ ?>
					<form method="post">
						<input type="submit" class="button tips" name="mds_add_client" value="<?php _e('Confirm Address', 'woocommerce'); ?>" data-tip="<?php _e('Add client to MDS database.', 'woocommerce'); ?>" />
					</form>
				<?php }
			?>
		</div>
		<div style="float:right;  width: 50%">
			<h2>My Information</h2>
			<table>
				<tr>
					<td>Name</td>
					<td><?php echo $my_contact['results'][$my_contact['contact_id']]['fname']; ?></td>
				</tr>
				<tr>
					<td>Number</td>
					<td><?php echo $my_contact['results'][$my_contact['contact_id']]['cellNo']; ?></td>
				</tr>
				<tr>
					<td>Email Address</td>
					<td><?php echo $my_contact['results'][$my_contact['contact_id']]['emailAddr']; ?></td>
				</tr>
				<tr>
					<td>Company Name</td>
					<td><?php echo $my_address['results']['companyName']; ?></td>
				</tr>
				<tr>
					<td>Location Type</td>
					<td><?php echo $cptypes[$my_address['results']['CP_Type']]; ?></td>
				</tr>
				<tr>
					<td>Town</td>
					<td><?php echo $my_address['results']['TownName']; ?></td>
				</tr>
				<tr>
					<td>Suburb</td>
					<td><?php echo $my_address['results']['Suburb']; ?></td>
				</tr>
				<tr>
					<td>Building Details</td>
					<td><?php //echo $contact['address']['results']['emailAddr']; ?></td>
				</tr>
				<tr>
					<td>Street Number</td>
					<td><?php echo $my_address['results']['Address3']; ?></td>
				</tr>
				<tr>
					<td>Street Name</td>
					<td><?php echo $my_address['results']['Address2']; ?></td>
				</tr>
			</table>
		</div>
		<?php
		if ( isset( $_POST['mds_add_client'] ) && $_POST['mds_add_client'] ) {
			if (isset($custom_fields['_mds_address_id'][0])&&$custom_fields['_mds_address_hash'][0]==md5(implode(',', $address)))
			{
				$cpid['results']=$custom_fields['_mds_address_id'][0];
				echo "<p class=\"green\"><strong>Address already added, using previous ID</strong></p>";
			} else {
				$cpid = $mds->addAddress($address);
			}
			
			$contact['cpid'] = $cpid['results'];
			
			if($cpid['error_message']) {
				print("Error - ".$cpid['error_message']);
			} else {
				update_post_meta( $order->id, '_mds_address_id', $cpid['results'] );
				update_post_meta( $order->id, '_mds_address_hash', md5(implode(',', $address)) );
				if (isset($custom_fields['_mds_contact_id'][0])&&$custom_fields['_mds_contact_hash'][0]==md5(implode(',', $contact)))
				{
					$ctid['results'] = $custom_fields['_mds_contact_id'][0];
					echo "<p class=\"green\"><strong>Contact already added, using previous ID</strong></p>";
				} else {
					$ctid = $mds->addContact($contact);
				}
				if($ctid['error_message'])
					print("Error - ".$ctid['error_message']);
				else{
					update_post_meta( $order->id, '_mds_contact_id', $ctid['results'] );
					update_post_meta( $order->id, '_mds_contact_hash', md5(implode(',', $contact)) );
					print("</strong></p><p class=\"green\"><strong>Address and Contact added succesfully!</strong></p>");
					$items = unserialize($custom_fields['_order_items'][0]);
					
					$collivery_data = mds_get_items($items);
					$collivery_data['collivery_from']=$my_address['address_id'];
					$collivery_data['contact_from']=$my_contact['contact_id'];
					$collivery_data['collivery_to']=$cpid['results'];
					$collivery_data['contact_to']=$ctid['results'];
					$collivery_data['collivery_type']=2;
					$collivery_data['service']=5;
					$collivery_data['mds_cover']=true;
					//print_r($collivery_data);
					$mds_contact = $mds->get_client_contact($cpid['results']);
					$mds_address = $mds->get_client_address($cpid['results']);
					
					$validation = $mds->validate($collivery_data);
					?>
					<h2>Validated Information</h2>
					<table>
						<tr>
							<th>Description</th>
							<th>Data</th>
						</tr>
						<tr>
							<td>From (Address)</td>
							<td style="text-align: right;"><?php echo $validation['results']['collivery_from']; ?></td>
						</tr>
						<tr>
							<td>From (Contact)</td>
							<td style="text-align: right;"><?php echo $validation['results']['contact_from']; ?></td>
						</tr>
						<tr>
							<td>To (Address)</td>
							<td style="text-align: right;"><?php echo $validation['results']['collivery_to']; ?></td>
						</tr>
						<tr>
							<td>To (Contact)</td>
							<td style="text-align: right;"><?php echo $validation['results']['contact_to']; ?></td>
						</tr>
						<tr>
							<td>Collivery Type</td>
							<td style="text-align: right;"><?php echo $validation['results']['collivery_type']; ?></td>
						</tr>
						<tr>
							<td>Number of Packages</td>
							<td style="text-align: right;"><?php echo $validation['results']['num_package']; ?></td>
						</tr>
						<tr>
							<td>Weight</td>
							<td style="text-align: right;"><?php echo $validation['results']['weight']; ?> Kg</td>
						</tr>
						<tr>
							<td>Volumetric Weight</td>
							<td style="text-align: right;"><?php echo $validation['results']['vol_weight']; ?> Kg</td>
						</tr>
						<tr>
							<td>Service</td>
							<td style="text-align: right;"><?php echo $validation['results']['service']; ?></td>
						</tr>
						<tr>
							<td>MDS Cover</td>
							<td style="text-align: right;"><?php echo ($validation['results']['mds_cover']==1) ? "Yes" : "No"; ?></td>
						</tr>
						<tr>
							<td>Collection Time</td>
							<td style="text-align: right;"><?php echo date("H\:i \- D\, d F Y",$validation['results']['collection_time']); ?></td>
						</tr>
						<tr>
							<td>Delivery Time</td>
							<td style="text-align: right;"><?php echo date("H\:i \- D\, d F Y",$validation['results']['delivery_time']); ?></td>
						</tr>
					</table>
					<p>Client Charged: <strong>R<?php echo $order->order_shipping; ?></strong><br>Actual Price: <strong>R<?php echo $validation['pricing']['results']['Total']; ?></strong></p>
					<form method="post">
						<input type="hidden" name="validation" value="<?php echo htmlentities(json_encode($validation['results'])); ?>">
						<input type="submit" class="button tips" name="mds_confirm_shipping_info" value="<?php _e('Accept Collivery', 'woocommerce'); ?>" data-tip="<?php _e('Accept the Collivery and send someone to pickup shipping.', 'woocommerce'); ?>" />
					</form>
					<?php
					
					
					
				}
			}
		}
	}
}

/*
 * Creates array with parcel information from Order
 */
function mds_get_items($items)
{
	$qty=0;
	$weight=0;
	foreach ($items as $item) {
		$product = new WC_Product($item['id']);
		$qty+=$item['qty'];
		
		$weight += $product->weight * $item['qty'];
		
		for ($i=0; $i < $item['qty']; $i++) { 
			$parcels[] = array("length" => $product->length, "width" => $product->width, "height" => $product->height, "weight" => $product->weight);
		}
	}
	return array(
		//'num_package'=>0,
		'weight'=>round($weight, 1),
		'parcels'=>$parcels,
		);
}