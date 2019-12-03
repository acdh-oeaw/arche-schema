#!/usr/bin/php
<?php
if ($argc < 2 || !file_exists($argv[1]) || !file_exists($argv[2])) {
    echo "\nusage: $argv[0] config.yaml ontology.owl [skipBinary]\n\n";
    return;
}
$cfg = json_decode(json_encode(yaml_parse_file($argv[1])));
$t0  = time();

if (isset($cfg->composerLocation)) {
    require_once $cfg->composerLocation;
} else {
    require_once __DIR__ . '/vendor/autoload.php';
}

include __DIR__ . '/functionsRdbms.php';

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use acdhOeaw\acdhRepoLib\BinaryPayload;
use acdhOeaw\acdhRepoLib\Repo;
use acdhOeaw\acdhRepoLib\RepoResource;
use acdhOeaw\acdhRepoLib\SearchConfig;
use acdhOeaw\acdhRepoLib\SearchTerm;
use acdhOeaw\acdhRepoLib\exception\NotFound;
use zozlak\RdfConstants as RDF;

$repo = Repo::factory($argv[1]);

// some prefixes are not available in the easyrdf namespace...
RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
RdfNamespace::set('acdh', $cfg->schema->namespaces->ontology);

$ontology = new Graph();
$ontology->parseFile($argv[2]);

// restrictions go first as checkRestriction() can affect the whole graph
$collections = [
    $cfg->schema->namespaces->ontology . 'ontology',
    RDF::OWL_RESTRICTION,
    RDF::OWL_CLASS,
    RDF::OWL_OBJECT_PROPERTY,
    RDF::OWL_DATATYPE_PROPERTY,
];

try {
    $repo->begin();

    echo "### Creating top-level collections\n";
    foreach ($collections as $i => $id) {
        try {
            $res = $repo->getResourceById($id);
        } catch (NotFound $e) {
            $meta = (new Graph())->resource('.');
            $meta->addLiteral($cfg->schema->label, preg_replace('|^.*[/#]|', '', $id));
            $meta->addResource($cfg->schema->id, $id);
            $res  = $repo->createResource($meta);
        }
    }

    $imported = [];
    foreach ($collections as $type) {
        echo "### Importing $type\n";
        foreach ($ontology->allOfType($type) as $i) {
            if ($type !== RDF::OWL_RESTRICTION || checkRestriction($i, $cfg->schema)) {
                $tmp = saveOrUpdate($i, $repo, $type, $imported);
            }
        }
    }
    
    if (!isset($argv[3]) || $argv[3] !== 'skipBinary') {
        echo "###  Updating the owl binary\n";

        // collection storing all ontology binaries
        $collId = $cfg->schema->namespaces->id . 'acdh-schema';
        try {
            $coll = $repo->getResourceById($collId);
        } catch (NotFound $e) {
            $meta = (new Graph())->resource('.');
            $meta->addResource($cfg->schema->id, $collId);
            $meta->addLiteral($cfg->schema->label, 'ACDH ontology binaries');
            $coll = $repo->createResource($meta);
        }
        echo "    " . $coll->getUri() . "\n";

        $curId = preg_replace('/#$/', '', $cfg->schema->namespaces->ontology);
        $old   = null;

        $newMeta = (new Graph())->resource('.');
        $newMeta->addResource($cfg->schema->id, $curId . '/' . date('Y-m-d_h:m:s'));
        $newMeta->addLiteral($cfg->schema->label, 'ACDH schema owl file');
        $newMeta->addResource($cfg->schema->parent, $coll->getUri());
        $newMeta->addResource($cfg->schema->acdh->accessRestriction, 'https://vocabs.acdh.oeaw.ac.at/archeaccessrestrictions/public');

        $binary = new BinaryPayload(null, $argv[2], 'application/rdf+xml');
        try {
            $old = $repo->getResourceById($curId);

            $hash = (string) $old->getGraph()->getLiteral($cfg->schema->hash);
            if (!preg_match('/^(md5|sha1):/', $hash)) {
                throw new Exception("fixity hash $hash not implemented - update the script");
            }
            $md5  = 'md5:' . md5_file($argv[2]);
            $sha1 = 'sha1:' . sha1_file($argv[2]);
            if (!in_array($hash, [$md5, $sha1])) {
                echo "    uploading a new version\n";
                $new     = $repo->createResource($newMeta, $binary);
                echo "      " . $new->getUri() . "\n";
                $oldMeta = $old->getGraph();
                $oldMeta->deleteResource($cfg->schema->id, $curId);
                $oldMeta->addResource($cfg->schema->acdh->previous, $new->getUri());
                $old->setGraph($oldMeta);
                $old->updateMetadata(RepoResource::UPDATE_OVERWRITE); // we must loose the old identifier

                $newMeta->addResource($cfg->schema->id, $curId);
                $new->setMetadata($newMeta);
                $new->updateMetadata();
            } else {
                echo "    owl binary up to date\n";
            }
        } catch (NotFound $e) {
            echo "    no owl binary - creating\n";
            $newMeta->addResource($cfg->schema->id, $curId);
            $new = $repo->createResource($newMeta, $binary);
            echo "      " . $new->getUri() . "\n";
        }
    }

    echo "### Removing obsolete resources...\n";
    array_shift($collections);
    foreach ($collections as $id) {
        $searchTerm              = new SearchTerm($cfg->schema->parent, $id, '=', SearchTerm::TYPE_RELATION);
        $searchCfg               = new SearchConfig();
        $searchCfg->metadataMode = RepoResource::META_RESOURCE;
        $children                = $repo->getResourcesBySearchTerms([$searchTerm], $searchCfg);
        foreach ($children as $res) {
            if (!in_array($res->getUri(), $imported)) {
                echo "    " . $res->getUri() . "\n";
                $res->delete(true);
            }
        }
    }

    $repo->commit();
    echo "\nFinished in " . (time() - $t0) . "s\n";
} finally {
    if ($repo->inTransaction()) {
        $repo->rollback();
    }
}
