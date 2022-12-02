This folder should hold the private/public key pair for oauth server.

Those files MUST be environment specific, so we need to generate them on each env.

That's why this folder contents are ignored on VCS by default (except for this readme, of course).

This folder is also mapped as a specific docker volume (so we can set specific permissions for the files).

It means that this folder isn't mapped inside the container, and you won't be able to see it's contents
inside the container (like this readme), or the generated keys on your host OS.

To generate the private/public key pair, run those commands inside this directory:

First generate the private key:

`openssl genrsa -out private.key 2048`
 
Then extract the public key from the private key:
 
`openssl rsa -in private.key -pubout -out public.key`

After that, set the correct OS level permissions to the keys:

`chmod 600 private.key`
`chmod 600 public.key`

Don't forget to set the correct ownership to those files. They should be owned by the
same user that runs the php-fpm service (usually www-data).
`chown www-data: private.key`
`chown www-data: public.key`

On each environment a Oauth key must be generated too. To do that go to the root of the api, and run this command 
(inside the API container):
`vendor/bin/generate-defuse-key`

Get the result and save on the OAUTH_KEY environment variable (inside .env), and restart the api container.