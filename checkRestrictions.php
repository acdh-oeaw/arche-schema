#!/usr/bin/php

<?php

if (file_exists('/var/www/html/vendor/autoload.php')) {
    include '/var/www/html/vendor/autoload.php';
    $cfgFile = '/var/www/html/config.ini';
} else {
    include __DIR__ . '/vendor/autoload.php';
    $cfgFile = __DIR__ . '/config.ini';
}
include __DIR__ . '/functions.php';

use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;

if ($argc < 2 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] ONTOLOGY.owl\n\n";
    return;
}

RC::init($cfgFile);

# some of the prefixes are not available in the easyrdf namespace...
RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
RdfNamespace::set('acdh', RC::vocabsNmsp());

$ontology = new Graph();
$ontology->parseFile($argv[1]);
foreach ($ontology->allOfType('http://www.w3.org/2002/07/owl#Restriction') as $i) {
    $res = checkRestriction($i);
    if ($res === true) {
        echo $i->getUri() . " - OK\n";
    } else if ($res === false) {
        echo ' ' . $i->dump('text');
    }
}
