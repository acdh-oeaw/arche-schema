#!/usr/bin/php
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

use acdhOeaw\acdhRepoLib\Repo;
use acdhOeaw\arche\schemaImport\Ontology;

$t0 = time();

if ($argc < 2 || !file_exists($argv[1])) {
    echo "Imports external vocabularies defined in the ontology.\n\n";
    echo "usage: $argv[0] config.yaml [ontology.owl]\n\n";
    return;
}
$cfg = json_decode(json_encode(yaml_parse_file($argv[1])));

if (isset($cfg->composerLocation)) {
    require_once $cfg->composerLocation;
} else {
    require_once __DIR__ . '/vendor/autoload.php';
}

$repo = Repo::factory($argv[1]);

$ontology = new Ontology($cfg->schema);
if ($argc > 2) {
    $ontology->loadFile($argv[2]);
} else {
    $ontology->loadRepo($repo);
}

try {
    $repo->begin();
    $ontology->importVocabularies($repo, true);
    $repo->commit();
} finally {
    if ($repo->inTransaction()) {
        $repo->rollback();
    }
}
echo "\nFinished in " . (time() - $t0) . "s\n";
