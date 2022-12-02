## Introduction

Contains the function calls to access airsend data from database and cache.

### Structure

Data layer is organized into DataController and DataStore Classes.
DataController acts as a facade that can be called by other business layers to access and manipulate data
from database and cache.  The DataStore classes contains the queries to be run against the database.

## Usage

To create a User. 

```
$controller = new DataController($container);
$userObject = new User(.... pass all requirired params...);
$controller->createUser($userObject);
```
