<?php

class MdsColliveryAutoLoader
{
	protected static $classMap = array(
		'MdsCollivery' => '\MdsSupportingClasses\Collivery',
		'EnvironmentInformationBag' => '\MdsSupportingClasses\EnvironmentInformationBag',
		'GitHubPluginUpdater' => '\MdsSupportingClasses\GitHubPluginUpdater',
		'MdsCache' => '\MdsSupportingClasses\MdsCache',
		'MdsCheckoutFields' => '\MdsSupportingClasses\MdsCheckoutFields',
		'MdsColliveryService' => '\MdsSupportingClasses\MdsColliveryService',
		'MdsLogger' => '\MdsSupportingClasses\MdsLogger',
		'Money' => '\MdsSupportingClasses\Money',
		'ParseDown' => '\MdsSupportingClasses\ParseDown',
		'UnitConverter' => '\MdsSupportingClasses\UnitConverter',
		'View' => '\MdsSupportingClasses\View',
		'DiscountCalculator' => '\MdsSupportingClasses\DiscountCalculator',
		'ExceptionMiddleware' => '\MdsExceptions\ExceptionMiddleware',
		'InvalidAddressDataException' => '\MdsExceptions\InvalidAddressDataException',
		'InvalidCartPackageException' => '\MdsExceptions\InvalidCartPackageException',
		'InvalidColliveryDataException' => '\MdsExceptions\InvalidColliveryDataException',
		'InvalidResourceDataException' => '\MdsExceptions\InvalidResourceDataException',
		'InvalidServiceException' => '\MdsExceptions\InvalidServiceException',
		'OrderAlreadyProcessedException' => '\MdsExceptions\OrderAlreadyProcessedException',
		'ProductOutOfStockException' => '\MdsExceptions\ProductOutOfStockException',
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
