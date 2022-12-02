# Application

Contains the Server API Framework level code. 

## Settings

A config registry class holds all system level settings. This can be connected to zookeeper if needed for easy
extension to handle more complex and distributed system configurations.

## Dependencies

A DI container holds all the services that are possibly needed

## Middleware

Common Middleware is configured here

## Routes

Define the API endpoints and the managers that execute them.