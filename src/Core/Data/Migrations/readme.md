# Airsend Database Migrations

This folder holds the database migrations for Airsend database upgrades. This document holds information about the 
database migration process.

## Upgrading the database

To run the migrations there is a special script on composer. You can try it running this command:
```
composer run dbmigrate
```

This command will only show the available/pending migrations. To execute them, you need to include the `exec` param:
```
composer run dbmigrate exec
```

It will execute all the database changes that are necessary to put the database on the latest version.

IMPORTANT: Be sure to test the migrations on a development/stagin env before running it on production.

## Creating a migration

(Only for developers)

Let's say that you're creating a feature that requires the creation of a new table on the database (same works for
 column inclusion, index creation, triggers, constraints, views, etc).
 
Just follow those steps:
1. Let the team know that you're creating a DB migration (DB versions can get conflicted if more than one developer
 are creating migrations at the same time).
1. Check the current DB version on `asconfig.php`, on `/db/core/version` (for core database) and `/db/fs/version
` (for storage/filesystem database).
1. Increment the version on `asconfig.php` (depending on the database that you're changing)
1. Create a new migration class under this folder, following the pattern: `DBVersion#.php` for core database and
 `FSDBVersion#.php` for storage database, replacing the `#` with the DB version that you just incremented on
  `asconfig`. Copy/paste from a previous migration, just replacing the version number.
1. This class MUST extend the `AbstractMigration`, located on the same namespace.
1. Implement the abstract methods from `AbstractMigration`.

The required methods are:
* `getDescription` - Just return a short textual description about what is been changed by this migration. This text
 will be shown on the script and stored on the `versions` table.
* `handle` - This method must hold the logic to change the database. It receives a `DatabaseService` object, that
is able to run the queries that you need to do the changes on the database. On storage migrations (starting with `FS`) a
 `FSDatabaseService` object is injected. You also have access to the `logger` and `container` properties (protected
  properties from the parent class).
  
Once those steps are done, you should be good to run the script.

Important notes:
* Pay attention to the naming conventions (the whole subsystem will fail if the their not followed correctly)
* Be sure that all possible exceptions are handled inside the `handle` method.
* A good test on local envs, is to recreate the database (using the `initdb` script) and run all the migrations (they
 run on a sequential fashion).
* ALWAYS test it on development envs before running it on live.