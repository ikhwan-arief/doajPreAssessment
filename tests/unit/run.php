<?php
/**
 * Dependency-free unit test runner (ADR-009 R1.1). Runs every tests/unit/*Test.php file.
 * Usage: php tests/unit/run.php   (exit code 0 only if every test passed)
 */
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) { throw new ErrorException($str, 0, $no, $file, $line); });

$GLOBALS['doajPaTests'] = array();
function doajpa_test($name, $fn) { $GLOBALS['doajPaTests'][] = array($name, $fn); }
function doajpa_assert($condition, $message) { if ($condition !== true) throw new Exception('Assertion failed: ' . $message); }
function doajpa_same($expected, $actual, $message) {
	if ($expected !== $actual) throw new Exception('Assertion failed: ' . $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

foreach (glob(__DIR__ . '/*Test.php') as $file) require $file;

$pass = 0; $fail = 0;
echo 'PHP ' . PHP_VERSION . "\n";
foreach ($GLOBALS['doajPaTests'] as $test) {
	list($name, $fn) = $test;
	try { $fn(); $pass++; echo "PASS $name\n"; }
	catch (Throwable $e) { $fail++; echo "FAIL $name: " . $e->getMessage() . "\n"; }
}
echo "tests=" . ($pass + $fail) . " pass=$pass fail=$fail\n";
exit($fail === 0 && $pass > 0 ? 0 : 1);
