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
		'DiscountCalculator' => '\MdsSupportingClasses\DiscountCalculator',
		'ExceptionMiddleware' => '\MdsExceptions\ExceptionMiddleware',
		'InvalidAddressDataException' => '\MdsExceptions\InvalidAddressDataException',
		'InvalidCartPackageException' => '\MdsExceptions\InvalidCartPackageException',
		'InvalidColliveryDataException' => '\MdsExceptions\InvalidColliveryDataException',
		'InvalidResourceDataException' => '\MdsExceptions\InvalidResourceDataException',
	);

	public static function autoload($class)
	{
		$classParts = explode('\\', $class);
		$vendor = array_shift($classParts);
		if ($vendor === 'MdsSupportingClasses' || $vendor === 'MdsExceptions') {
			if(file_exists(_MDS_DIR_ . '/' . $vendor . '/' . implode('/', $classParts) . '.php')) {
				if (!class_exists($vendor . '\\' . implode('/', $classParts))) {
					require _MDS_DIR_ . '/' . $vendor . '/' . implode('/', $classParts) . '.php';
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
