<?php namespace MdsSupportingClasses;

class MdsLogger
{
	/**
	 * @type string
	 */
	private $log_dir = 'cache/mds_collivery/';

	/**
	 * @param string $log_dir
	 */
	function __construct($log_dir = null)
	{
		if ($log_dir !== null) {
			$this->log_dir = $log_dir;
		}
	}

	/**
	 * Determines if a specific cache file exists and is valid
	 *
	 * @param $name
	 * @return bool
	 */
	public function has($name)
	{
		$log = $this->load($name);
		if ($log) {
			return $log;
		}

		return false;
	}

	/**
	 * @param $function
	 * @param $error
	 * @param $settings
	 * @param array $extraData
	 * @internal param $data
	 */
	public function error($function, $error, $settings, $extraData = [])
	{
		$this->put('error', [
			'function' => $function,
			'error' => $error,
			'settings' => $settings,
			'data' => $extraData,
		]);
	}

	/**
	 * @param $function
	 * @param $error
	 * @param $settings
	 * @param array $extraData
	 * @internal param $data
	 */
	public function warning($function, $error, $settings, $extraData = [])
	{
		$this->put('warning', [
			'function' => $function,
			'error' => $error,
			'settings' => $settings,
			'data' => $extraData,
		]);
	}

	/**
	 * Loads a specific log file else creates the log directory
	 *
	 * @param $name
	 * @return mixed
	 */
	protected function load($name)
	{
		if (file_exists($this->log_dir . $name) && $content = file_get_contents($this->log_dir . $name)) {
			return $content;
		} else {
			$this->create_dir($this->log_dir);
		}
	}

	/**
	 * Gets a specific cache files contents
	 *
	 * @param $name
	 * @return null
	 */
	public function get($name)
	{
		if ($log = $this->has($name)) {
			return json_decode($log);
		}

		return null;
	}

	/**
	 * Creates a specific cache file
	 *
	 * @param $name
	 * @param $value
	 * @return bool
	 */
	protected function put($name, $value)
	{
		if(file_exists($this->log_dir . $name)) {
			if(filemtime($this->log_dir . $name) < strtotime(date('Y-m-d') . ' 00:00:00')) {
				unlink($this->log_dir . $name);
			}
		}

		if (file_put_contents($this->log_dir . $name, json_encode(array(time() => $value), JSON_PRETTY_PRINT), FILE_APPEND)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Creates the cache directory
	 *
	 * @param $dir_array
	 */
	protected function create_dir($dir_array)
	{
		if (!is_array($dir_array)) {
			$dir_array = explode('/', $this->log_dir);
		}

		array_pop($dir_array);
		$dir = implode('/', $dir_array);

		if ($dir != '' && !is_dir($dir)) {
			$this->create_dir($dir_array);
			mkdir($dir);
		}
	}
}
