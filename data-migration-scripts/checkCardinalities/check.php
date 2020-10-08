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
 * - `acdhOeaw\acdhRepoLib\RepoDb` object (from acdh-oeaw/arche-lib library)
 * - `acdhOeaw\arche\Ontology` object (from acdh-oeaw/arche-lib-schema library)
 */

require_once 'vendor/autoload.php';
use zozlak\RdfConstants as C;

$configLocation = 'config.yaml';
$cfg            = json_decode(json_encode(yaml_parse_file($configLocation)));
$dbConn         = new PDO($cfg->dbConnStr->guest);
$repo           = acdhOeaw\acdhRepoLib\RepoDb::factory($configLocation);
$schemaCfg = (object) [
    'ontologyNamespace' => preg_replace('/#.*$/', '#', $cfg->schema->parent),
    'parent'            => $cfg->schema->parent,
    'label'             => $cfg->schema->label,
];
$ontology = new acdhOeaw\arche\Ontology($dbConn, $schemaCfg);

$statsByClass = [];
$statsByProp  = [];

$query  = $dbConn->prepare("SELECT value AS class, count(*) AS count FROM metadata WHERE property = ? AND value LIKE ? GROUP BY 1 ORDER BY 1");
$query->execute([C::RDF_TYPE, $schemaCfg->ontologyNamespace . '%']);
$counts = [];
$resCount = 0;
while ($i = $query->fetchObject()) {
    $counts[$i->class]       = $i->count;
    $resCount                += $i->count;
    $statsByClass[$i->class] = [];
}

$query = $dbConn->prepare("SELECT id, value AS class FROM metadata WHERE property = ? AND value LIKE ? ORDER BY value, id");
$query->execute([C::RDF_TYPE, $schemaCfg->ontologyNamespace . '%']);
$n     = 1;
while ($i = $query->fetchObject()) {
    printf("Resource %d (%d / %d %.1f%%)\n", $i->id, $n, $resCount, 100 * $n / $resCount);
    $n++;
    $res  = new acdhOeaw\acdhRepoLib\RepoResourceDb($i->id, $repo);
    $meta = $res->getGraph();

    $c       = $ontology->getClass($i->class);
    $checked = [];
    foreach ($c->properties as $pUri => $p) {
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
            $statsByClass[$i->class][$pUri] = ($statsByClass[$i->class][$pUri] ?? 0) + 1;
            $statsByProp[$pUri] = ($statsByProp[$pUri] ?? 0) + 1;
        }
        if ($p->max > 0) {
            foreach ($byLang as $lang => $count) {
                if ($count > $p->max) {
                    echo "\tto many values ($count) for property $pUri and lang $lang\n";
                }
            }
        }
    }
}

echo "----------------------------------------\n";
foreach ($statsByClass as $class => $props) {
    if (count($props) > 0) {
        echo "Missing property statistics for class $class\n";
        foreach ($props as $prop => $count) {
            printf("\t%s %d (%.1f%%)\n", $prop, $count, 100 * $count / $counts[$class]);
        }
    }
}

