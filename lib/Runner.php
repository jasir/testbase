<?php
/**
* PhpUnit test custom runner.
*
* - Renders output to HTML if runned from browser.
* - Automatically decides between browser, cli, phpunit usage.
*
* Usage:
* <code>
* class SomeTest extends \TestBase\TestCase {
*   ...
* }
* \TestBase\Runner::test('SomeTest');
* </code>
*
* Example 2:
* <code>
* if (\TestBase\Runner::isRunnedByPHPUnit() === FALSE) {
*      $runner = new \TestBase\Runner();
*      $runner->addSuite('toStringTest');
*      $runner->run();
* }
* </code>
*
* @package    TestBase
* @author     Jaroslav Povolný <jaroslav.povolny@gmail.com>
* @license    WTFPL
* @todo       Support for suites
* @todo       Rewrite using own listener
**/

namespace TestBase;

use
	PHPUnit_Framework_TestSuite,
	PHPUnit_TextUI_TestRunner,
	SimpleXMLElement,
	PHPUnit_Framework_TestResult,
	PHPUnit_Util_Log_JUnit,
	Nette\Utils\Finder
;

class Runner
{

	private $suite;

	public function __construct() {
		$this->suite = new PHPUnit_Framework_TestSuite();
		$this->suite->setBackupGlobals(FALSE);
	}

	/**
	* Adds Test class to run.
	* @param string $testClass
	* @param string $suite
	*/
	protected function addTest($testClassName) {
		$this->suite->addSuite($testClassName);
	}

	/**
	 * Adds tests in given directory.
	 * Adds files with naming pattern '<name>Test.php'
	 * @param string $directory
	 * @param string $suite
	 */
	public function addDirectory($directory) {
		$dirIterator = new \DirectoryIterator($directory);
		foreach ($dirIterator as $file) {
			if ($file->isDot()) {
				continue;
			}
			if ($file->isDir()) {
				$this->addDirectory($file->getPathName());
				continue;
			}
			$fileName = $file->getFileName();
			if (preg_match('/(([a-zA-Z_][a-zA-Z0-9_]*))Test.php/', $fileName, $regs)) {
				$this->suite->addTestFile($file->getPathName());
			}
		}
	}

	/**
	 * Runs test suite and renders output according to environment (console,...)
	 */
	public function run() {
		ob_start();
		if ($this->isConsole() === TRUE) {
			$this->runInConsole();
		} else {
			$this->runInBrowser();
		}
		ob_end_flush();
	}

	private function runInBrowser() {
		$this->renderHtmlHead();
		$this->renderEnvironmentInfo();

		echo "<div id='results'>";

		foreach ($this->getTestClasses() as $suite => $classes) {

			$filter = $this->getFilter();
			if ($filter) {
				echo "<div class='filtername'>Filtered to <code>$filter</code>&nbsp;&nbsp;<a href='?'>clear filter</a></div>";
			}

			echo "<div class='suitename'>suite <code>$suite</code></div>";

			asort($classes);

			$notRunned = '';
			foreach($classes as $testClass) {
				if ($filter === NULL || strpos($testClass, $filter) === 0) {
					$result = $this->runOneTest($testClass);
					$this->renderTestResult($testClass, $result);
				} else {
					ob_start();
					echo "<div class='notrunned'>";
					$this->renderClassHeader(new \ReflectionClass($testClass));
					echo "</div>";
					$notRunned .= ob_get_contents();
					ob_end_clean();
				}
			}
		}
		if ($notRunned) {
			echo "<h3>Skipped tests: <a href='?'>clear filter</a></h3>";
			echo $notRunned;
		}
		echo "</div>";
		$this->renderHtmlEnd();
	}

	private function getFilter() {
		$filter = isset($_GET['filter']) ? $_GET['filter'] : NULL;
		return empty($filter) ? NULL : $filter;
	}

	/**
	 * Static function to run one test. Used inside test files.
	 * in IDE.
	 * @param string $testClass
	 */
	public static function test($testClass) {
		if (self::isRunnedByOthers() === FALSE) {
			$test = new self;
			$test->addTest($testClass, 'single file');
			$test->run();
		}
	}

	/**
	 * Runs one test
	 * @param string $testClass
	 * @return array result
	 */
	private function runOneTest($testClass) {
		$suite = new PHPUnit_Framework_TestSuite($testClass);
		$suite->setBackupGlobals(FALSE);
		$suite->setName($testClass);

		// Create a xml listener object
		$listener = new PHPUnit_Util_Log_JUnit(NULL, TRUE);

		// Create TestResult object and pass the xml listener to it
		$testResult = new PHPUnit_Framework_TestResult();
		$testResult->addListener($listener);

		// Run the TestSuite
		$result = $suite->run($testResult);

		// Get the results from the listener
		$xml_result = $listener->getXML();

		$result = $this->processXML($xml_result);
		//collect output
		$tests = $suite->tests();
		foreach($tests as $i => $test) {
			$output = NULL;
			if ($test instanceOf \PHPUnit_Framework_TestCase) {
				$output = $test->getActualOutput();
			}
			$result[$i]['output'] = $output;
		}
		return $result;
	}

	//---------------------------------------------------------------------------
	//-- Private functions ------------------------------------------------------
	//---------------------------------------------------------------------------

	private function renderTestResult($testClass, $test_results) {
		ob_start();
		$results = array('Total' => 0, 'Pass' => 0, 'Fail' => 0, 'Error' => 0, 'NRY' => 0, 'Skip' => 0);
		$classAnnotation = new \Nette\Reflection\ClassType($testClass);
		?>
		<table cellspacing="0" class="test_results" border=1>
		  <thead>
			 <tr><th>Result</th><th>Name</th><th title="count of assertions">Asserts<br>Count</th><th title="msec">Time<br>in ms</th><th>Output</th></tr>
		  </thead>
		  <tbody>

		  <?php
				foreach ($test_results as $test_result):
					if (!is_array($test_result) || !isset($test_result['result'])) {
						continue;
					}
					$result = $test_result['result'];
					if (!isset($results[$result])) {
						echo "Unknown result type \"$result\", please modify MyTestRunner.php";
						$results[$result] = 0;
					}
					$results[$result]++;
					$results['Total']++;
		  ?>

		  <tr>
				<?php if ($test_result['result'] === 'Fail') : ?>
					<td class="result test_fail"/><?php echo strtoupper($test_result['result'])?></td>
				<?php elseif ($test_result['result'] == 'Pass'): ?>
					<td class="result test_pass"/><?php echo strtoupper($test_result['result'])?></td>
				<?php else: ?>
					<td class="result test_other"/><?php echo strtoupper($test_result['result'])?></td>
				<?php endif; ?>
				<td style="text-align:left;"><?php
					$cmethod = $methodName = $test_result['name'];
					if (strpos($methodName, ' ')) {
						$cmethod = substr($methodName, 0, strpos($methodName, ' '));
					}
					$method = $classAnnotation->getMethod($cmethod);
					$editLink = $this->createEditLink($method->fileName, $method->startLine);
					echo "<a title=\"$method\" href=\"$editLink\">".$this->translateTestName($methodName)."</a>"
				?>
				<?php

					if ($test_result['message']) {
						$message = $this->visibleInvisible(htmlentities(trim($test_result['message'])));
						?>
						<pre class="pree"><?php echo $message; ?></pre>
						<?php
					}
				?>
				</td>
				<td>
					<?php echo $test_result['assertions']?>
				</td>
				<td>
					<?php echo round(1000*$test_result['time'],1)?>
				</td>
				<?php
					if (isset($test_result['output'])) {
						$output = $test_result['output'];
						$output = $this->visibleInvisible(htmlspecialchars($output));
					} else {
						$output = '';
					}
				?>
				<td><pre class="pree"><?php echo $output?></pre></td>
		  </tr>
		  <?php endforeach; ?>
		  </tbody>
		</table>

		<?php
		$table = ob_get_clean();

		$fileName = $classAnnotation->fileName;
		$editLink = $this->createEditLink($fileName);

		if ($results['Total'] !== $results['Pass'] + $results['NRY'] + $results['Skip']) {
			$errtxt   = $results['Fail'] === 0 ? 'ERRORS' : 'FAILED';
			$cssClass = "fail";
			$cssStyle = "color:red;font-weight:bold;";
			$display  = "block";
		} else {
			$errtxt   = "PASSED";
			$cssClass = "passed";
			$cssStyle = "color:green;font-weight:bold;";
			$display  = "none";
		}

		$detail = "{$results['Total']}<span style=\"color:green\"> / {$results['Pass']}<span> / <span style=\"color:red\">{$results['Fail']}</span>";
		$detail .= "<span style=\"color:black\">";
		if ($results['Skip'] > 0) {
			$detail .= " Skipped: {$results['Skip']}";
		}
		if ($results['NRY'] > 0) {
			$detail .= " / {$results['NRY']}";
		}
		$detail .= "</span>";

		$htmlClass = str_replace('\\', '-', $testClass);

		echo "<div class=\"{$cssClass}\">";

			//class links
			$this->renderClassHeader($classAnnotation);

			//result numbers
			echo "<span style=\"{$cssStyle}\">&nbsp&nbsp;&nbsp;$errtxt&nbsp;</span> {$detail} ";

			//collapsing
			echo "<a href=\"#\" onClick=\"javascript:return toggle('{$htmlClass}');\">&#x25ba;</a>";
		echo "</div>";
		echo "<div style=\"display:{$display}\"id=\"{$htmlClass}\">$table</div>";
	}

	private function visibleInvisible($string) {
		$string = str_replace(' ', '<span class="inv">&#765;</span>', $string);
		$string = str_replace("\n", "<span class=\"inv\">&#x2193;</span>\n", $string);
		$string = str_replace("\t", "<span class=\"inv\">&#x25a1;</span>", $string);
		return $string;
	}

	private function renderClassHeader(\ReflectionClass $class) {

		$fileName = $class->getFileName();
		$editLink = $this->createEditLink($fileName);
		$testClass = $class->getName();


		//edit icon
		echo "<a href='$editLink' title='$fileName - open in editor'>";
		echo '<img src="data:image/gif;base64,R0lGODlhCgAKAIABAG5ubv///yH5BAEAAAEALAAAAAAKAAoAAAIVhBFpganaHnQRtcXkqTBmp4HadiQFADs=" /></a>';
		echo "</a>&nbsp;&nbsp;";

		//namespaces clicks
		$parts = explode("\\", $testClass);
		$first = TRUE;
		$pathArr = array();
		foreach ($parts as $part) {
			$pathArr[] = $part;
			if ($first === FALSE) {
				echo "&nbsp; \ &nbsp;";
			} else {
				$first = FALSE;
			}
			$path = implode("\\", $pathArr);
			echo "<a class='classname' href='?filter={$path}'>{$part}</a>";
		}
	}

	private function renderHtmlHead() {
	?>
		<html>
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Tests <?php if($this->isUnderDebugger()) {echo " - DEBUGGER";}?></title>
		<style>
		body{font-family: arial; padding:0em; margin:0em;}

		body * {font-size:12px;}
		div {padding:0px;margin:0;}
		a {text-decoration:none;}
		h1 {font-size:14px;margin:3px, 0px, 3px, 0px;padding:0px;}
		h3 {color:#555}
		table {border-collapse:collapse; margin:10px;border-color:white;}
		#results {margin:0.5em;}
		.test_results td, .test_results th {border-color:#ccc;}
		.test_results td, .test_results th, .test_results td pre  {vertical-align: top; text-align: left;}
		.test_results td {text-align:center;padding:3px;}
		.test_results thead th {background:#abc; padding:2px; padding-left:5px;font-weight:bold; color:black; vertical-align:middle;}
		.test_results th {background:white;padding:3px;font-weight:normal;}
		td.test_fail {background:darkred;color:white}
		td.test_pass {background:green;color:white;}
		td.test_other {background:yellow}
		td.result {text-align:center;vertical-align: middle;font-weight: bold;}
		pre {font-family:consolas;font-size:11px;margin:0px;line-height:12px;}
		</style>
		<script language='Javascript'>
			function toggle(obj) {
				var el = document.getElementById(obj);
				if ( el.style.display != 'none' ) {
					el.style.display = 'none';
				}
				else {
					el.style.display = '';
				}
				return false;
			}
		</script>
		</head>
		<body>
	<?php
	}

	private function renderHtmlEnd() {
		echo "</body></html>";
	}

	private function renderEnvironmentInfo() {
		$ini = get_cfg_var('cfg_file_path');
		echo "<div style=\"margin-top:0px;font-size:70%;background:black;color:#ccc;padding-left:5px;padding-bottom:5px;\">";
		echo '<div onClick="location.reload(true)" style="cursor:hand;color:black;color:white;font-size:2.3em;font-weight:bold;padding-top:5px;padding-bottom:2pxpx;">TestBase::Runner</div>';
		echo "PHP Version: " . PHP_VERSION . " using ini file:$ini";
		echo "<span style=\"cursor:hand;font-size:0.9em;color:yellow;\" onClick=\"toggle('moreinfo-box');\"> more info</span>";
			echo "<div style=\"display:none;\" id=\"moreinfo-box\">";
			ob_start();
			include(__DIR__ . "/info.phtml");
			$s = ob_get_contents();
			ob_end_clean();
			echo $s;
			echo "</div>";
		echo "</div>";
		echo $this->isUnderDebugger() ? "<div style=\"padding:5 0 5 5;margin:0px;color:white;background:green;font-weight:bold;\">DEBUGGER ACTIVE</div>" : '';



	}

	/**
	 * Processes XML PhpUnit result into array
	 * @param string $xml
	 * @returns array
	 */
	private function processXML($xml) {
		$simple = new SimpleXMLElement($xml);
		$test_results = array();
		$test_results = $this->parseTestCases($simple->testsuite->testcase,  $test_results);
		foreach($simple->testsuite->testsuite as $suite) {
			$test_results = $this->parseTestCases($suite->testcase,  $test_results);
		}
		return $test_results;
	}

	private function parseTestCases($cases, $test_results) {

		foreach ($cases as $testcase) {
			 $result = array();
			 $result['name'] = (string) $testcase['name'];

			 if (isset($testcase->failure)) {
				  $result['result'] = 'Fail';
				  $result['message'] = (string)$testcase->failure;

			 } elseif (isset($testcase->error)) {
				if ((string) $testcase->error['type'] === 'PHPUnit_Framework_IncompleteTestError') {
					$result['result'] = 'NRY';
					$result['message'] = (string) $testcase->error;
				} elseif ((string) $testcase->error['type'] === 'PHPUnit_Framework_SkippedTestError') {
					$result['result'] = 'Skip';
					$result['message'] = (string) $testcase->error;
				} else {
					$result['result'] = 'Error';
					$result['message'] = (string) $testcase->error;
				}

			 } else {
					$result['result'] = 'Pass';
					$result['message'] = '';
			 }
			 $result['time'] = (string)$testcase['time'];
			 $result['assertions'] = (string)$testcase['assertions'];
			 $test_results[] = $result;
		}
		return $test_results;

	}

	private function runInConsole() {
		foreach ($this->getTestClasses() as $testClass) {
			$this->runSuiteInConsole($testClass);
		}
	}

	private function runSuiteInConsole($testClass) {
		if (is_array($testClass)) {
			foreach($testClass as $class) {
				$this->runSuiteInConsole($class);
			}
			return;
		}
		$suite = new PHPUnit_Framework_TestSuite();
		$suite->setBackupGlobals(FALSE);
		return PHPUnit_TextUI_TestRunner::run($suite);
	}

	private function isConsole() {
		return PHP_SAPI === 'cli';
	}

	private function getTestClasses() {
		$classes = array();
		foreach($this->suite->tests() as $test) {
			$classes[] = $test->getName();
		}
		return array('default' => $classes);
	}

	protected function isUnderDebugger() {
		if (isset($_GET['DBGSESSID'])) {
			if ($_GET['DBGSESSID'] != -1) {
				return TRUE;
			}
		}
		return FALSE;
	}

	public static function isRunnedByPHPUnit() {
		return defined('PHPUnit_MAIN_METHOD');
	}

	private static function isRunnedByDirectoryRun() {
		return defined('LocalRunTest_HANDLES_THIS');
	}

	private static function isRunnedByOthers() {
		return self::isRunnedByPHPUnit() || self::isRunnedByDirectoryRun();
	}

	protected function createEditLink($file, $line = 1) {
		return strtr(\Nette\Diagnostics\Debugger::$editor, array('%file' => urlencode(realpath($file)), '%line' => $line));
	}

	protected function translateTestName($name) {
		$name = substr($name, 4);
		$name = str_replace('__', '() ', $name);
		$name = preg_replace_callback(
			'/0x([\dA-Fa-f][\dA-Fa-f])/s',
			function ($matches) {
				return chr(hexdec($matches[1]));
			},
			$name
		);

		$name = preg_replace_callback(
			'/_([\d]+)\z/s',
			function ($matches) {
				return ' [' . $matches[1] . ']';
			},
			$name
		);
		$name = str_replace('_', ' ', $name);
		$name = trim($name);
		return $name;
	}

}
