<?php
require_once('App.php');
require_once('phpQuery.php');
require_once('config.php');

$files = glob('apps/*.json');
foreach($files as $app) {
    $item = new App($app);
    $item->validate();
}