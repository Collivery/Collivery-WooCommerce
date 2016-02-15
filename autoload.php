<?php

class MdsColliveryAutoLoader
{
	protected static $classMap = array(
		'MdsCache' => '\MdsSupportingClasses\MdsCache',
		'MdsCollivery' => '\MdsSupportingClasses\Collivery',
		'GitHubPluginUpdater' => '\MdsSupportingClasses\GitHubPluginUpdater',
		'MdsColliveryService' => '\MdsSupportingClasses\MdsColliveryService',
		'ParseDown' => '\MdsSupportingClasses\ParseDown',
		'UnitConverter' => '\MdsSupportingClasses\UnitConverter',
	);

	public static function autoload($class)
	{
		$classParts = explode('\\', $class);
		$vendor = array_shift($classParts);
		if ($vendor === 'MdsSupportingClasses') {
			if(file_exists(_MDS_DIR_ . '/MdsSupportingClasses/' . implode('/', $classParts) . '.php')) {
				if (!class_exists('MdsSupportingClasses\\' . implode('/', $classParts))) {
					require _MDS_DIR_ . '/MdsSupportingClasses/' . implode('/', $classParts) . '.php';
				}
			}
		} elseif (array_key_exists($class, self::$classMap)) {
			class_alias(self::$classMap[$class], $class);
		}
	}
}

spl_autoload_register(
	'MdsColliveryAutoLoader::autoload',
	true
);
