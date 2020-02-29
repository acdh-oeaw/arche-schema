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
use acdhOeaw\fedora\acl\WebAclRule as WAR;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\exceptions\NotFound;
use acdhOeaw\schema\file\File;
use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\Resource;
use EasyRdf\RdfNamespace;

if ($argc < 2 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] ONTOLOGY.owl [skipBinary]\n\n";
    return;
}

RC::init($cfgFile);

$fedora = new Fedora();

# some of the prefixes are not available in the easyrdf namespace...
RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
RdfNamespace::set('acdh', RC::vocabsNmsp());

###################
# parse owl
###################

$ontology = new Graph();
$ontology->parseFile($argv[1]);

try {
    $fedora->begin();
    # Create collections for classes and properties
    $collections = array(
        'ontology' => RC::vocabsNmsp() . 'ontology',
        'ontology/class' => 'http://www.w3.org/2002/07/owl#class',
        'ontology/objectProperty' => 'http://www.w3.org/2002/07/owl#objectProperty',
        'ontology/datatypeProperty' => 'http://www.w3.org/2002/07/owl#datatypeProperty',
        'ontology/restriction' => 'http://www.w3.org/2002/07/owl#Restriction',
    );

    foreach ($collections as $i => $id) {
        try {
            $res = $fedora->getResourceByUri($i);
        } catch (NotFound $e) {
            $graph = new Graph();
            $meta = $graph->resource('.');
            $meta->addLiteral(RC::get('doorkeeperOntologyLabelProp'), new Literal($i, 'en'));
            $meta->addResource(RC::idProp(), $id);
            $res = $fedora->createResource($meta, '', $i, 'PUT');
        }
    }

    # Create resources
    $imported = [];

    // restrictions go first as checkRestriction() can affect the whole graph
    foreach ($ontology->allOfType('http://www.w3.org/2002/07/owl#Restriction') as $i) {
        if (checkRestriction($i)) {
            $tmp = saveOrUpdate($i, $fedora, 'ontology/restriction/', $imported);
        }
    }

    foreach ($ontology->allOfType('http://www.w3.org/2002/07/owl#Class') as $i) {
        $tmp = saveOrUpdate($i, $fedora, 'ontology/class/', $imported);
    }

    foreach ($ontology->allOfType('http://www.w3.org/2002/07/owl#ObjectProperty') as $i) {
        saveOrUpdate($i, $fedora, 'ontology/objectProperty/', $imported);
    }

    foreach ($ontology->allOfType('http://www.w3.org/2002/07/owl#DatatypeProperty') as $i) {
        saveOrUpdate($i, $fedora, 'ontology/datatypeProperty/', $imported);
    }
    
    if (!isset($argv[2]) || $argv[2] !== 'skipBinary') {
        # Import ontology as a binary
        echo "\nUpdating the owl binary...\n";
    
        // collection storing all ontology binaries
        $collId = 'https://id.acdh.oeaw.ac.at/acdh-schema';
        try {
             $coll = $fedora->getResourceById($collId);
        } catch (NotFound $e) {
            $meta = (new Graph())->resource('.');
            $meta->addResource(RC::idProp(), $collId);
            $meta->addLiteral(RC::titleProp(), new Literal('ACDH ontology binaries', 'en'));
            $coll = $fedora->createResource($meta);
        }
        echo "    " . $coll->getUri(true) . "\n";
    
        $curId = 'https://vocabs.acdh.oeaw.ac.at/schema';
        $old = null;

        $newMeta = (new Graph())->resource('.');
        $newMeta->addResource(RC::idProp(), $curId . '/' . date('Y-m-d_h:m:s'));
        $newMeta->addLiteral(RC::titleProp(), new Literal('ACDH schema owl file', 'en'));
        $newMeta->addResource(RC::relProp(), $coll->getId());
    
        // ontology binary itself
        try {
            $old = $fedora->getResourceById($curId);
            $fixity = explode(':', $old->getFixity());
            if ($fixity[1] !== 'sha1') {
                throw new Exception('fixity hash not implemented - update the script');
            }
            if (sha1_file($argv[1]) !== $fixity[2]) {
                echo "    uploading a new version\n";            
                $new = $fedora->createResource($newMeta, $argv[1], $coll->getUri());
            
                $oldMeta = $old->getMetadata();
                $oldMeta->delete(RC::idProp(), new Resource($curId));
                $oldMeta->addResource(RC::get('fedoraPrevProp'), $new->getId());
                $oldMeta->delete(RC::titleProp());
                $oldMeta->addLiteral(RC::titleProp(), new Literal('ACDH ontology binaries', 'en'));
                $old->setMetadata($oldMeta);
                $old->updateMetadata();
            } else {
               echo "    owl binary up to date\n";
            }
        } catch (NotFound $e) {
            echo "    no owl binary - creating\n";
            $new = $fedora->createResource($newMeta, $argv[1], $coll->getUri());
        }
    }

    $fedora->commit();
    if (isset($new)) {
        // passing same id between resources is possible only by removing it from one, 
        // commiting transaction and then setting it on other resource in a new transaction
        $fedora->begin();
        $new->getAcl()->createAcl()->grant(WAR::USER, WAR::PUBLIC_USER, WAR::READ);
        $newMeta = $new->getMetadata();
        $newMeta->addResource(RC::idProp(), $curId);
        $new->setMetadata($newMeta);
        $new->updateMetadata();
        $fedora->commit();
    }

    // remove obsolete resources
    echo "removing obsolete resources...\n";
    $fedora->begin();
    array_shift($collections);
    foreach ($collections as $uri => $id) {
        $col = $fedora->getResourceByUri($uri);
        foreach ($col->getFedoraChildren() as $res) {
            if (!in_array($res->getUri(true), $imported)) {
                echo "    " . $res->getUri(true) . "\n";
                $res->delete(true);
            }
        }
    }
    $fedora->commit();

    // grant read rights for public
    echo "granting read rights...\n";
    $fedora->begin();
    $fedora->getResourceByUri('/ontology')->getAcl()->createAcl()->grant(WAR::USER, WAR::PUBLIC_USER, WAR::READ);
    $fedora->commit();
} catch (Exception $e) {
    $fedora->rollback();
    throw $e;
}


