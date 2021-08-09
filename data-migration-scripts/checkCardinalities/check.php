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

/*
 * Script checking properties cardinality restrictions for all repository resources
 * having an ACDH class assigned.
 *
 * Requires a config.yaml file allowing to initialize:
 * - `acdhOeaw\arche\lib\RepoDb` object (from acdh-oeaw/arche-lib library)
 * - `acdhOeaw\arche\lib\schema\Ontology` object (from acdh-oeaw/arche-lib-schema library)
 */

require_once 'vendor/autoload.php';
use zozlak\RdfConstants as C;
use acdhOeaw\arche\lib\schema\Ontology;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\RepoResourceDb;

$configLocation = 'config.yaml';
$cfg            = json_decode(json_encode(yaml_parse_file($configLocation)));
$dbConn         = new PDO($cfg->dbConn->guest);
$dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repo           = RepoDb::factory($configLocation);
$schemaCfg = (object) [
    'ontologyNamespace' => preg_replace('/#.*$/', '#', $cfg->schema->parent),
    'parent'            => $cfg->schema->parent,
    'label'             => $cfg->schema->label,
];
$ontology = new Ontology($dbConn, $schemaCfg);

$statsByClass    = [];
$statsByProp     = [];
$statsUnexpected = [];
$statsNotAllowed = [];

$query  = $dbConn->prepare("SELECT value AS class, count(*) AS count FROM metadata WHERE property = ? AND value LIKE ? GROUP BY 1 ORDER BY 1");
$query->execute([C::RDF_TYPE, $schemaCfg->ontologyNamespace . '%']);
$counts = [];
while ($i = $query->fetchObject()) {
    $counts[$i->class]       = $i->count;
    $statsByClass[$i->class] = [];
}

$param = [C::RDF_TYPE, $schemaCfg->ontologyNamespace . '%'];
$filter = '';
if ($argc > 1) {
    $filter = "JOIN (SELECT (get_relatives(id, ?, 999999, 0)).* FROM identifiers WHERE id = ? OR ids = ?) t USING (id)";
    $param = array_merge([$cfg->schema->parent, (int) $argv[1], $argv[1]], $param);
}
$queryStr = "FROM metadata $filter WHERE property = ? AND value LIKE ?";

$query    = $dbConn->prepare("SELECT count(DISTINCT id) $queryStr");
$query->execute($param);
$resCount = $query->fetchColumn();

$query = $dbConn->prepare("SELECT id, json_agg(value ORDER BY value) AS classes $queryStr GROUP BY id ORDER BY id");
$query->execute($param);
$n     = 1;
while ($i = $query->fetchObject()) {
    printf("Resource %d (%d / %d %.1f%%)\n", $i->id, $n, $resCount, 100 * $n / $resCount);
    $n++;
    $res  = new RepoResourceDb($i->id, $repo);
    $meta = $res->getGraph();

    $classes   = json_decode($i->classes);
    $allowedP  = [];
    foreach ($classes as $class) {
        $c         = $ontology->getClass($class);
        $checked   = [];
        foreach ($c->properties as $pUri => $p) {
            $allowedP[] = $pUri;
            if (in_array(spl_object_hash($p), $checked) || strpos($pUri, $schemaCfg->ontologyNamespace) !== 0) {
                continue;
            }
            $checked[] = spl_object_hash($p);

            $byLang = [];
            foreach ($meta->all($pUri) as $v) {
                if ($v instanceof EasyRdf\Resource) {
                    $byLang['URI'] = ($byLang['URI'] ?? 0) + 1;
                } else {
                    $byLang[(string) $v->getLang()] = ($byLang[(string) $v->getLang()] ?? 0) + 1;
                }
            }
            if ($p->min > 0 && count($byLang) < $p->min) {
                echo "\tmissing property $pUri\n";
                $statsByClass[$class][$pUri] = ($statsByClass[$class][$pUri] ?? 0) + 1;
                $statsByProp[$pUri] = ($statsByProp[$pUri] ?? 0) + 1;
            }
            if ($p->max > 0) {
                foreach ($byLang as $lang => $count) {
                    if ($count > $p->max) {
                        echo "\ttoo many values ($count) for property $pUri and lang $lang\n";
                    }
                }
            }
            if (!empty($p->vocabs)) {
                foreach ($meta->all($pUri) as $v) {
                    $v = (string) $v;
                    if (false === $p->checkVocabularyValue($v, Ontology::VOCABSVALUE_ID)) {
                        echo "\tvalue $v for property $pUri is not allowed\n";
                        if (!isset($statsNotAllowed[$pUri])) {
                            $statsNotAllowed[$pUri] = [];
                        }
                        $statsNotAllowed[$pUri][$v] = ($statsNotAllowed[$pUri][$v] ?? 0) + 1;
                    }
                }
            }
        }
    }

    foreach ($meta->propertyUris() as $pUri) {
        if (strpos($pUri, $schemaCfg->ontologyNamespace) === 0 && !in_array($pUri, $allowedP)) {
            echo "\tproperty $pUri used while ontology doesn't associate it with this resource class\n";
            if (!isset($statsUnexpected[$pUri])) {
                $statsUnexpected[$pUri] = [];
            }
            $cc = implode(', ', $classes);
            $statsUnexpected[$pUri][$cc] = ($statsUnexpected[$pUri][$cc] ?? 0) + 1;
        }
    }
}

echo "----------------------------------------\n";
foreach ($statsByClass as $class => $props) {
    if (count($props) > 0) {
        echo "Missing properties statistics for class $class\n";
        foreach ($props as $prop => $count) {
            printf("\t%s %d (%.1f%%)\n", $prop, $count, 100 * $count / $counts[$class]);
        }
    }
}
echo "----------------------------------------\n";
foreach ($statsUnexpected as $prop => $classes) {
    $domain = '?';
    foreach ($ontology->getProperty([], $prop)->domain ?? [] as $d) {
        if (strpos($d, $schemaCfg->ontologyNamespace) === 0) {
            $domain = $d;
            break;
        }
    }
    echo "Property $prop with domain $domain is used in incompatible classes:\n";
    foreach ($classes as $class => $count) {
        printf("\t%s %d\n", $class, $count);
    }
}
echo "----------------------------------------\n";
foreach ($statsNotAllowed as $prop => $values) {
    echo "Property $prop has wrong values:\n";
    foreach ($values as $value => $count) {
        echo "\t$value $count\n";
    }
}

