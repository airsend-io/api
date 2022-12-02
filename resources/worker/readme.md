The code inside this folder is deprecated. It was replaced with the kafka worker, written in NodeJS.

That was made because nodejs can easily deal with Apache Kafka, and easily handle non-blocking/async code.

Keep this for now, just for short term reference.


Here's the docker-compose entry for the php container (just for reference):

```
    build:
      context: env/dev/kafkaworker
    volumes:
      - ./server/api:/var/www/dev:${CACHING_OPTION:-cached}
      - ./env/dev/api/conf/docker-php-ext-xdebug.ini:/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini:cached
    working_dir: /var/www/dev
    command: "php /var/www/dev/resources/worker/MQConsumer.php --groupid=parallelbackgrounders --topic=as_parallel_bg_queue"
    environment:
      AIRSEND_KAFKA_HOST: ${AIRSEND_KAFKA_HOST:-kafka:9092}
      AIRSEND_DEPLOYMENT_HOSTNAME: ${AIRSEND_DEPLOYMENT_HOSTNAME:-localhost}
      APP_INTERNAL_AUTH_TOKEN: ${APP_INTERNAL_AUTH_TOKEN:-}
```     

Here is the Dockerfile:
```
FROM php:7.2-fpm

# apt-get update
RUN apt-get update

# install the php extensions
RUN apt-get install -y libpq-dev libzip-dev unzip librdkafka-dev libzookeeper-mt-dev --no-install-recommends && \
    docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip

# kafka extension
RUN pecl install -o -f rdkafka && \
    rm -rf /tmp/pear && \
    docker-php-ext-enable rdkafka

# zookeeper extension
RUN pecl install -o -f zookeeper && \
    rm -rf /tmp/pear && \
    docker-php-ext-enable zookeeper

RUN docker-php-ext-install pcntl
RUN apt-get install -y procps
```

Deprecated on: Sep/2020 by Jeff Almeida.