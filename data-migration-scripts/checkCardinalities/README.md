# Cardinalities check

Checking resource cardinality constraints is too complex to be done (in a convenient way) using an SQL query.

The `check.php` script takes benefit of the `acdh-oeaw/arche-lib-schema` library to make the check simpler.

It has to be run from a place allowing direct connection to the database.

The `config.yaml` provided in this repository is set up to work with the test database setup as described on https://github.com/acdh-oeaw/arche-schema/tree/master/data-migration-scripts#environment-for-preparing-data-migration-scripts. Please adjust it to your needs when running using different setup.

## Installation

* clone this repository and open the `{repoRootDir}/data-migration-scripts/checkCardinalities` directory
* adjust database connection settings in `config.yaml` (no need to do so if you are using the test database setup as described on https://github.com/acdh-oeaw/arche-schema/tree/master/data-migration-scripts#environment-for-preparing-data-migration-scripts)
* run `composer update`

## Usage

`php -f check.php`

