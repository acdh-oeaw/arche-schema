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

use EasyRdf\Literal;
use EasyRdf\Resource;
use zozlak\RdfConstants as RDF;

/**
 * Description of SkosConcept
 *
 * @author zozlak
 */
class SkosConcept {

    /**
     *
     * @var \EasyRdf\Resource
     */
    public $res;

    public function __construct(Resource $res) {
        $this->res = $res->copy();
    }

    public function getUri(): string {
        return (string) $this->res->getUri();
    }
    
    public function getMetadata(): Resource {
        return $this->res;
    }
    
    /**
     * Sanitizes a SKOS concept by adding a repository id, assuring it has a title,
     * etc.
     * 
     * @param object $schema
     * @param string $parent
     * @param string $defaultLang
     * @return void
     */
    public function sanitize(object $schema, string $parent = null,
                             string $defaultLang = 'en'): void {
        $this->res->addResource($schema->id, $this->getUri());

        if (!empty($parent)) {
            $this->res->addResource($schema->parent, $parent);
        }

        $titles = [];
        foreach ($this->res->allLiterals(RDF::SKOS_ALT_LABEL) as $i) {
            $titles[$i->getLang() ?? ''] = (string) $i;
        }
        foreach ($this->res->allLiterals(RDF::SKOS_PREF_LABEL) as $i) {
            $titles[$i->getLang() ?? ''] = (string) $i;
        }
        $titles[$defaultLang] = $titles[$defaultLang] ?? ($titles[''] ?? (reset($titles) ?? $this->res->getUri()));
        unset($titles['']);
        foreach ($titles as $lang => $title) {
            $this->res->addLiteral($schema->label, new Literal($title, $lang));
        }
    }

}
