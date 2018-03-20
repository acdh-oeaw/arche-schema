<?php

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\exceptions\NotFound;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;
use EasyRdf\RdfNamespace;

/**
 * helper functions for importOntology.php and checkRestrictions.php
 */

/**
 * Imports or updates (if it already exists) a given owl object 
 * (class/dataProperty/objectProperty/restriction) into the repository
 * @param \EasyRdf\Resource $res an owl object to be imported/updated
 * @param \acdhOeaw\fedora\Fedora $fedora repository connection object
 * @param string $path collection in which a repository repository resource should be created
 * @param array $imported an array of created/updated resources (will be extended with a
 *   resource corresponding to the $res upon a successful creation/update)
 */
function saveOrUpdate(Resource $res, Fedora $fedora, string $path, array &$imported) {
    static $ids = array();
    
    $ontology = $res->getGraph();

    if ($res->isA('http://www.w3.org/2002/07/owl#Restriction')) {
        // restrictions in owl are anonymous, we need to create ids for them automatically
        $id = generateRestrictionId($res);
    } else {
        $id = RdfNamespace::expand($res->getUri());
        if (preg_match('/^_:genid[0-9]+$/', $id)) {
            echo "Skipping an anonymous resource \n" . $res->dump('text');
            return;
        }
    }

    if (in_array($id, $ids)) {
        echo "Skipping a duplicated resource \n" . $res->dump('text');
        return;
    }
    $ids[] = $id;

    $graph = new Graph();
    $meta = $graph->resource('.');

    foreach ($res->properties() as $p) {
        foreach ($res->allLiterals($p) as $v) {
            if ($v->getValue() !== '') {
                $meta->addLiteral($p, $v->getValue(), $v->getLang());
            }
        }

        foreach ($res->allResources($p) as $v) {
            if ($v->isBNode()) {
                continue;
            }
            $meta->addResource($p, $v);
        }
    }

    $meta->addResource(RC::idProp(), $id);

    if (!$meta->hasProperty(RC::get('doorkeeperOntologyLabelProp'))) {
        $meta->addLiteral(RC::get('doorkeeperOntologyLabelProp'), preg_replace('|^.*[/#]|', '', $id));
    }

    try {
        $fedoraRes = $fedora->getResourceById($id);
        echo "updating " . $id;
        $fedoraRes->setMetadata($meta);
        $fedoraRes->updateMetadata();
    } catch (NotFound $e) {
        echo "creating " . $id;
        $fedoraRes = $fedora->createResource($meta, '', $path, 'POST');
    }

    echo ' as ' . $fedoraRes->getUri(true) . "\n";
    $imported[] = $fedoraRes->getUri(true);
}

/**
 * Checks if $what inherits from $from
 */
function doesInherit(Resource $what, Resource $from): bool {
    if ($what === $from) {
        return true;
    }
    $flag = false;
    foreach ($from->getGraph()->resourcesMatching('http://www.w3.org/2000/01/rdf-schema#subClassOf', $from) as $i) {
        $flag |= doesInherit($what, $i);
    }
    return $flag;
}

/**
 * Checks if a given restriction is consistent with the rest of the ontology
 */
function checkRestriction(Resource $r): bool {
    // there must be at least one class connected with the restriction 
    // (which in owl terms means there must be at least one class inheriting from the restriction)
    $children = $r->getGraph()->resourcesMatching('http://www.w3.org/2000/01/rdf-schema#subClassOf', $r);
    if (count($children) === 0) {
        echo $r->getUri() . " - no classes inherit from the restriction\n";
        return false;
    }
    
    // property for which the restriction is defined must exist and have both domain and range defined
    $prop = $r->getResource('http://www.w3.org/2002/07/owl#onProperty');
    if ($prop === null) {
        echo $r->getUri() . " - it lacks owl:onProperty\n";
    }
    $propDomain = $prop->getResource('http://www.w3.org/2000/01/rdf-schema#domain');
    if ($propDomain === null) {
        echo $r->getUri() . " - property " . $prop->getUri() . " has no rdfs:domain\n";
        return false;
    }
    $propRange = $prop->getResource('http://www.w3.org/2000/01/rdf-schema#range');
    if ($propRange === null) {
        echo $r->getUri() . " - property " . $prop->getUri() . " has no rdfs:range\n";
        return false;
    }
    
    // classes inheriting from the restriction must match or inherit from restriction's property domain
    foreach ($children as $i) {
        if (!doesInherit($i, $propDomain)) {
            echo $r->getUri() . " - owl:onClass (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:domain (" . $propDomain->getUri() . ")\n";
            return false;
        }
    }
    
    // target classes of qualified restrictions must match or inherit from restriction's property range
    $rangeMatch = 0;
    $onClass = $r->allResources('http://www.w3.org/2002/07/owl#onClass');
    foreach ($onClass as $i) {
        if (!doesInherit($i, $propRange)) {
            echo $r->getUri() . " - owl:onClass (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:range (" . $propRange->getUri() . ")\n";
            return false;
        }
        $rangeMatch += $i === $propRange;
    }
    $onDataRange = $r->allResources('http://www.w3.org/2002/07/owl#onDataRange');
    foreach ($onDataRange as $i) {
        if (!doesInherit($i, $propRange)) {
            echo $r->getUri() . " - owl:onDataRange (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:range (" . $propRange->getUri() . ")\n";
            return false;
        }
        $rangeMatch += $i === $propRange;
    }
    
    // simplify qualified restrictions which qualified rules don't differ from restriction's property range
    if (count($onClass) + count($onDataRange) === $rangeMatch && $rangeMatch > 0) {
        echo "simplifying " . $r->getUri() . "\n";
        $r->deleteResource('http://www.w3.org/2002/07/owl#onClass');
        $r->deleteResource('http://www.w3.org/2002/07/owl#onDataRange');
        foreach (['q', 'minQ', 'maxQ'] as $i) {
            $srcProp = 'http://www.w3.org/2002/07/owl#' . $i . 'ualifiedCardinality';
            $targetProp = str_replace('qualifiedC', 'c', str_replace('Qualified', '', $srcProp));
            foreach ($r->allLiterals($srcProp) as $j) {
                $r->addLiteral($targetProp, $j->getValue());
            }
            $r->delete($srcProp);
        }
    }
    
    return true;
}

function generateRestrictionId(Resource $res): string {
    $idProps = [];
    foreach ($res->allResources('http://www.w3.org/2002/07/owl#onProperty') as $i) {
        $idProps[] = $i->getUri();
    }
    foreach ($res->allResources('http://www.w3.org/2002/07/owl#onDataRange') as $i) {
        $idProps[] = $i->getUri();
    }
    foreach ($res->allResources('http://www.w3.org/2002/07/owl#onClass') as $i) {
        $idProps[] = $i->getUri();
    }
    $idProps = array_unique($idProps);
    sort($idProps);
    return RC::vocabsNmsp() . 'restriction-' . md5(implode(',', $idProps));
}
