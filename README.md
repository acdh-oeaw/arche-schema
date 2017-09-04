# ACDH-Ontology

This is the repo for the `ACDH-Ontology`. The ontology is going to be used to describe resources in the acdh-repo.

* For an interactive network visualization of the current ontology, please see [here](http://visualdataweb.de/webvowl/#iri=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl) (thanks https://github.com/VisualDataWeb/WebVOWL) 
* For a more static overview - please see [here](https://teiminator.acdh.oeaw.ac.at/services/owl2html.xql?owl=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl)
  * or if you prefere a table-layout - click [here](https://teiminator.acdh.oeaw.ac.at/services/owl2html.xql?owl=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl&format=table)

# Naming-Conventions

## Classes

* as usual, class names have to start with a capital letter
* use camelcase writing

## Properties

* as usual, property names have to start with a lower case letter
* use camelcase writing
* the _direction_ of a property should be stated in the name of the property. 
  * *BAD* `acdh:title`
  * *GOOD* `acdh:hasTitle`
