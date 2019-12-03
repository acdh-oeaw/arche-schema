<?php

use acdhOeaw\acdhRepoLib\Repo;
use acdhOeaw\acdhRepoLib\exception\NotFound;
use EasyRdf\Graph;
use EasyRdf\Resource;
use EasyRdf\RdfNamespace;
use zozlak\RdfConstants as RDF;

/**
 * helper functions for importOntology.php and checkRestrictions.php
 */

/**
 * Imports or updates (if it already exists) a given owl object 
 * (class/dataProperty/objectProperty/restriction) into the repository
 * @param \EasyRdf\Resource $res an owl object to be imported/updated
 * @param \acdhOeaw\acdhRepoLib\Repo $repo repository connection object
 * @param string $parentId collection in which a repository repository resource should be created
 * @param array $imported an array of created/updated resources (will be extended with a
 *   resource corresponding to the $res upon a successful creation/update)
 */
function saveOrUpdate(Resource $res, Repo $repo, string $parentId,
                      array &$imported) {
    static $ids = [];
    $schema     = $repo->getSchema();

    if ($res->isA(RDF::OWL_RESTRICTION)) {
        // restrictions in owl are anonymous, we need to create ids for them automatically
        $id = generateRestrictionId($res, $schema);
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

    $meta = (new Graph())->resource('.');

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

    $meta->addResource($schema->id, $id);
    $meta->addResource($schema->parent, $parentId);

    if (null === $meta->getLiteral($schema->label)) {
        $meta->addLiteral($schema->label, preg_replace('|^.*[/#]|', '', $id));
    }

    try {
        $fedoraRes = $repo->getResourceById($id);
        echo "updating " . $id;
        $fedoraRes->setMetadata($meta);
        $fedoraRes->updateMetadata();
    } catch (NotFound $e) {
        echo "creating " . $id;
        $fedoraRes = $repo->createResource($meta);
    } catch (GuzzleHttp\Exception\RequestException $e) {
        echo "\n" . $meta->getGraph()->serialise('turtle') . "\n";
        throw $e;
    }

    echo ' as ' . $fedoraRes->getUri() . "\n";
    $imported[] = $fedoraRes->getUri();
}

/**
 * Checks if $what inherits from $from
 */
function doesInherit(Resource $what, Resource $from): bool {
    if ($what === $from) {
        return true;
    }
    $flag = false;
    foreach ($from->getGraph()->resourcesMatching(RDF::RDFS_SUB_CLASS_OF, $from) as $i) {
        $flag |= doesInherit($what, $i);
    }
    return $flag;
}

/**
 * Checks if a given restriction is consistent with the rest of the ontology
 */
function checkRestriction(Resource $r, object $schema) {
    static $values = [];

    // there must be at least one class connected with the restriction 
    // (which in owl terms means there must be at least one class inheriting from the restriction)
    $children = $r->getGraph()->resourcesMatching(RDF::RDFS_SUB_CLASS_OF, $r);
    if (count($children) === 0) {
        echo $r->getUri() . " - no classes inherit from the restriction\n";
        return false;
    }

    // property for which the restriction is defined must exist and have both domain and range defined
    $prop = $r->getResource(RDF::OWL_ON_PROPERTY);
    if ($prop === null) {
        echo $r->getUri() . " - it lacks owl:onProperty\n";
    }
    $propDomain = $prop->getResource(RDF::RDFS_DOMAIN);
    if ($propDomain === null) {
        echo $r->getUri() . " - property " . $prop->getUri() . " has no rdfs:domain\n";
        return false;
    }
    $propRange = $prop->getResource(RDF::RDFS_RANGE);
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
    $onClass    = $r->allResources(RDF::OWL_ON_CLASS);
    foreach ($onClass as $i) {
        if (!doesInherit($i, $propRange)) {
            echo $r->getUri() . " - owl:onClass (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:range (" . $propRange->getUri() . ")\n";
            return false;
        }
        $rangeMatch += $i === $propRange;
    }
    $onDataRange = $r->allResources(RDF::OWL_ON_DATA_RANGE);
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
        $r->deleteResource(RDF::OWL_ON_CLASS);
        $r->deleteResource(RDF::OWL_ON_DATA_RANGE);
        $qCard = [RDF::OWL_QUALIFIED_CARDINALITY, RDF::OWL_MIN_QUALIFIED_CARDINALITY,
            RDF::OWL_MAX_QUALIFIED_CARDINALITY];
        foreach ($qCard as $srcProp) {
            $targetProp = str_replace('qualifiedC', 'c', str_replace('Qualified', '', $srcProp));
            foreach ($r->allLiterals($srcProp) as $j) {
                $r->addLiteral($targetProp, $j->getValue());
            }
            $r->delete($srcProp);
        }
    }

    // minQualifiedCardinality equal to 0 provides no information
    // (evaluated after simplification)
    if (count($r->allLiterals(RDF::OWL_MIN_QUALIFIED_CARDINALITY, 0)) > 0) {
        echo $r->getUri() . " - owl:minQualifiedCardinality = 0 which doesn't provide any useful information\n";
        return false;
    }

    $id = generateRestrictionId($r, $schema);

    // fix class inheritance
    foreach ($children as $i) {
        $i->deleteResource(RDF::RDFS_SUB_CLASS_OF, $r);
        $i->addResource(RDF::RDFS_SUB_CLASS_OF, $id);
    }

    // if restriction is duplicated there is no need to import it
    if (isset($values[$id])) {
        echo $r->getUri() . " - duplicated restriction (but no actions needed)\n";
        return null;
    }
    $values[$id] = '';

    return true;
}

function generateRestrictionId(Resource $res, object $schema): string {
    $idProps = [];
    foreach ($res->allResources(RDF::OWL_ON_PROPERTY) as $i) {
        $idProps[] = $i->getUri();
    }
    foreach ($res->allResources(RDF::OWL_ON_DATA_RANGE) as $i) {
        $idProps[] = $i->getUri();
    }
    foreach ($res->allResources(RDF::OWL_ON_CLASS) as $i) {
        $idProps[] = $i->getUri();
    }
    $cardProps = [RDF::OWL_QUALIFIED_CARDINALITY, RDF::OWL_MIN_QUALIFIED_CARDINALITY,
        RDF::OWL_MAX_QUALIFIED_CARDINALITY, RDF::OWL_CARDINALITY, RDF::OWL_MIN_CARDINALITY,
        RDF::OWL_MAX_CARDINALITY];
    foreach ($cardProps as $p) {
        $tmp = $res->getLiteral($p);
        if ($tmp !== null) {
            $idProps[] = $p . $tmp->getValue();
        }
    }

    $idProps = array_unique($idProps);
    sort($idProps);
    return $schema->namespaces->ontology . 'restriction-' . md5(implode(',', $idProps));
}
