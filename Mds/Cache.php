<?php namespace Mds;

class Cache
{

	private static $cache_dir = 'cache/mds_collivery/';
	private static $cache;

	protected static function load( $name )
	{
		print_r($cache_dir);
		if ( ! isset( Cache::$cache[ $name ] ) ) {
			if ( file_exists( Cache::$cache_dir . $name ) && $content = file_get_contents( Cache::$cache_dir . $name ) ) {
				Cache::$cache[ $name ] = json_decode( $content, true );
				return Cache::$cache[ $name ];
			} else {
				if ( ! is_dir( Cache::$cache_dir ) ) {
					mkdir( Cache::$cache_dir );
				}
			}
		} else {
			return Cache::$cache[ $name ];
		}
	}

	public static function has( $name )
	{
		$cache = Cache::load( $name );
		if ( is_array( $cache ) && ( $cache['valid'] - 30 ) > time() ) {
			return true;
		} else {
			return false;
		}
	}

	public static function get( $name )
	{
		$cache = Cache::load( $name );
		if ( is_array( $cache ) && $cache['valid'] > time() ) {
			return $cache['value'];
		} else {
			return null;
		}
	}

	public static function put( $name, $value, $time = 1440 )
	{
		$cache = array( 'value' => $value, 'valid' => time() + ( $time*60 ) );
		if ( file_put_contents( Cache::$cache_dir . $name, json_encode( $cache ) ) ) {
			Cache::$cache[ $name ] = $cache;
			return true;
		} else {
			return false;
		}
	}

	public static function forget( $name )
	{
		$cache = array( 'value' => '', 'valid' => 0 );
		if ( file_put_contents( Cache::$cache_dir . $name, json_encode( $cache ) ) ) {
			Cache::$cache[ $name ] = $cache;
			return true;
		} else {
			return false;
		}
	}
}
