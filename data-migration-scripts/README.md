## Environment for preparing data migration scripts

To create migration scripts you need a database containing both current content of the public ARCHE instance and the new ontology.

* Obtain a current repository dump
    * Log into `arche@apollo.arz.oeaw.ac.at`
    * Run `./login.sh`
    * Run `pg_dump -c -T users -T raw -N archive -f /home/www-data/config/dump.sql && exit`
    * Run `cd shares/config && zip dump.sql.zip dump.sql && rm dump.sql && mv dump.sql.zip ~/ && exit`
    * Transfer the `dump.sql.zip` using sftp (e.g. `sftp arche@apollo.arz.oeaw.ac.at` followed by `get dump.sql.zip`, `rm dump.sql.zip` and `exit` or using your favourite SFTP client like FileZilla of WinSCP)
    * Unzip it (e.g. `unzip dump.sql.zip` or using your favourite archive manager)
* Prepare a test docker environment
    * Run
      ```
      git clone --depth 1 --branch arche https://github.com/acdh-oeaw/arche-docker-config.git config
      rm -f config/run.d/gui.sh config/run.d/oaipmh.sh config/run.d/resolver.sh config/run.d/logs.sh
      chmod -x config/initScripts/*.php
      chmod +x config/initScripts/01-users.php
      mkdir -p log postgresql
      docker run --name arche -p 5432:5432 -v `pwd`/log:/home/www-data/log -v `pwd`/config:/home/www-data/config -v `pwd`/postgresql:/home/www-data/postgresql -e USER_UID=`id -u` -e USER_GID=`id -g` -d acdhch/arche
      ```
    * Wait until the `# INIT SCRIPTS ENDED` line appears at the end of the `log/initScripts.log` (it may take a few minutes)
    * Run
      ```
      echo "listen_addresses = '*'" >> postgresql/postgresql.conf
      sed -i -E 's/peer|ident|md5/trust/g' postgresql/pg_hba.conf
      echo "host all all 127.0.0.1/0 trust" >> postgresql/pg_hba.conf
      docker exec arche supervisorctl restart postgresql
      ```
* Restore the database dump
    * Move the dump to the location accessible from the container with
      `mv dump.sql log/`
    * Log into the database with
      ```
      docker exec -ti -u `id -u` arche psql
      ```
    * Run `\i log/dump.sql` and exit with `\q`
    * Connect to the databse (e.g. with `psql -h 127.0.0.1 -p 5432 -U www-data` or using your favourite app like PgAdmin or Dbeaver) and run
      `UPDATE identifiers SET ids = replace(ids, 'https://arche.acdh.oeaw.ac.at/api/', 'http://127.0.0.1/api/') WHERE ids LIKE 'https://arche.acdh.oeaw.ac.at/api/%';`
* Import the new ontology
    * Move the ontology file into the docker container with
      `cp acdh-schema.owl log/`
    * Go into the docker container
      ```
      docker exec -ti -u `id -u` arche bash
      ```
    * Run
      ```
      chmod +x config/initScripts/10-updateOntology.php
      cp log/acdh-schema.owl vendor/acdh-oeaw/arche-schema/acdh-schema.owl
      config/initScripts/10-updateOntology.php
      ```
* Your test database is ready. You can connect to it locally (`127.0.0.1`) on port `5432` as a `www-data` user (the database name is also `www-data`). Password is not required.

