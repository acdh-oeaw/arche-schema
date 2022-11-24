# ACDH-Ontology

This is the repo for the `ACDH-Ontology`. The ontology is going to be used to describe resources in the acdh-repo.

* For an interactive network visualization of the current ontology, please see [here](https://vowl.acdh.oeaw.ac.at/#iri=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl) (thanks https://github.com/VisualDataWeb/WebVOWL) 
* For a more static overview - please see [here](https://teiminator.acdh.oeaw.ac.at/services/owl2html.xql?owl=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl)
  <!-- * or if you prefere a table-layout - click [here](https://teiminator.acdh.oeaw.ac.at/services/owl2html.xql?owl=https%3A%2F%2Fraw.githubusercontent.com%2Facdh-oeaw%2Frepo-schema%2Fmaster%2Facdh-schema.owl&format=table) -->

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

# Conventions

## Version numbers

* major number change is constituted by ANY of:
    * adjusting or adding a cardinality restriction
    * removing a class, a property or an annotation property
    * removing an annotation property
* middle number change is constitude by ANY of:
    * adding a new class property or annotation property
    * adjusting class inheritance
    * adjusting property inheritance, range or domain
    * adjusting values of annotation properties driving the doorkeeper or GUI behaviour
* minor number change is constituted by:
    * a change with no impact on the doorkeeper and GUI
      (e.g. description or translation changes)

## Classes

* As usual, class names have to start with a capital letter
* Use camelcase writing
* Uf a union of classes is required use a helper class
* Helper classes are all subclasses of acdh:Helper

## Properties

* As usual, property names have to start with a lower case letter
* Use camelcase writing
* The _direction_ of a property should be stated in the name of the property. 
    * *BAD* `acdh:title`
    * *GOOD* `acdh:hasTitle`
    * *BAD* `acdh:isMember`
    * *GOOD* `acdh:isMemberOf`
* **Remember about differences between datatype and object properties:**
    * What is what and how it impacts the GUI?
        * A datatype property has a literal value
          (in layman's terms in a ttl it's value is written between `"`, e.g. `_:someRes acdh:someProperty "literal value"`)
            * It can still be an URL/URI, just it will be stored as a string and will NOT create a separate repository resource
              (in GUI it will be displayed as a clickable link opening the URL in a new tab)
        * An object property has an URI value
          (in layman's terms in a ttl it's value is written between `<` and `>` or using a shorthand syntax, e.g. `_:someRes acdh:someProperty <https://some/url>` or `_:someRes acdh:someProperty acdhi:otherResource`)
            * Object property values **create new repository resources**
    * What can't be done (in owl)
        * A datatype property can't inherit from an object property (and vice versa)
        * A datatype property and an object property can't be equivalent
* **A property meaning must not depend on a class context**.
  If you feel a property meaning is (even a little) different for different classes, create two (or more) properties instead.
* Don't create both a property and its inverse version - it creates a lot of trouble and doesn't make providing data easier

## Ranges

* Choose datatype property ranges wisely as they affect the doorkeeper and the GUI
    * Properties using `xsd:` types other than `xsd:string` and `xsd:anyURI` will be casted by a doorkeeper
      (e.g. if the range is `xsd:date` and ingested value is `2018` the doorkeeper will cast it to `2018-01-01`)
    * Properties with range `xsd:anyURI` will be displayed in the GUI as clickable links opening in a new tab
      while all other datatype property values are displayed in the GUI just as they are

## Restrictions

* Do not use qualified cardinality restrictions - the range is already defined by a property and shouldn't be redefined (or changed) in the restriction
  (in Protege terms it means setting as range rdfs:Literal for datatype properties and owl:Thing for object properties)
* Try to avoid duplicating same restrictions on all subclasses of a common parent - define the restriction on the parent instead
  (you won't loose anything as Protege still shows you such inheritet restrictions and it will allow to keep the ontology smaller and simpler)
* Model actual repository behaviour
  (take into account not all resources in the repository are of any of ACDH classes defined in this ontology but some rules stil apply to them, e.g. all must have a title and an identifier)
    * Use `owl:Thing` to denote any resource in the repository

## Annotation properties

* Follow annotation property description closely
