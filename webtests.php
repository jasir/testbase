<?php

//require bootstrap for test
require_once __DIR__ . '/bootstrap.php';

$runner = new \TestBase\Runner();

$runner->addDirectory(__DIR__ . '/../tests/');
$runner->run();
