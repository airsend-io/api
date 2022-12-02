# Response Filter

The response filter sub-system is a tool that allow to the client calling the 
REST API, to be able to send a filter to reduce the size of the response,
and get only the data that is needed, avoiding big response payloads.

By the backend perspective, the response filter can be enabled in any route,
just attaching the `ResponseFilterMiddleware`.

Once this middleware is attached, the caller can just send a `response_filter`
parameter (on the request body, or on the query sring), and the response will
reflect what the client asked for (without any change on the endpoint execution).

One important information is: This middleware doesn't affect the internal processing
of the request. It only filters the output. It means that: If the endpoint brings
millions of records from the database, it will still do it, but after that, the
response will be filtered. The goal of this middleware is to allow clients to 
reduce the response size, not to reduce internal api processing.

Response filters only work on json responses.

If no response filter is provided, the entire response will be given.

### The response_filter property syntax

- A response filter is always a string
- The response filter string is a set of paths, separated by `;` (it's possible and common to have just one path)
- Each path on the response filter will return an object, and all objects will be merged on the response.
- Each path, is a set of steps, separated by `.`
- Each step can be of 3 types: a single property, a list of properties or an array/range filter
- A property step, is just a simple string, that represents a property of the json object. Ex: `id` or `members`
- A property list step, is just a list of properties that must be included on the response, separated by `,`. Ex: 
  `id,channel_name`
- An array/range step, is a filter that represents a range of an array. It can be one of the following types:
  - `*` or empty: The entire array. Ex: `channels.*.id,channel_name` or `channels..id,channel_name`
  - A single number: Only one specific item of the array. Ex: `channels.0.id,channel_name`
  - A range of items, separated by `-`. The range can have both limits, or just one, like `0-10`, `-10` (anything 
    smaller or equals to 10) or `10-` (anything bigger or equals to 10). Ex: `channels.0-10.id,channel_name` or 
    `channels.10-.id,channel_name`
  - A property-filtered range. It's possible to use any of the above patterns, followed by a property filter between 
    square brackets (`[]`). Ex: `channels.*[id=123456].channel_name` or `channels.0-10[created_on_ts>4564564].channel_name`
  


Examples:

Given the json

```json
{
  "countries": [
    {
      "name": "Brazil",
      "continent": "South America",
      "lang": "Portuguese",
      "population": 211,
      "biggest_cities": [
        {
          "name": "São Paulo",
          "population": 12
        },
        {
          "name": "Rio de Janeiro",
          "population": 7
        },
        {
          "name": "Brasilia",
          "population": 3
        }        
      ]
    },
    {
      "name": "USA",
      "continent": "North America",
      "lang": "English",
      "population": 328,
      "biggest_cities": [
        {
          "name": "New York",
          "population": 8
        },
        {
          "name": "Los Angeles",
          "population": 4
        },
        {
          "name": "Chicago",
          "population": 3
        }
      ]
    },
    {
      "name": "Canada",
      "continent": "North America",
      "lang": "English",
      "population": 37,
      "biggest_cities": [
        {
          "name": "Toronto",
          "population": 3
        },
        {
          "name": "Montreal",
          "population": 2
        },
        {
          "name": "Calgary",
          "population": 1
        }
      ]
    },
    {
      "name": "France",
      "continent": "Europe",
      "lang": "French",
      "population": 67,
      "biggest_cities": [
        {
          "name": "Paris",
          "population": 2
        },
        {
          "name": "",
          "Marseille": 1
        }
      ]
    },
    {
      "name": "England",
      "continent": "Europe",
      "lang": "English",
      "population": 55,
      "biggest_cities": [
        {
          "name": "London",
          "population": 8
        },
        {
          "name": "Birmingham",
          "population": 1
        }
      ]
    },
    {
      "name": "Germany",
      "continent": "Europe",
      "lang": "German",
      "population": 83,
      "biggest_cities": [
        {
          "name": "Berlin",
          "population": 4
        },
        {
          "name": "Hanburg",
          "population": 2
        },
        {
          "name": "Munchen",
          "population": 1
        }
      ]
    }
  ]
}
```
- Just the name of all countries: `countries..name` 
- Just the name of the 3 first countries: `countries.-2.name`
- Names from all countries from Europe: `countries.*[continent=Europe].name`
- Names of countries with population bigger than 100kk: `countries.*[population>100].name`
- Just the country name and the biggest city name of all countries (assuming biggest cities are ordered): 
  `countries..name;countries..biggest_cities.0.name`
- Country name and city name of cities with more than 2kk inhabitants:
  `countries..name;countries..biggest_cities.*[population>=2].name`