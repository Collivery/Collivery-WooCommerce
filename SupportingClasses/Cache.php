<?php namespace SupportingClasses;

class Cache {

	/**
	 * @type string
	 */
	private $cache_dir;

	/**
	 * @type
	 */
	private $cache;

	/**
	 * @param string $cache_dir
	 */
	function __construct($cache_dir = 'cache/mds_collivery/')
	{
		if ($cache_dir === null) {
			$cache_dir = 'cache/mds_collivery/';
		}

		$this->cache_dir = $cache_dir;
	}

	/**
	 * Creates the cache directory
	 *
	 * @param $dir_array
	 */
	protected function create_dir($dir_array)
	{
		if ( !is_array($dir_array) ) {
			$dir_array = explode('/', $this->cache_dir);
		}

		array_pop( $dir_array );
		$dir = implode( '/', $dir_array );

		if ( $dir!='' && ! is_dir( $dir ) ) {
			$this->create_dir( $dir_array );
			mkdir( $dir );
		}
	}

	/**
	 * Loads a specific cache file else creates the cache directory
	 *
	 * @param $name
	 * @return mixed
	 */
	protected function load( $name )
	{
		if ( ! isset( $this->cache[ $name ] ) ) {
			if ( file_exists( $this->cache_dir . $name ) && $content = file_get_contents( $this->cache_dir . $name ) ) {
				$this->cache[ $name ] = json_decode( $content, true );
				return $this->cache[ $name ];
			} else {
				$this->create_dir($this->cache_dir);
			}
		} else {
			return $this->cache[ $name ];
		}
	}

	/**
	 * Determines if a specific cache file exists and is valid
	 *
	 * @param $name
	 * @return bool
	 */
	public function has( $name )
	{
		$cache = $this->load( $name );
		if ( is_array( $cache ) ) {
			if($cache['valid'] === 0 || ( $cache['valid'] - 30 ) > time() ) {
				return (!empty($cache['value'])) ? true : false;
			}
		}

		return false;
	}

	/**
	 * Gets a specific cache files contents
	 *
	 * @param $name
	 * @return null
	 */
	public function get( $name )
	{
		$cache = $this->load( $name );
		if ( is_array( $cache ) ) {
			if($cache['valid'] === 0 || ( $cache['valid'] - 30 ) > time() ) {
				return (!empty($cache['value'])) ? $cache['value'] : null;
			}
		}

		return null;
	}

	/**
	 * Creates a specific cache file
	 *
	 * @param $name
	 * @param $value
	 * @param int $time
	 * @return bool
	 */
	public function put( $name, $value, $time = 1440 )
	{
		$cache = array( 'value' => $value, 'valid' => time() + ( $time*60 ) );
		if ( file_put_contents( $this->cache_dir . $name, json_encode( $cache ) ) ) {
			$this->cache[ $name ] = $cache;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Forgets a specific cache file
	 *
	 * @param $name
	 * @return bool
	 */
	public function forget( $name )
	{
		$cache = array( 'value' => '', 'valid' => 0 );
		if ( file_put_contents( $this->cache_dir . $name, json_encode( $cache ) ) ) {
			$this->cache[ $name ] = $cache;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Clears all cache or only files matching a group name
	 *
	 * @param null|string|array $group
	 */
	public function clear( $group = null )
	{
		$map = $this->directory_map();
		if( is_array($group) ) {
			foreach( $group as $row ) {
				$this->forget_each_file( $map, $row );
			}
		} else {
			$this->forget_each_file( $map, $group );
		}
	}

	/**
	 * Forgets each file in the directory map or only files matching a group name
	 *
	 * @param array $map
	 * @param null $group
	 */
	private function forget_each_file( array $map, $group = null )
	{
		foreach( $map as $key => $row ) {
			if( $group ) {
				if( preg_match( '|' . preg_quote($group) . '|', $row ) ) {
					$this->forget( $row );
				}
			} else {
				$this->forget( $row );
			}
		}
	}

	/**
	 * Maps all files in the cache directory
	 *
	 * @return array|bool
	 */
	private function directory_map()
	{
		if ( $fp = @opendir( $this->cache_dir ) ) {
			$file_data = array();

			while ( FALSE !== ( $file = readdir( $fp ) ) ) {
				// Remove '.', '..', and hidden files
				if ( !trim( $file, '.' ) OR ( $file[0] == '.' ) ) {
					continue;
				}

				$file_data[] = $file;
			}

			closedir( $fp );
			return $file_data;
		}

		return false;
	}
}
