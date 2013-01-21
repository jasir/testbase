<?php
/**
 * TestBase Framework loader.
 *
 * @package  TestBase
 * @author   Jaroslav Povolný <jaroslav.povolny@gmail.com>
 * @license  WTFPL
 **/

/* --- Fix include path problems --- */
$pearPath = getenv('PHP_PEAR_LIB') ?: 'c:/php/phped/php5/pear';
$path = ini_get('include_path');
if (!strpos($path, $pearPath)) {
	ini_set('include_path', $path . ';' . $pearPath);
}


require_once 'PHPUnit/Autoload.php';
require_once dirname(__FILE__) . '/Runner.php';
