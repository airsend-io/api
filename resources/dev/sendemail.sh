#!/bin/bash

DIRECTORY=`dirname $0`;
COMMAND="php $DIRECTORY/../cli/cli.php -r $DIRECTORY/send_email.php";

${COMMAND} "${@:1}"