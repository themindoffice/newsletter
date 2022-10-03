<?php

$dir = 'modules/Addons/Newsletter';

echo 'Creating folder...' . PHP_EOL;

if (!is_dir($dir)) {
    mkdir($dir);
}
