* [Introduction](#introduction)
* [Quick start](#quick-start)
  * [Install](#install)
  * [Initialize the client](#initialize-the-client)
  * [Create new issue](#create-new-issue)
  * [Get existing issue](#get-the-issue)
  * [Update existing issue](#update-the-issue)
  * [Delete exiting issue](#delete-the-issue)
* [Documentation](#documentation)
  * [Client and ClientRaw](#client-and-clientraw)
  * [\Badoo\Jira\Issue class](#badoojiraissue-class)
  * [Custom fields](#custom-fields)
  * [Issue changelog](#issue-changelog)
  * [Other instances](#other-instances-of-the-badoo-jira-api-client)
* [Advanced topics](#advanced-topics)
  * [Managing API requests](#managing-api-requests)
  * [Extending \Badoo\Jira\Issue](#extending-badoojiraissue)
  * [Writing new CustomField base class](#writing-your-own-custom-field-base-class)


# Introduction

This is Badoo JIRA REST Client. It contains a bunch of wrapper classes for most common API objects:
Issues, Components and so on.

This makes code easier to write because of autocompletion your IDE will provide you.

You can also generate lots of classes for custom fields to get the documentation for your own JIRA installation
right in PHP code.


# Quick start

## Install

```bash
composer require badoo/jira-client
```

## Initialize the client

### Rest Client
```php
$Jira = \Badoo\Jira\REST\Client::instance();
$Jira
    ->setJiraUrl('https://jira.example.com/')
    ->setAuth('user', 'token/password');
$Client = new \Badoo\Jira\Client($Jira);
```
> NOTE: this action will save 'global' Jira rest config
> Every time you call a \Badoo\Jira\REST\Client::instance()->set...() method, \Badoo\Jira\REST\ClientRaw::$instance state is changed.
> It's safe if you have only one url/login for Jira server
> See [Client and ClientRaw](#client-and-clientraw)

### Common Client
```php
$Client = new \Badoo\Jira\Client(\Badoo\Jira\REST\Client::instance());
```
## Basic operations
### Issue
#### Create new issue

```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$Issue = $Client->createIssue('SMPL', 'Task')
    ->setSummary('Awesome issue!')
    ->setDescription('description of issue created by Badoo JIRA PHP client')
    ->setLabels(['hey', 'it_works!'])
    ->addComponents('Other','Try it yourself')
    ->send();

print_r(
    [
        'key'           => $Issue->getKey(),
        'summary'       => $Issue->getSummary(),
        'description'   => $Issue->getDescription(),
    ]
);
```

#### Get one issue
```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$Issue = $Client->getIssue('SMPL-1');

print_r(
    [
        'key'           => $Issue->getKey(),
        'summary'       => $Issue->getSummary(),
        'description'   => $Issue->getDescription(),
    ]
);
```
#### Get more issues
```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$issue_keys = [
    'SMPL-1',
    'SMPL-2',
    'SMPL-3',
    'SMPL-4',
    //...
    'SMPL-10',
];

$Issues = $Client->getIssues(...$issue_keys);

foreach ($Issues as $Issue){
    /**
     * @var \Badoo\Jira\Issue $Issue
     */
    echo $Issue->getSummary();
}
```

#### Update the issue

```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$Client->getIssue('SMPL-1')
    ->setSummary('Awesome issue!')
    ->setDescription('Yor new description for issue')
    ->edit('customfield_12345', ['add' => 'value for field'])
    ->save();

```

#### Delete the issue

```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$Client->deleteIssue('SMPL-1');
```

### User
#### Get user
```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$User = $Client->getUser('username');
```
#### Get user by email
```php
/**
 * @var \Badoo\Jira\Client $Client
 */
$Client->getUserByEmail('user@example.com');
```

# Documentation

> NOTE: all examples in this documentation related to any interaction with JIRA consider you configured 'global'
> client object.
>
> Read [Configure the client](#configure-the-client) section above to know how to do that.

## Client, REST\Client and REST\ClientRaw

The client to JIRA API is split into three parts:

#### Common Jira Client

A class that provides basic operations implemented in the library for issues, users, components and groups

Look at [Basic operations](#basic_operations)

#### Structured REST client \Badoo\Jira\REST\Client

It is split into several sections, one for each prefix of API methods: e.g. /issue, /field, /project and so on.
Each section has bindings to the  most popular API methods with parameters it accepts.

The idea is free you from remembering URIs and HTTP request types for common actions. It enables your IDE to give you a
hint about available API methods and the options you can provide to each.

Some of sections also cache API responses and have special 'synthetic' methods for most common actions. For example,
you can't get info on particular field by its ID using only API. You have also search through the response.
But with `\Badoo\Jira\REST\Client` you can do this

```php
/**
 * @var \Badoo\Jira\REST\Client $Client
 */
$FieldInfo = $Client->field()->get('summary');
print_r($FieldInfo);
```

When you can't find something in structured client, you still can access Raw client inside it to do everything you need:
```php
/**
 * @var \Badoo\Jira\REST\Client $Client
 */
$response = $Client->getRawClient()->get('/method/you/wat/to/request', [<parameters]);
```

The structured client also has a 'global' client object. This object can be accessed via instance() static method:
```php
$Client = \Badoo\Jira\REST\Client::instance();
```
Under the hood is 'new \Badoo\Jira\REST\Client()', but ::instance() will always return you the same
object for all calls to method.

Almost all wrapper classes inside `\Badoo\Jira` library require a configured API client to work.
It is always received as the last parameter of any static method or constructor of wrapper and it always defaults to
'global' client when the value was not provided.

Once you configured the global client you don't need to give API client to all wrappers you initialize.
They will get it by themselves.

```php
\Badoo\Jira\REST\Client::instance()
    ->setJiraUrl('https://jira.example.com')
    ->setAuth('user', 'password/token');
```

> NOTE: all following examples in documentation, related to any interaction with JIRA, consider you configured 'global'
> client object. That is why we don't pass initialized JIRA API Client to all Issue, CustomField and other objects.

The only reason we left the way to provide API client to all wrappers as a parameter is to enable you to interact with
several JIRA installations from one piece of code. For example, if you want to work with your staging and production
instances at the same time:

```php
$Prod = new \Badoo\Jira\REST\Client(new \Badoo\Jira\REST\ClientRaw('https://jira.example.com/'));
$Prod->setAuth('produser', 'password/token');

$Staging = new \Badoo\Jira\REST\Client(new \Badoo\Jira\REST\ClientRaw('https://jira.example.com/'));
$Staging->setAuth('staginguser', 'password/token');

$ProdIssue = new \Badoo\Jira\Issue('SMPL-1', $Prod);
$StagingIssue = new \Badoo\Jira\Issue('SMPL-1', $Staging);

// ...
```

#### The simplest interface to API: \Badoo\Jira\REST\ClientRaw

It can request API and parse responses.
Throws an `\Badoo\Jira\REST\Exception` for API errors or parsed response data when everything went OK.

That's all, it has no other complex logic inside: you decide what URI to request, which type of HTTP request to send
(GET, POST, etc.) and what parameters to send.

Consider ClientRaw as a smart wrapper for PHP curl.

```php
$RawClient = new \Badoo\Jira\REST\ClientRaw('https://jira.example.com');
$RawClient->setAuth('user', 'token/password');

$fields = $RawClient->get('/field');
print_r($fields);
```


## \Badoo\Jira\Issue class

### Getting \Badoo\Jira\Issue instances

To get an issue object you can create it providing only an issue key.

```php
$Issue = new \Badoo\Jira\Issue('SMPL-1');
```

This is equivalent to:
```php
$Client = \Badoo\Jira\REST\Client::instance();
$Issue = new \Badoo\Jira\Issue('SMPL-1', $Client);
```

If you want, you can instantiate another API client and provide it to `\Badoo\Jira\Issue` constructor.
This might be useful when you have several JIRA instances and want to work with them from single piece of code.
Look at [Client and ClientRaw](#client-and-clientraw) section of documentation to see how to configure instance of API client.

### Updating the issue

`\Badoo\Jira\Issue` object accumulates changes for fields in internal properties. This means, none of changes you did with
your $Issue object will be applied to real JIRA issue until you call ->save(). This allows you to update issue
in compact way, putting several field changes into a single API request. $Issue object will also continue to return old
field values until you send changes to JIRA with ->save().

```php
$Issue = new \Badoo\Jira\Issue('SMPL-1');
$Issue
    ->setSummary('new summary')
    ->setDescription('new description')
    ->edit('customfield_12345', 'new custom field value');

$Issue->getSummary(); // will return old issue summary, not the one you tried to set 3 lines of code ago

$Issue->save(); // makes API request to JIRA, updates all 3 fields you planned

$Issue->getSummary(); // will return new issue summary, as expected
```

### Checking if we can edit the field

Not all fields can be changed even if you have them displayed in fields list.
This can be caused by project permissions or issue edit screen configuration. To check if current user can update
field through API, use `->isEditable();`

```php
$Issue = new \Badoo\Jira\Issue('SMPL-1');
if ($Issue->isEditable('summary')) {
    // we can edit summary
} else {
    // we can't edit summary
}
```

### Initializing \Badoo\Jira\Issue object on partial fields data

You also can create `\Badoo\Jira\Issue` object on data that contains only some fields. For example, you store in your DB
some issues info for your own reasons: key, summary, and description. You can create `\Badoo\Jira\Issue` object on this
data without breaking the object logic: it still will load data from API when you need it.

```php
// Consider you get this data from your database:
$db_data = [
    'key' => 'SMPL-1',
    'summary' => 'summary of example issue',
    'description' => 'description of example issue',
];

// First, create an \stdClass object to mimic API response:
$IssueInfo = new \stdClass();
$IssueInfo->key = $db_data['key'];

$IssueInfo->fields = new \stdClass();
$IssueInfo->fields->summary = $db_data['summary'];
$IssueInfo->fields->description = $db_data['description'];

// Now we can create an issue object. It will store key, summary and description field values in internal cache
// When you need some additional data, e.g. creation time or assignee - object will still load it from API on demand.
$Issue = \Badoo\Jira\Issue::fromStdClass($IssueInfo, ['key', 'summary', 'description']);
```

## Custom fields

You can generate a custom field with special generator stored in this repositroy.
For more information follow `CFGenerator` subdirectory and open README.md file.
You will find both quickstart and detailed documentation on generator there.

In this section we consider you already created a class for regular custom field,
available out of the box in JIRA:
'Checkboxes', 'Number Field', 'Radio Buttons', 'Select List (single choice)' and so on.

Let's consider you created custom field class (or classes) inside `\Example\CustomField` namespace.

### Field value: get, check, set

```php
$MyCustomField = \Example\CustomFields\MyCustomField::forIssue('SMPL-1'); // get field value from JIRA API

$field_value = $MyCustomField->getValue();
$field_is_empty = $Value->isEmpty(); // true when field has no value

if ($Value->isEditable()) {
    $MyCustomField->setValue($MyCustomField::VALUE_AWESOME); // consider this is a select field
    $MyCustomField->save(); // send API request to update field value in JIRA
}
```

### Several custom fields on single issue

When you need to work with several custom fields of the same issue, it is a better practice to use single $Issue object
for it:

```php
$Issue = new \Badoo\Jira\Issue('SMPL-1');

$MyCustomField1 = new \Example\CustomFields\MyCustomField1($Issue);
$MyCustomField2 = new \Example\CustomFields\MyCustomField2($Issue);

$MyCustomField1->setValue('value of first field');
$MyCustomField2->setValue('value of second field');

$Issue->save();
```

## Issue changelog

Changelog of issue has the following structure:
```
    - changelog record 1 (issue update event 1)
        - changelog item 1 (field 1 changed)
        - changelog item 2 (field 2 changed)
        - ...
    - changelog record 2 (issue update event 2)
        - changelog item 1 (field 1 changed)
        - ...
```

There is a special `\Badoo\Jira\History` class designed to work with this data.
It uses its own wrappers for each piece of information from changelog:

```
\Badoo\Jira\Issue\History
    \Badoo\Jira\Issue\HistoryRecord[]
        \Badoo\Jira\Issue\LogRecordItem[]
```

### Getting issue's history of changes

If you already have an issue object to work with, just use `->getHistory()` method:

```php
$Issue = new \Badoo\Jira\Issue('SMPL-1');

$History = $Issue->getHistory();
```

When you have none, just create an object using static method:
```php
$History = \Badoo\Jira\Issue\History::forIssue('SMPL-1');
```

History class has some useful methods to help you solve most common tasks:
- track field changes,
- calculate time in statuses,
- get last change of issue,
- get last change of specific issue field,
- ...and so on

Discover it's methods using your IDE autocompletion, they might be useful!

## Other instances of the Badoo JIRA API Client

Most of the wrapper classes, e.g. User, Status, Priority and so on, have ability to transparently load data from API on
demand.

As for CustomFields and Issue objects, you have 2 ways of initialization: with static methods (e.g. `::get()`)
and regular constructor:

```php
$User = new \Badoo\Jira\User(<user name>);

$User = \Badoo\Jira\User::byEmail(<user email>);
```

Most of them have shorthand static methods:

```php
$users = \Badoo\Jira\User::search(<pattern>); // looks for all users with login, email or display name similar to pattern
$Version = \Badoo\Jira\Version::byName(<project>, <version name>); // looks for version with specific name in project
$components = \Badoo\Jira\Component::forProject(<project>); // lists all components available in project
```

Names of this methods have similar structure. For convenience we decided to follow next convention:

* _::search()_ static methods are about multi-criteria search of instances.
  This is applicable, e.g. for `\Badoo\Jira\Issue::search()` where you use complex JQL queries and
  `\Badoo\Jira\User::search()` where JIRA looks through several user attributes trying to find you a user.
* _::get()_ static methods are about getting a single object by its ID with immediate request to API inside method.
  This allows you to control where exactly you will get the `\Badoo\Jira\REST\Exception` on API errors if you need it.
* _::by<Criteria>()_ static methods provide you with single or multiple objects identified by some single criteria.

  Example:
  * \Badoo\Jira\User::byEmail() gives you a JIRA User by its email
  * \Badoo\Jira\Version::byName() gives you a JIRA Version by its name.
* _::for<Instance>()_ static methods look for all items somehow related to Instance.

  Example:
  * `\Badoo\Jira\CustomField::forIssue()` gives you a custom field object related to an issue
  * `\Badoo\Jira\Version::forProject()` gives you all versions created in specific project

* _::fromStdClass()_ method is used by all wrapper classes for initialization on data from API.
  If you got some information from API with specific request using, say, `\Badoo\Jira\REST\ClientRaw`,
  you still can operate with typed objects instead of raw \stdClass' ones

  Example:
  ```php
  $PriorityInfo = \Badoo\Jira\REST\ClientRaw::instance()->get('priority/<priority ID>');
  $Priority = \Badoo\Jira\Issue\Priority::fromStdClass($PriorityInfo);
  ```

The classes who work as active records and know not only how to load data from API, but also how to set it, use
the same behaviour as `\Badoo\Jira\Issue` uses: they accumulate changes within object and push them to API only on
`->save()` method call.

# Advanced topics

## Managing API requests

Once $Issue object is created with 'new' instruction - it has only issue key and client inside.
It will load data only when you try to get some field for the first time:

```php
$Issue = new \Badoo\Jira\Issue('SMPL-1'); // no request to API here, just an empty object is returned
$Issue->getSummary(); // causes request to JIRA API
```

When $Issue object loads data from API by himself, it does not select the fields to load.
This increases API response time and loads lots of data which is not required 'right now' for getting issue's summary,
but \Badoo\Jira\Issue has no idea how many additional ->get<Field>() calls it will get,
so it is better to load all info once, than ask API many times, when you need the summary, then the description, status,
priority and so on.

We compared the time it takes the JIRA to load the data and send it to the client (see examples/measure-load-time.php).
It may vary from installation to installation, but almost always (as far as we know - always) the 'get all fields'
request will be more effective than 3 'get single field' requests and frequently it will be more effective than 2 ones.

```text
Get single field time: 0.42949662208557
Get all fields time: 0.84061505794525
```

You can make it do the API call immediately after new instance creation by using one of class' static methods:
```php
$Issue = \Badoo\Jira\Issue::byKey('SMPL-1'); // causes request to JIRA API
$Issue->getSummary(); // no request here, object already has all the data on issue
```

The only thing `\Badoo\Jira\Issue` manages inside is 'expand'. JIRA API allows you to request various portions of
information for issue, controlled by 'expand' parameter.
E.g. in most cases you don't need rendered HTML code of fields, or issue changelog.
This data will not be loaded by `\Badoo\Jira\Issue` by default when you call ->get<Field>().
Only default data provided by JIRA API will be loaded.

When you need an issue history, `\Badoo\Jira\Issue` object _has_ to request API once again to get it.
It will also provide object with updated fields information and you will get updated summary, description and so on
if they changed since the last call to API.

In most cases, when you work with a single issue, you don't need to bother yourself with this internal logic
of `\Badoo\Jira\Issue` class, but understanding is required to manage API requests in an effective way when you
start to work with lots of issues at the same time:
you can choose several ways of Issue objects initialization and this will
have different side effects on API requests amount and effectiveness.

For example, if you know you need only summary and description for lots of issues, you can request only them.
This will dramatically reduce the time of API response:
```php
// load only summary and description for the latest 1000 issues in project 'SMPL'.
$issues = \Badoo\Jira\Issue::search('project = SMPL ORDER BY issuekey DESC', ['summary', 'description']);

foreach($issues as $Issue) {
    $Issue->getDescription(); // this will not make \Badoo\Jira\Issue to silently request JIRA API in background

    $Issue->getPriority(); // but this - will. $Issue object has no status information in cache.
}
```

Issue history can be quite hard to load for JIRA. It affects API response time significantly,
especially when you have long changelogs.
This is the thing you also can optimize by telling `\Badoo\Jira\Issue` what do you need:
```php
// load latest 100 issues from project 'SMPL'
$issues = \Badoo\Jira\Issue::search(
    'project = SMPL ORDER BY issuekey DESC',
    [],
    [\Badoo\Jira\REST\Section\Issue::EXP_CHANGELOG],
    100
);

foreach ($issues as $Issue) {
    $description = $Issue->getDescription(); // this will not cause API request
    $status_changes = $Issue->getHistory()->trackField('status'); // this will not cause API request too!
}
```

Unfortunately, you can't use both $fields and $expand parameters at the same time.
This is because of internal logic of `\Badoo\Jira\Issue` cache, that will be broken by such combination.
We will fix this issue in the future if it show up itself as problematic.


### Managing API requests with custom fields

You can instantiate a custom field object in several ways. As for `\Badoo\Jira\Issue` instantiation,
they differ in API requests required for initialization and values update.

```php
$MyCustomField = \Example\CustomFields\MyJIRACustomField::forIssue('SMPL-1');

// The example above is equivalent to:
$Issue = \Badoo\Jira\Issue::byKey('SMPL-1', ['key', \Example\CustomFields\MyJIRACustomField::ID]);
$MyCustomField = new \Example\CustomFields\MyJIRACustomField($Issue);
```

In both examples CustomField object we creted has \Badoo\Jira\Issue object under the hood.
The difference reveals when you start to work with several custom fields of one issue.

Initialization with static method `::forIssue()` will always create new \Badoo\Jira\Issue object under the hood.
This means that fields:
```php
$MyCustomField1 = \Example\CustomFields\MyFirstCustomField::forIssue('SMPL-1');
$MyCustomField2 = \Example\CustomFields\MySecondCustomField::forIssue('SMPL-1');
```
will have different `\Badoo\Jira\Issue` objects, even though they are refer to the single JIRA issue.

All custom fields use `\Badoo\Jira\Issue` as instrument to manage their values:
they load data through it and edit themselves using interface Issue provides.

When you call `$CustomField->setValue()`, it actually is simillar to `$Issue->edit(<custom field id>, <new field value>);`.

That means you are able to 'stack' several custom field changes in one $Issue object to send updates to API only once,
making interaction with API more optimal.

```php
$Issue = \Badoo\Jira\Issue::byKey('SMPL-1'); // causes API request to get all issue fields

$MyCustomField1 = new \Example\CustomFields\MyFirstCustomField($Issue);
$MyCustomField2 = new \Example\CustomFields\MySecondCustomField($Issue);
// other custom fields initialization

$MyCustomField1->setValue('new value'); // no API requests here. Field value in JIRA remains the same
$MyCustomField2->setValue($MyCustomField2::VALUE_CHANGED); // no API requests here too.
// other custom fields changes

$Issue->save(); // API request to JIRA with field updates

// Now JIRA issue has new field values and one new changelog record.
// You can also use $MyCustomField2->save(); - it is the same,
// but with $Issue->save(); it is more clear what is happening
```

### Managing API requests with other classes

Other classes, like Status, Priority and User, have special `::get` static method which duplicates a regular constructor
but has effect on requests to API.

```php
$Status = new \Badoo\Jira\Issue\Status(<status ID>); // no request to API here
$Status->getName(); // requests API in background. This is where exception will be thrown on errors.

// ...

$Status = \Badoo\Jira\Issue\Status::get(<status ID>); // request to API here. This is where exception will be thrown on errors.
```

## Extending \Badoo\Jira\Issue

`\Badoo\Jira\Issue` is about abstract JIRA instance. It has no idea about custom fields you oftenly use, statuses you
frequently transition to, and so on. It is much more convenient to have your own shortcuts for actions you do often

To do this, we recomment to craete you own Issue class to extend `\Badoo\Jira\Issue` functionality with your own methods.

For example, you might want to easily close issue with one call, setting resolution to some special value.
Here is the receipt:
```php
namespace Example;

class Issue extends \Badoo\Jira\Issue {
    public function markDone() : Issue
    {

        return $this;
    }
}

// ...

$Issue = new \Example\Issue('SMPL-1');
$Issue->markDone();
```

You would probably want to extend `\Badoo\Jira\Issue\CreateRequest` to return your Issue object instead of
original one:

```php
namespace Example;

class CreateRequest extends \Badoo\Jira\Issue\CreateRequest {
    public function create() : \Badoo\Jira\Issue
    {
        $Issue = parent::create();

        $IssueInfo = new \stdClass();
        $IssueInfo->id = $Issue->getId();
        $IssueInfo->key = $Issue->getKey();

        return \Example\Issue::fromStdClass($IssueInfo, ['id', 'key']);
    }
}
```

### Methods to use in child class

Here is just a piece of code with examples. They are much more informative than lost of words.
```php
namespace Example;

class Issue extends \Badoo\Jira\Issue {
    public function getSomeDataFromRawApiResponse()
    {
        /** @var \stdClass $IssueInfo - contains an issue data obtained from JIRA API,
                                        returned from \Badoo\Jira\ClientRaw 'as-is'. */
        $IssueInfo = $this->getBaseIssue();

        $issue_key = $IssueInfo->key;
        $issue_id = $IssueInfo->id;
        $self_link = $IssueInfo->self;
        $summary = $IssueInfo->fields->summary;
        // ...
    }

    public function getFieldUsingCache() : \stdClass
    {
        return $this->getFieldValue('customfield_12345');
        // this is equivalent to
        //  $this->getBaseIssue()->fields->customfield_12345;
        // but will not cause API request when you use partial field inititialization
    }

    public function getMyCustomField() : \Example\CustomFields\MyCustomField
    {
        return $this->getCustomField(\Example\CustomFields\MyCustomField::class);
        // this will also not cause API request when you use partial field initialization, but also return you
        // the same object of \Example\CustomFields\MyCustomField each time you use the method
    }
}
```

## Writing your own custom field base class

All custom fields should be inherited from `\Badoo\Jira\Issue\CustomFields\CustomField` class or one of its children
The simplest examples of custom field base classes are `\Badoo\Jira\CustomFields\TextField` and
`\Badoo\Jira\CustomFields\NumberField`.

There are some additional special methods you should know about:
* `$this->getOriginalObject()` - gets field value as it is provided by JIRA API.
* `$this->dropCache()` - drops internal object cache, e.g. drops cached field value.

`getOriginalObject()` method requests bound Issue object for current field value.
It caches value inside current object, it is safe to call it multiple times in a row.
This will not cause several API requests.
We expect you to always use this method instead of `$this->Issue->getFieldValue()` when you write your own
wrapper inherited directly from `\Badoo\Jira\Issue\CustomFields\CustomField`.

`dropCache()` method is intended to drop all data about field value cached internally in object. If you plan to use
internal properties in your custom class, don't forget to redefine `dropCache()` method so
it clears values of your fields.

`dropCache()` method is called by bound Issue object once it loads data from API. This is a way to notify all
existing bound custom field objects that field value might have been updated.
