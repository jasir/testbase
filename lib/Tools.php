<?php
/**
 * Useful tool functions for building tests.
 *
 * @package  TestBase
 * @author   Jaroslav Povolny (jasir) <jaroslav.povolny@gmail.com>
 * @license  WTFPL
 **/

namespace TestBase;

class Tools {

	/* --- Properties --- */

	/* --- Public API --- */

	/**
	 * Deletes file
	 * @param string $fileName
	 */

	public static function deleteFile($fileName) {
		if (file_exists($fileName)) {
			unlink($fileName);
		}
	}

	/**
	 * Recursively deletes directory
	 * @param string $dir
	 * @param boolean $deleteRootToo
	 */
	public static function unlinkRecursive($dir, $deleteRootToo=FALSE) {
		if(!$dh = @opendir($dir)) {
			return;
		}
		while (FALSE !== ($obj = readdir($dh))) {
			if($obj == '.' || $obj == '..') {
				continue;
			}
			if (!@unlink($dir . '/' . $obj)) {
				self::unlinkRecursive($dir.'/'.$obj, TRUE);
			}
		}

		closedir($dh);
		if ($deleteRootToo) {
			@rmdir($dir);
		}
		return;
	}

	/* --- Hidden details --- */

}