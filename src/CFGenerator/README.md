# Badoo JIRA Custom fields classes generator

Here is the generator, which can load list of custom fields from JIRA and create classes for them
The main idea is to free yourself from handwriting bunch of similar code

> NOTE: This section of documentation is for kind of advanced Generator usage. 
> There is a special CLI script for Generator, which makes its usage much more simple out of the box.
>
> If you just explore the possibilities of instruments in this repository, we recommend to start from CLI script first.
> Check `bin/README.md` for more information.

## Howto's

### Generate single field class by regular rules

Here is an example of code for single field class generation.

```php
// Initizlize API client

$Jira = new \Badoo\Jira\REST\Client;
$Jira
    ->setJiraUrl('https://jira.example.com/')
    ->setAuth('user', 'token/password');
    
// Create instance of generator 

$Generator = new \Badoo\Jira\CFGenerator\Generator($Jira);

// Set it up
$Generator
    ->setTargetDirectory('your path here')
    ->setTargetNamespace('your namespace here');
    
// Prevent all fields from being generated:
$Generator->skipTypePattern('/.*/');

// Allow only one we want
$Generator->skipField('customfield_12345', false);

$Generator->generateField('customfield_12345');
```

> NOTE: generateField ignores all skip rules. If you tell generator "do exactly this field" - it does.

### Give the generator a custom template

If you plan to use native PHP templates with the renderer provided with Generator out of the box:

```php
// Template name is used only in error messages and allows to narrow the field of error when you face it
$Template = new \Badoo\Jira\CFGenerator\SimpleTemplate('MyCustomTemplate', $Jira);
 
// When field has unlimited values and JQL search line does not suggest anything for field in 
// JIRA WEB UI - this is just a waste of time. Think if you need it.
$Template->setLoadOptions(true); // true if we want template to load suggestions for field. 

// Template file location
$Template->setTemplatePath('path to template file');

// Map particular field to your template
$Generator->mapFieldToTemplate('customfield_12345', $Template);

// Map all fields of specific type to your template
$Generator->mapTypeToTemplate('com.atlassian.jira.plugins...', $Template);

// Now generator will use new template for configured
//$Generator->generateField('customfield_12345');
//$Generator->generateAll();
 
```

### Use custom template engine

Generator expects not a \Badoo\Jira\CFGenerator\SimpleTemplate class as a template. 
It expects \Badoo\Jira\CFGenerator\ITemplate

Once you implemented it - you can map the field or field type to your very special template,
written on awesome template engine you use.

```php
namespace \Example;

class SpecialTemplate implements ITemplate
{
    public function render(\stdClass $FieldInfo, string $full_class_name) : string
    {
        // You must use provided $full_class_name for class name and namespace
        // for consistency of code you generate
         
        // Split full class path into namespace and class
        $class_parts    = explode('\\', $full_class_name);
        $class_name     = array_pop($class_parts);
        $namespace      = implode('\\', $class_parts);

        // Load options for field if you want. You need a JIRA client inside your class for that:
        $this->Jira->jql()->getFieldSuggestions($FieldInfo->name)

        // Generate class for field
        // you now what to do.
        return '<rendered class text to be saved to file>';
    }
}
```

### Resolve class name collisions

Generator uses `generated-fields.lock` file. That's no the feature of CLI utility, it is built in generator code.

Use the recipe from `bin/README.md` to solve the problem. This solution will make generator to provide custom 
class name to `$full_field_class_name` argument of `ITemplate->render()` method.

### Use custom logger

Generator uses PSR-3 logger inside. This means you can provide it any logger that is embedded into your
infrastructure to track what is happening.

```php
$Logger = <PSR-3 compatible configured logger you prefer>;

$Generator->setLogger($Logger);
```

### Generate single field class from custom template

```php
$Generator = <configured generator you want to use>

$Template = <my custom template I want to use>;
$FieldInfo = $Jira->fields()->get('customfield_10500');
$class_name = 'IWantToUseSpecialClassForIt';

$Generator->generateField($FieldInfo, $Template, $class_name);
```
