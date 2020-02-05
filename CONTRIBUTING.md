# Contribution guidelines

* [Setup the env](#setup-the-environment)
* [Change the code](#change-the-code)
  * [Comments in code](#comments-in-code)
  * [Code structure](#the-code-structure-we-follow)
    * [Exceptions](#exceptions)
    * [REST API Client](#api-client)
    * [Wrapper classes](#wrapper-classes)
* [Make a commit and MR](#make-a-commit-and-mere-request)

## Setup the environment

After cloning the repository fork, you just need to install the dependencies using composer:
```bash
composer install
```

That's it! You're ready.

## Change the code

### Comments in code

All functions and all methods in classes MUST have [PHP Doc](https://www.phpdoc.org/) comments with parameter and return
types specification. They also SHOULD contain comments for each parameter and description of method itself.
Here is the example of ideal documented function:

```php
    /**
     * Check if data under key <key> is available in cache.
     * NOTE: checks only for key existence, returns true even when value associated with key is 'null' or 'false'.
     *
     * @param string $key - key to check in cache
     * @return bool - true when key with name <key> exists in cache
     */
    protected function isCached($key) : bool
    {
        // <function implementation here>
    }
```


### The code structure we follow

All classes of Badoo JIRA PHP Client are located in `\Badoo\Jira` PHP namespace. This namespace is matched to `src`
project directory thanks to `autoload` configuration in `composer.json` file.

We follow [PSR-4](https://www.php-fig.org/psr/psr-4/) specification.

We also use namespaces to split the code by ideas and purpose, there are special namespaces for:
* exceptions (`\Badoo\Jira\Exception\ `)
* API client (`\Badoo\Jira\REST\ `)
* Issue related objects (`\Badoo\Jira\Issue\ `)
* Custom field base classes (`\Badoo\Jira\CustomFields\ `)
* Custom field generator script code (`\Badoo\Jira\CFGenerator\ `)
* CLI utility related code (`\Badoo\Jira\CLI\ `)
* Helpers with some very common code not bound to any JIRA instance, like code for working with
  strings, JSON data and files (`\Badoo\Jira\Helpers\ `)

We may introduce more special namespaces in the future. The main idea is to put wrappers for portions of data related to
some large JIRA object into their own namespace, like `Status` class for Issues.
Statuses are not about any other JIRA object, so there is no need to keep them in 'global' `\Badoo\Jira\ ` namespace.

#### Exceptions

All custom exceptions thrown from `\Badoo\Jira\ ` code MUST be inherited from `\Badoo\Jira\Exception` class.

All REST client custom exceptions have their own location in `\Badoo\Jira\REST\Exception\ ` namespace and
MUST be inherited from `\Badoo\Jira\REST\Exception` class.

#### API Client

JIRA REST API client of this library is split into sections united by single JIRA object,
e.g. `\Badoo\Jira\REST\Section\Issue` is a class with methods for interaction with JIRA issues.
This sectioning is based on API structure itself: all methods for the same object type have common URI prefix in JIRA.

If you don't know where to put the new method, or how to name the new section class, URIs of JIRA API methods can
give you a hint. We usually just transform the URI prefix to CamelCase, like for `issuetype` API methods or
`statuscategory` ones.

JIRA has several APIs for different parts of its functionality, for example it has
separate API for agile boards management.
Sections for this additional APIs are located under common API type prefix.

> This means all section classes for, say, `agile` JIRA REST API should be placed into `\Badoo\Jira\REST\Agile\` namespace.

This seems all about the file locations and class naming. Let's go deeper into the code now.
All section classes follow the same interface convention:

* All methods that want to return complex objects or lists of objects MUST return them as `\stdClass` or
  `\stdClass[]` (array of `\stdClass` objects).

In PHP mixed data sets can be represented either as arrays, or as \stdClass objects. For example both of code fragments

```php
$objectAsArray = [
    "propA" => "valA",
    "propB" => 2,
    "propC" => true,
];
```

```php
$objectAsStdClass = new \stdClass();
$objectAsStdClass->propA = "valA";
$objectAsStdClass->propB = 2;
$objectAsStdClass->propC = true;
```

keeps the same data of the same types under the same keys. They will be serialized to the same JSON or YAML data.
If we step aside from internal realization, they keep the same _meaning_.

There is no reason why we prefer `\stdClass` to `arrays`, it was just a choice.
We want to keep things same across the library, this is just one of the restrictions that allow us to acheive the goal.

This means that all `->get.*()` methods that want to return complex data MUST return single `\stdClass` object
and `->list.*()` ones MUST return array of `\stdClass` objects.

* If the method gets, creates updates or deletes single instance of main object related to the API section, it
  should be named just `get()`, `create()`, `update()` or `delete()`.

  For example, we have a section class for operating with JIRA fields, called `\Badoo\Jira\REST\Section\Field`.
  All methods there are related to fields and there is no need to name getter as `getField()` here. `get` is clear enough.

  When you can select single instance by several criterias, e.g. by instance ID or by its Name, you MAY provide separate
  getters for each: `get()` for ID and `getByName()` one for name.

* Methods `search` and `searchBy.*` are for searching instances in JIRA. They do not guarantee user the data will be
  found and returned.

  For example, method `\Badoo\Jira\REST\Section\Priority::searchByName()` MAY return priority with given name,
  but it also MAY return nothing (`null`) without any exception.

  Another method `\Badoo\Jira\REST\Section\Issue::search` returns a list of `\stdClass` objects as a result.
  It MAY return an empty list at any time.

* Methods `list` and `list*` MUST provide caller with `\stdClass[]` of instances of the same type without any
  selection by user-defined criteria. They are just for listing of all known objects.

  For example, method `\Badoo\Jira\REST\Section\Field::list()` returns all fields confugured in your JIRA instance
  regardless of field type. `::listCustom()` returns only custom fields, it does not expect any query string as argument,
  like `search*` methods do.

#### Wrapper classes

Wrapper classes for global JIRA objects, like `User`, `Issue` or `Version` are located at package namespace
root `\Badoo\Jira`. Wrappers

* Wrapper classes MUST be able to load data transparently on demand.

* Wrapper class constructor method SHOULD expect minimum data for initialization

* Each wrapper class MUST have `::fromStdClass` static method which allows manual object initialization on response
  from JIRA API

* ...


<Here would be the rules for wrapper classes>


## Make a commit and mere request

When writing the commit message, please follow the recommendations described
in [conventional commits](https://www.conventionalcommits.org/en/v1.0.0/) specification.
This simple rules ease new library versions documentation a lot and make git history
readable and clear.
