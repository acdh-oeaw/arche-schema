<?php

if (file_exists('/var/www/html/vendor/autoload.php')) {
    include '/var/www/html/vendor/autoload.php';
} else {
    include __DIR__ . '/vendor/autoload.php';
}

if ($argc < 2 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] ONTOLOGY.owl\n\n";
    return;
}

$g = new EasyRdf\Graph();
$g->parseFile($argv[1]);
echo $g->serialise('ntriples');
