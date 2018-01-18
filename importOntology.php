#!/usr/bin/php

<?php

include '/var/www/html/vendor/autoload.php';

use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\acl\WebAclRule as WAR;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\exceptions\NotFound;
use acdhOeaw\schema\file\File;
use EasyRdf\Graph;
use EasyRdf\Resource;
use EasyRdf\RdfNamespace;

if ($argc < 2 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] ONTOLOGY.owl\n\n";
    return;
}

RC::init('/var/www/html/config.ini');

$fedora = new Fedora();

# some of the prefixes are not available in the easyrdf namespace...
RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
RdfNamespace::set('acdh', RC::vocabsNmsp());

###################
# helper functions
###################

function saveOrUpdate($res, $fedora, $path, $ontology) {
    static $ids = array();

    $id = RdfNamespace::expand($res->getUri());

    if (in_array($id, $ids)) {
        throw new Exception('Duplicated entity URI: ' . $id);
    }
    $ids[] = $id;

    $graph = new Graph();
    $meta = $graph->resource('.');

    foreach ($res->properties() as $p) {
        foreach ($res->allLiterals($p) as $v) {
            $meta->addLiteral($p, $v->getValue());
        }

        foreach ($res->allResources($p) as $v) {
            if ($v->isBNode()) {
                continue;
            }
            $meta->addResource($p, $v);
        }
    }

    $restrictions = $ontology->resourcesMatching('http://www.w3.org/2002/07/owl#onProperty', $res);
    foreach ($restrictions as $r) {
        $restrictionProps = array(
            'http://www.w3.org/2002/07/owl#minCardinality',
            'http://www.w3.org/2002/07/owl#maxCardinality',
            'http://www.w3.org/2002/07/owl#cardinality'
        );
        foreach($restrictionProps as $p) {
            $v = $r->getLiteral($p);
            if ($v) {
                $meta->addLiteral($p, $v);
            }
        }
    }

    $meta->addResource(RC::idProp(), $id);

    if (!$meta->hasProperty(RC::get('doorkeeperOntologyLabelProp'))) {
        $meta->addLiteral(RC::get('doorkeeperOntologyLabelProp'), preg_replace('|^.*[/#]|', '', $id));
    }

    try {
        $fedoraRes = $fedora->getResourceById($id);
        echo "updating " . $id . " as ";
        $fedoraRes->setMetadata($meta);
        $fedoraRes->updateMetadata();
    } catch (NotFound $e) {
        echo "creating " . $id;
        $fedoraRes = $fedora->createResource($meta, '', $path, 'POST');
    }

    echo ' as ' . preg_replace('|tx:[^/]+|', '', $fedoraRes->getUri()) . "\n";
}

###################
# parse owl
###################

$ontology = new Graph();
$ontology->parseFile($argv[1]);
$restr = array();

try {
    $fedora->begin();
    # Create collections for classes and properties
    $collections = array(
        'ontology' => RC::vocabsNmsp() . 'ontology',
        'ontology/class' => 'http://www.w3.org/2002/07/owl#class',
        'ontology/objectProperty' => 'http://www.w3.org/2002/07/owl#objectProperty',
        'ontology/datatypeProperty' => 'http://www.w3.org/2002/07/owl#datatypeProperty'
    );

    foreach ($collections as $i => $id) {
        try {
            $res = $fedora->getResourceByUri($i);
        } catch (NotFound $e) {
            $graph = new Graph();
            $meta = $graph->resource('.');
            $meta->addLiteral(RC::get('doorkeeperOntologyLabelProp'), $i);
            $meta->addResource(RC::idProp(), $id);
            $res = $fedora->createResource($meta, '', $i, 'PUT');
        }
    }

    # Create resources
    $t = 'http://www.w3.org/2002/07/owl#Class';
    foreach ($ontology->allOfType($t) as $i) {
        saveOrUpdate($i, $fedora, 'ontology/class/', $ontology);
    }

    $t = 'http://www.w3.org/2002/07/owl#ObjectProperty';
    foreach ($ontology->allOfType($t) as $i) {
        saveOrUpdate($i, $fedora, 'ontology/objectProperty/', $ontology);
    }

    $t = 'http://www.w3.org/2002/07/owl#DatatypeProperty';
    foreach ($ontology->allOfType($t) as $i) {
        saveOrUpdate($i, $fedora, 'ontology/datatypeProperty/', $ontology);
    }

    # Import ontology as a binary
    echo "\nUpdating the owl binary...\n";
    
    // collection storing all ontology binaries
    $collId = 'https://id.acdh.oeaw.ac.at/acdh-schema';
    try {
        $coll = $fedora->getResourceById($collId);
    } catch (NotFound $e) {
        $meta = (new Graph())->resource('.');
        $meta->addResource(RC::idProp(), $collId);
        $meta->addLiteral(RC::titleProp(), 'ACDH ontology binaries');
        $coll = $fedora->createResource($meta);
    }
    echo "    " . $coll->getUri(true) . "\n";
    
    $curId = 'https://vocabs.acdh.oeaw.ac.at/schema';
    $old = null;

    $newMeta = (new Graph())->resource('.');
    $newMeta->addResource(RC::idProp(), $curId . '/' . date('Y-m-d_h:m:s'));
    $newMeta->addLiteral(RC::titleProp(), 'ACDH schema owl file');
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
            $old->setMetadata($oldMeta);
            $old->updateMetadata();
        } else {
            echo "    owl binary up to date\n";
        }
    } catch (NotFound $e) {
        echo "    no owl binary - creating\n";
        $new = $fedora->createResource($newMeta, $argv[1], $coll->getUri());
    }

    $fedora->commit();
    if (isset($new)) {
        // passing same id between resources is possible only by removing it from one, 
        // commiting transaction and then setting it on other resource in a new transaction
        $fedora->begin();
        $newMeta = $new->getMetadata();
        $newMeta->addResource(RC::idProp(), $curId);
        $new->setMetadata($newMeta);
        $new->updateMetadata();
        $new->getAcl()->createAcl()->grant(WAR::USER, WAR::PUBLIC_USER, WAR::READ);
        $fedora->commit();
    }
} catch (Exception $e) {
    $fedora->rollback();
    throw $e;
}
