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

use EasyRdf\Resource;
use zozlak\RdfConstants as RDF;

/**
 * Class checking ontology restrictions consistency.
 *
 * @author zozlak
 */
class Restriction {

    /**
     *
     * @var EasyRdf\Resource
     */
    private $res;

    /**
     *
     * @var object
     */
    private $schema;

    public function __construct(Resource $res, object $schema) {
        $this->res    = $res;
        $this->schema = $schema;
    }

    /**
     * Checks if a given restriction is consistent with the rest of the ontology
     */
    public function check(bool $verbose): ?bool {
        static $values = [];

        // there must be at least one class connected with the restriction 
        // (which in owl terms means there must be at least one class inheriting from the restriction)
        $children = $this->res->getGraph()->resourcesMatching(RDF::RDFS_SUB_CLASS_OF, $this->res);
        if (count($children) === 0) {
            echo $verbose ? $this->res->getUri() . " - no classes inherit from the restriction\n" : '';
            return false;
        }

        // property for which the restriction is defined must exist and have both domain and range defined
        $prop = $this->res->getResource(RDF::OWL_ON_PROPERTY);
        if ($prop === null) {
            echo $verbose ? $this->res->getUri() . " - it lacks owl:onProperty\n" : '';
        }
        $propDomain = $prop->getResource(RDF::RDFS_DOMAIN);
        if ($propDomain === null) {
            echo $verbose ? $this->res->getUri() . " - property " . $prop->getUri() . " has no rdfs:domain\n" : '';
            return false;
        }
        $propRange = $prop->getResource(RDF::RDFS_RANGE);
        if ($propRange === null) {
            echo $verbose ? $this->res->getUri() . " - property " . $prop->getUri() . " has no rdfs:range\n" : '';
            return false;
        }

        // classes inheriting from the restriction must match or inherit from restriction's property domain
        foreach ($children as $i) {
            if (!Util::doesInherit($i, $propDomain)) {
                echo $verbose ? $this->res->getUri() . " - owl:onClass (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:domain (" . $propDomain->getUri() . ")\n" : '';
                return false;
            }
        }

        // target classes of qualified restrictions must match or inherit from restriction's property range
        $rangeMatch = 0;
        $onClass    = $this->res->allResources(RDF::OWL_ON_CLASS);
        foreach ($onClass as $i) {
            if (!Util::doesInherit($i, $propRange)) {
                echo $verbose ? $this->res->getUri() . " - owl:onClass (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:range (" . $propRange->getUri() . ")\n" : '';
                return false;
            }
            $rangeMatch += $i === $propRange;
        }
        $onDataRange = $this->res->allResources(RDF::OWL_ON_DATA_RANGE);
        foreach ($onDataRange as $i) {
            if (!Util::doesInherit($i, $propRange)) {
                echo $verbose ? $this->res->getUri() . " - owl:onDataRange (" . $i->getUri() . ") doesn't inherit from owl:onProperty/rdfs:range (" . $propRange->getUri() . ")\n" : '';
                return false;
            }
            $rangeMatch += $i === $propRange;
        }

        // simplify qualified restrictions which qualified rules don't differ from restriction's property range
        if (count($onClass) + count($onDataRange) === $rangeMatch && $rangeMatch > 0) {
            echo $verbose ? "simplifying " . $this->res->getUri() . "\n" : '';
            $this->res->deleteResource(RDF::OWL_ON_CLASS);
            $this->res->deleteResource(RDF::OWL_ON_DATA_RANGE);
            $qCard = [RDF::OWL_QUALIFIED_CARDINALITY, RDF::OWL_MIN_QUALIFIED_CARDINALITY,
                RDF::OWL_MAX_QUALIFIED_CARDINALITY];
            foreach ($qCard as $srcProp) {
                $targetProp = str_replace('qualifiedC', 'c', str_replace('Qualified', '', $srcProp));
                foreach ($this->res->allLiterals($srcProp) as $j) {
                    $this->res->addLiteral($targetProp, $j->getValue());
                }
                $this->res->delete($srcProp);
            }
        }

        // minQualifiedCardinality equal to 0 provides no information
        // (evaluated after simplification)
        if (count($this->res->allLiterals(RDF::OWL_MIN_QUALIFIED_CARDINALITY, 0)) > 0) {
            echo $verbose ? $this->res->getUri() . " - owl:minQualifiedCardinality = 0 which doesn't provide any useful information\n" : '';
            return false;
        }

        $id = $this->generateId();

        // fix class inheritance
        foreach ($children as $i) {
            $i->deleteResource(RDF::RDFS_SUB_CLASS_OF, $this->res);
            $i->addResource(RDF::RDFS_SUB_CLASS_OF, $id);
        }

        // if restriction is duplicated there is no need to import it
        if (isset($values[$id])) {
            echo $verbose ? $this->res->getUri() . " - duplicated restriction (but no actions needed)\n" : '';
            return null;
        }
        $values[$id] = '';

        return true;
    }

    public function generateId(): string {
        $idProps = [];
        foreach ($this->res->allResources(RDF::OWL_ON_PROPERTY) as $i) {
            $idProps[] = $i->getUri();
        }
        foreach ($this->res->allResources(RDF::OWL_ON_DATA_RANGE) as $i) {
            $idProps[] = $i->getUri();
        }
        foreach ($this->res->allResources(RDF::OWL_ON_CLASS) as $i) {
            $idProps[] = $i->getUri();
        }
        $cardProps = [RDF::OWL_QUALIFIED_CARDINALITY, RDF::OWL_MIN_QUALIFIED_CARDINALITY,
            RDF::OWL_MAX_QUALIFIED_CARDINALITY, RDF::OWL_CARDINALITY, RDF::OWL_MIN_CARDINALITY,
            RDF::OWL_MAX_CARDINALITY];
        foreach ($cardProps as $p) {
            $tmp = $this->res->getLiteral($p);
            if ($tmp !== null) {
                $idProps[] = $p . $tmp->getValue();
            }
        }

        $idProps = array_unique($idProps);
        sort($idProps);
        return $this->schema->namespaces->ontology . 'restriction-' . md5(implode(',', $idProps));
    }

}
