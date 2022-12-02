#!/bin/sh

BASEDIR=$(dirname "$0");

POPULATOR_SCRIPT="php resources/cli/cli.php -r ${BASEDIR}/asclouddbinitialize.php --tests --quiet --hide-script-info";
DUMP_FILE="${BASEDIR}/../../tests/_data/dump.sql";

if [ "$1" = "init" ]; then

  # run the db initializer
  ${POPULATOR_SCRIPT};

  # store the dump
  mysqldump -hdb -u"${AIRSEND_DB_ROOT_USER}" -p"${AIRSEND_DB_ROOT_PASSWORD}" asclouddb_tests > "${DUMP_FILE}";

else

  # create the database from dump
  mysql -hdb -u"${AIRSEND_DB_ROOT_USER}" -p"${AIRSEND_DB_ROOT_PASSWORD}" asclouddb_tests < "${DUMP_FILE}";

fi

