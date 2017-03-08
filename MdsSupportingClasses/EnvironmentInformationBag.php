<?php namespace MdsSupportingClasses;

class EnvironmentInformationBag
{
	/**
	 * @var string
	 */
	public $wordpressVersion;

	/**
	 * @var string
	 */
	public $woocommerceVersion;

	/**
	 * @var string
	 */
	public $appName;

	/**
	 * @var string
	 */
	public $appVersion;

	/**
	 * @var string
	 */
	public $appHost;

	/**
	 * @var string
	 */
	public $appUrl;

	/**
	 * @var string
	 */
	public $phpVersion;

	/**
	 * @var string
	 */
	public $soapInstalled;

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * EnvironmentInformation constructor.
	 * @param array $settings
	 */
	public function __construct($settings = null)
	{
		global $wp_version;

		if(is_array($settings)) {
			$this->setSettings($settings);
		}

		$this->wordpressVersion = $wp_version;
		$this->woocommerceVersion = $this->getWoocommerceVersionNumber();
		$this->appHost = 'Wordpress: ' . $wp_version . ' - WooCommerce: ' . $this->wordpressVersion;
		$this->appUrl = get_site_url();
		$this->appName = 'WooCommerce Plugin - ' . preg_replace('/^(http|https):\/\//i', '', $this->appUrl);
		$this->phpVersion = phpversion();
		$this->appVersion = MDS_VERSION;
		$this->soapInstalled = (extension_loaded('soap')) ? 'yes' : 'no';
	}

	/**
	 * @param array $settings
	 */
	public function setSettings(array $settings)
	{
		if(isset($settings['mds_pass'])) {
			unset($settings['mds_pass']);
		}

		$this->settings = $settings;
	}

	/**
	 * @return array
	 */
	public function loggerFormat()
	{
		return array(
			'wordpressVersion' => $this->wordpressVersion,
			'woocommerceVersion' => $this->woocommerceVersion,
			'appHost' => $this->appHost,
			'appName' => $this->appName,
			'phpVersion' => $this->phpVersion,
			'appVersion' => $this->appVersion,
			'appUrl' => $this->appUrl,
			'soapInstalled' => $this->soapInstalled,
		) + $this->settings;
	}

	/**
	 * @return null
	 */
	private function getWoocommerceVersionNumber()
	{
		// If get_plugins() isn't available, require it
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Create the plugins folder and file variables
		$plugin_folder = get_plugins('/' . 'woocommerce');
		$plugin_file = 'woocommerce.php';

		// If the plugin version number is set, return it
		if (isset($plugin_folder[$plugin_file]['Version'])) {
			return $plugin_folder[$plugin_file]['Version'];
		} else {
			// Otherwise return null
			return NULL;
		}
	}
}
