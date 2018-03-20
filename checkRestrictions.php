#!/usr/bin/php

<?php

include '/var/www/html/vendor/autoload.php';
include __DIR__ . '/functions.php';

use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;

if ($argc < 2 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] ONTOLOGY.owl\n\n";
    return;
}

RC::init('/var/www/html/config.ini');

# some of the prefixes are not available in the easyrdf namespace...
RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
RdfNamespace::set('acdh', RC::vocabsNmsp());

$ontology = new Graph();
$ontology->parseFile($argv[1]);
foreach ($ontology->allOfType('http://www.w3.org/2002/07/owl#Restriction') as $i) {
    if (checkRestriction($i)) {
        echo $i->getUri() . " - OK\n";
    } else {
        echo ' ' . $i->dump('text');
    }
}

