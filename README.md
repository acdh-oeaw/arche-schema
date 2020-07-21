# ACDH-Ontology

This is the repo for the `ACDH-Ontology`. The ontology is going to be used to describe resources in the acdh-repo.

* For an interactive network visualization of the current ontology, please see [here](http://visualdataweb.de/webvowl/#iri=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl) (thanks https://github.com/VisualDataWeb/WebVOWL) 
* For a more static overview - please see [here](https://teiminator.acdh.oeaw.ac.at/services/owl2html.xql?owl=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl)
  * or if you prefere a table-layout - click [here](https://teiminator.acdh.oeaw.ac.at/services/owl2html.xql?owl=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl&format=table)

# Release cycle

Releasing new ontology versions requires lots of care. This is because the ontology determines behaviour of crucial ARCHE components (most notably the doorkeeper and the GUI) and because we must be able to assure already existing metadata are in line with the current ontology.

To assure new ontology release won't cause any trouble, the release process should go as follows:

* Create a new git branch (`git checkout -b branchName`, where *branchName* may be e.g. the next ontology version number).
* Make changes in the new branch, commit it and push to the GitHub (`git push origin branchName`).
* Create a pull request:
    * go to https://github.com/acdh-oeaw/arche-schema/compare
    * choose your branch in the `compare:` drop-down list
    * provide description of your changes
    * click the *create pull request* button
* Wait for approval from Martina, Mateusz and Norbert.  
  The checklist:
    * ontology check script reports no errors
    * arche-lib-schema passes tests against the new ontology
    * arche-doorkeeper passes tests against the new ontology
    * dynamic root table displays new ontology corretly
    * we have scripts for updating old metadata so they are in line with the new ontology
* Merge pull request and create a new release.

# Naming-Conventions

## Classes

* as usual, class names have to start with a capital letter
* use camelcase writing

* if a union of classes is required use a helper class
* helper classes are all subclasses of acdh:Helper

## Properties

* as usual, property names have to start with a lower case letter
* use camelcase writing
* the _direction_ of a property should be stated in the name of the property. 
  * *BAD* `acdh:title`
  * *GOOD* `acdh:hasTitle`
  * *BAD* `acdh:isMember`
  * *GOOD* `acdh:isMemberOf`
  
## Ranges

* preferably use types coming form xsd
  * e.g. use `xsd:string` instead of `rdfs:literal`
