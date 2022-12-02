# AirSend

## Getting Started

### Dependencies

* PHP 7.2+
   * Extensions: PDO_MySQL
* NGINX with php-fpm
* MySQL 64 bit with innoDB
* Redis Cache
* Apache Kafka (MQ)
* NodeJS with Socket.IO

### Installing

* Run 
```
composer install
```

### Running Tests

* Run specific tests
```
composer test unit <testname>
```

* Run all unit tests
```
composer test unit 
```


## Quick References

* [Slim 4 Framework Docs](http://www.slimframework.com/docs/v4/)
* [Monolog Logger](https://github.com/Seldaek/monolog)
* [symfony/event-dispatcher Docs](https://symfony.com/doc/current/components/event_dispatcher.html)
* [php-di/php-di] (http://php-di.org/doc/)

## Detailed Notes

Whenever changing composer autoloader mapping, you would need to recreate the autoloader by running
```
composer dump-autoload
```