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
	 * @param string $directory
	 * @param bool $deleteDir
	 * @return success
	 * implementation from http://lixlpixel.org/recursive_function/php/recursive_directory_delete/
	 */
	public static function unlinkRecursive ($directory, $deleteDir = FALSE) {
		if (substr($directory,-1) == '/') {
			$directory = substr($directory,0,-1);
		}
		if (!file_exists($directory) || !is_dir($directory)) {
			return FALSE;
		} elseif (is_readable($directory)) {
			$handle = opendir($directory);
			while (FALSE !== ($item = readdir($handle)))   {
				if ($item != '.' && $item != '..') {
					$path = $directory.'/'.$item;
					if (is_dir($path)) {
						recursive_remove_directory($path);
					} else {
						unlink($path);
					}
				}
			}

			closedir($handle);
			if( $deleteDir == FALSE) {
				if(!rmdir($directory)) {
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	/* --- Hidden details --- */

}