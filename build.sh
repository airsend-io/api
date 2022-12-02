#!/bin/sh
cd /
rsync -avzh --info=progress2 --exclude=scratch/*/* /var/www/dev/ /var/www/build/
chown www-data: -R /var/www/build