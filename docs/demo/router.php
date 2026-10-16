<?php

$file = __DIR__.'/public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file)) {
    return false;
}

require __DIR__.'/public/index.php';
