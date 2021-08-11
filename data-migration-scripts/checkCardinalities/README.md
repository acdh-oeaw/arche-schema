# Cardinalities check

Checking resource cardinality constraints is too complex to be done (in a convenient way) using an SQL query.

The `check.php` script takes benefit of the `acdh-oeaw/arche-lib-schema` library to make the check simpler.

**For performance reasons it has to be run from a place allowing direct connection to the database.**

## Installation

* Clone this repository and open the `{repoRootDir}/data-migration-scripts/checkCardinalities` directory.
* Run `composer update`.
* Inspect `config.yaml` and adjust if needed (take a look at `dbConn.guest`, `rest.urlBase` and `rest.pathBase`).

## Usage

`php -f check.php` or php -f check.php resourceId` (the latter one checks only the givnen resource and its children)

