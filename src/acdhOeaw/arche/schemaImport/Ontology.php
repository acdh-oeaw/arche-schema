<?php

/*
 * The MIT License
 *
 * Copyright 2020 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\schemaImport;

use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\Resource;
use EasyRdf\RdfNamespace;
use GuzzleHttp\Exception\RequestException;
use acdhOeaw\acdhRepoLib\BinaryPayload;
use acdhOeaw\acdhRepoLib\Repo;
use acdhOeaw\acdhRepoLib\RepoResource;
use acdhOeaw\acdhRepoLib\SearchConfig;
use acdhOeaw\acdhRepoLib\SearchTerm;
use acdhOeaw\acdhRepoLib\exception\NotFound;
use zozlak\RdfConstants as RDF;

/**
 * Description of Ontology
 *
 * @author zozlak
 */
class Ontology {

    /**
     *
     * @var \EasyRdf\Graph
     */
    private $ontology;

    /**
     *
     * @var \object
     */
    private $schema;

    public function __construct(object $schema) {
        $this->schema = $schema;
    }

    public function loadFile(string $filename): void {
        $this->ontology = new Graph();
        $this->ontology->parseFile($filename);
    }

    public function loadRepo(Repo $repo): void {
        $this->ontology = new Graph();        
        $collections             = [
            RDF::OWL_RESTRICTION,
            RDF::OWL_CLASS,
            RDF::OWL_OBJECT_PROPERTY,
            RDF::OWL_DATATYPE_PROPERTY,
        ];
        $searchTerm              = new SearchTerm($this->schema->parent, '', '=', SearchTerm::TYPE_RELATION);
        $searchCfg               = new SearchConfig();
        $searchCfg->metadataMode = RepoResource::META_RESOURCE;
        foreach ($collections as $i) {
            $searchTerm->value = $i;
            $children          = $repo->getResourcesBySearchTerms([$searchTerm], $searchCfg);
            foreach($children as $j) {
                /* @var $j RepoResource */
                $j->getGraph()->copy([], '/^$/', '', $this->ontology);
            }
        }
    }

    public function import(Repo $repo, bool $verbose = false): void {
        // restrictions go first as checkRestriction() can affect the whole graph
        $collections = [
            $this->schema->namespaces->ontology . 'ontology',
            RDF::OWL_RESTRICTION,
            RDF::OWL_CLASS,
            RDF::OWL_OBJECT_PROPERTY,
            RDF::OWL_DATATYPE_PROPERTY,
        ];

        echo $verbose ? "### Creating top-level collections\n" : '';
        foreach ($collections as $i => $id) {
            $this->createCollection($repo, $id);
        }

        $imported = [];
        foreach ($collections as $type) {
            echo $verbose ? "### Importing $type\n" : '';
            foreach ($this->ontology->allOfType($type) as $i) {
                $import = true;
                if ($type === RDF::OWL_RESTRICTION) {
                    $restriction = new Restriction($i, $this->schema);
                    $import      = $restriction->check($verbose);
                }
                if ($import) {
                    $this->saveOrUpdate($repo, $i, $type, $imported, $verbose);
                }
            }
        }

        echo $verbose ? "### Removing obsolete resources...\n" : '';
        array_shift($collections);
        foreach ($collections as $id) {
            Util::removeObsoleteChildren($repo, $id, $this->schema->parent, $imported, $verbose);
        }
    }

    public function importOwlFile(Repo $repo, string $owlPath, bool $verbose): void {
        $s = $this->schema;

        echo $verbose ? "###  Updating the owl binary\n" : '';

        // collection storing all ontology binaries
        $collId = $s->namespaces->id . 'acdh-schema';
        try {
            $coll = $repo->getResourceById($collId);
        } catch (NotFound $e) {
            $meta = (new Graph())->resource('.');
            $meta->addResource($s->id, $collId);
            $meta->addLiteral($s->label, new Literal('ACDH ontology binaries', 'en'));
            $coll = $repo->createResource($meta);
        }
        echo $verbose ? "    " . $coll->getUri() . "\n" : '';

        $curId = preg_replace('/#$/', '', $this->schema->namespaces->ontology);
        $old   = null;

        $newMeta = (new Graph())->resource('.');
        $newMeta->addResource($s->id, $curId . '/' . date('Y-m-d_H:m:s'));
        $newMeta->addLiteral($s->label, new Literal('ACDH schema owl file', 'en'));
        $newMeta->addResource($s->parent, $coll->getUri());
        $newMeta->addResource($s->accessRestriction, 'https://vocabs.acdh.oeaw.ac.at/archeaccessrestrictions/public');

        $binary = new BinaryPayload(null, $owlPath, 'application/rdf+xml');
        try {
            $old = $repo->getResourceById($curId);

            $hash = (string) $old->getGraph()->getLiteral($s->hash);
            if (!preg_match('/^(md5|sha1):/', $hash)) {
                throw new Exception("fixity hash $hash not implemented - update the script");
            }
            $md5  = 'md5:' . md5_file($owlPath);
            $sha1 = 'sha1:' . sha1_file($owlPath);
            if (!in_array($hash, [$md5, $sha1])) {
                echo $verbose ? "    uploading a new version\n" : '';
                $new     = $repo->createResource($newMeta, $binary);
                echo $verbose ? "      " . $new->getUri() . "\n" : '';
                $oldMeta = $old->getGraph();
                $oldMeta->deleteResource($s->id, $curId);
                $oldMeta->addResource($s->versioning->isPrevOf, $new->getUri());
                $old->setGraph($oldMeta);
                $old->updateMetadata(RepoResource::UPDATE_OVERWRITE); // we must loose the old identifier

                $newMeta->addResource($s->id, $curId);
                $new->setMetadata($newMeta);
                $new->updateMetadata();
            } else {
                echo $verbose ? "    owl binary up to date\n" : '';
            }
        } catch (NotFound $e) {
            echo $verbose ? "    no owl binary - creating\n" : '';
            $newMeta->addResource($s->id, $curId);
            $new = $repo->createResource($newMeta, $binary);
            echo $verbose ? "      " . $new->getUri() . "\n" : '';
        }
    }

    public function importVocabularies(Repo $repo, bool $verbose): void {
        echo $verbose ? "###  Importing external vocabularies\n" : '';

        $vocabsProp = $this->schema->ontology->vocabs;
        foreach ($this->ontology->resourcesMatching($vocabsProp) as $res) {
            foreach ($res->all($vocabsProp) as $vocabularyUrl) {
                $vocabularyUrl = (string) $vocabularyUrl;
                echo $verbose ? "$vocabularyUrl\n" : '';
                $vocabulary    = new Vocabulary($this->schema);
                $vocabulary->loadUrl($vocabularyUrl);
                try {
                    $vocabulary->update($repo, $verbose);
                } catch (RequestException $e) {
                    echo $verbose ? "    fetch error" . $e->getMessage() . "\n" : '';
                }
            }
        }
    }

    private function createCollection(Repo $repo, $id): void {
        try {
            $res = $repo->getResourceById($id);
        } catch (NotFound $e) {
            $meta = (new Graph())->resource('.');
            $meta->addLiteral($this->schema->label, new Literal(preg_replace('|^.*[/#]|', '', $id), 'en'));
            $meta->addResource($this->schema->id, $id);
            $res  = $repo->createResource($meta);
        }
    }

    /**
     * Imports or updates (if it already exists) a given owl object 
     * (class/dataProperty/objectProperty/restriction) into the repository
     * @param \acdhOeaw\acdhRepoLib\Repo $repo repository connection object
     * @param \EasyRdf\Resource $res an owl object to be imported/updated
     * @param string $parentId collection in which a repository repository resource should be created
     * @param array $imported an array of created/updated resources (will be extended with a
     *   resource corresponding to the $res upon a successful creation/update)
     * @param bool $verbose
     */
    function saveOrUpdate(Repo $repo, Resource $res, string $parentId,
                          array &$imported, bool $verbose) {
        static $ids = [];
        $schema     = $repo->getSchema();

        if ($res->isA(RDF::OWL_RESTRICTION)) {
            // restrictions in owl are anonymous, we need to create ids for them automatically
            $id = (new Restriction($res, $schema))->generateId();
        } else {
            $id = RdfNamespace::expand($res->getUri());
            if (preg_match('/^_:genid[0-9]+$/', $id)) {
                echo $verbose ? "Skipping an anonymous resource \n" . $res->dump('text') : '';
                return;
            }
        }

        if (in_array($id, $ids)) {
            echo $verbose ? "Skipping a duplicated resource \n" . $res->dump('text') : '';
            return;
        }
        $ids[] = $id;

        $meta = (new Graph())->resource('.');

        foreach ($res->properties() as $p) {
            foreach ($res->allLiterals($p) as $v) {
                if ($v->getValue() !== '') {
                    $meta->addLiteral($p, $v->getValue(), $v->getLang() ?? 'en');
                }
            }
            foreach ($res->allResources($p) as $v) {
                if ($v->isBNode()) {
                    continue;
                }
                $meta->addResource($p, $v);
            }
        }

        $meta->addResource($schema->id, $id);
        $meta->addResource($schema->parent, $parentId);

        if (null === $meta->getLiteral($schema->label)) {
            $meta->addLiteral($schema->label, preg_replace('|^.*[/#]|', '', $id), 'en');
        }

        $repoRes    = Util::updateOrCreate($repo, $id, $meta, $verbose);
        $imported[] = $repoRes->getUri();
    }

}
