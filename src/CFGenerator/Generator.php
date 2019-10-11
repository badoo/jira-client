<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CFGenerator;

/**
 * Class Generator
 * @package Badoo\Jira\CFGenerator
 *
 * Generator allows to generate wrapper classes for custom fields.
 * Each class is generated from template registred in Generator templates list.
 *
 * You can add custom templates by calling Generator::addTemplate().
 * Once added, template should be bound to one or more fields or field types
 * using Generator::mapFieldToTemplate and Generator::mapTypeToTemplate
 *
 * If you want to generate classes for custom fields covered only by default templates provided
 * with Generator out of the box:
 *
 *    $G = new \Badoo\Jira\CFGenerator\Generator($ConfiguredJiraClient)
 *    $G->generate();
 *
 * This will make generator to create custom field classes in current working directory for all main JIRA custom
 * field types supported by current version of Generator
 *
 * A bit more sophisticated, but still the most common usage scenario of Generator looks like this:
 *
 *    $G = new \Badoo\Jira\CFGenerator\Generator($ConfiguredJiraClient)
 *
 *    // Configure generator to personal needs of your project
 *
 *    $G->setTargetDirectory('/abs/or/relative/path/to/generated/classes');
 *    $G->setTargetNamespace('\My\Jira\CustomFields\');
 *
 *    $CustomTemplate = <initialization of your custom template here>
 *
 *    $G->mapFieldToTemplate('customfield_12345', $CustomTemplate);
 *    $G->mapTypeToTemplate('com.atlassian.jira.plugin.system.customfieldtypes:datepicker', $CustomTemplate);
 *
 *    // Run classes generation
 *
 *    $G->generate();
 */
class Generator implements \Psr\Log\LoggerAwareInterface
{
    /*
     * Configuration
     */

    const DIR_WITH_DEFAULT_TEMPLATES = 'templates';

    const DEFAULT_GENERATED_MAP_FILE = 'generated-fields.lock';

    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \Psr\Log\LoggerInterface */
    protected $Logger;

    /** @var string */
    protected $target_directory = '';
    protected $generated_map_file = '';
    protected $target_namespace = '';

    /** @var SimpleTemplate[] - list of known default templates for fields */
    protected $default_templates = [];

    /** @var ITemplate[] - JIRA field ID -> field template map
     *                  Example:
     *                    [ 'customfield_12345' => <TextField.php.tpl>, ... ]
     */
    protected $field_template_map = [];
    /** @var ITemplate[] - JIRA custom field type -> field template map
     *                  Example:
     *                    [ 'com.atlassian.jira.plugin.system.customfieldtypes:textfield' => <TextField.php.tpl>, ... ]
     */
    protected $type_template_map = [];

    /** @var bool[] - skip generation for fields with given IDs
     *                  (e.g. customfield_12345) */
    protected $skip_fields = [];
    /** @var bool[] - skip generation for fields with given types
     *                  (e.g. com.atlassian.jira.plugin.system.customfieldtypes:userpicker) */
    protected $skip_types = [];
    /** @var bool[] - skip generation for fields which 'type' matches regex
     *                  (e.g. 'com\.pyxis\.greenhopper\.jira:.*')
     */
    protected $skip_patterns = [];

    /*
     * Internal properties
     */
    protected $map_file_loaded = false;

    /** @var string[] - maps field ID to last known class name that was generated for field.
     *                  This makes generator to use the same class for the same field even after field rename. */
    protected $generated_fields = [];

    /** @var string[][] - maps class name of generated class to field ID inside this class.
     *                    This allows to detect class name collisions for several fields during class generation. */
    protected $generated_classes = [];

    /**
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use. It DOES NOT default to global client.
     *                                        To use global client, provide \Badoo\Jira\REST\Client::instance() intentionally
     */
    public function __construct(\Badoo\Jira\REST\Client $Jira)
    {
        $this->Jira = $Jira;
        $this->Logger = new SimpleLogger();

        $this->loadDefaultTemplates();
    }

    /**
     * Reads defailt templates config and maps *.tpl files with field templates to desired field types
     */
    protected function loadDefaultTemplates()
    {
        $config_file = $this->getLocalPath(static::DIR_WITH_DEFAULT_TEMPLATES, '_template-config.json');

        $json = \Badoo\Jira\Helpers\Files::fileGetContents($config_file);
        $config = \Badoo\Jira\Helpers\Json::decode($json, true);

        foreach ($config as $field_type => $template_config) {
            $template_name = $template_config['name'];
            $load_options = $template_config['load-options'] ?? false;
            $template_file_path = $this->getLocalPath(static::DIR_WITH_DEFAULT_TEMPLATES, "{$template_name}.tpl");

            $Template = new SimpleTemplate($template_name, $this->Jira);

            $Template
                ->setTemplatePath($template_file_path)
                ->setLoadOptions($load_options);

            $this->default_templates[$field_type] = $Template;
        }
    }

    /**
     * Get generated map file path.
     * This file contains kind of database with fields list and their generated classes.
     *
     * File helps to detect code generation collisions (when two custom fields are mapped to the same class name)
     * and allows to keep the custom field in the same class even when field is renamed in JIRA.
     */
    protected function getGeneratedMapFile() : string
    {
        if (empty($this->generated_map_file)) {
            return $this->getTargetPath(static::DEFAULT_GENERATED_MAP_FILE);
        }

        return $this->generated_map_file;
    }

    /**
     * Read DB with last generation results data: fields treated, classes generated and so on.
     */
    protected function loadGeneratedFieldsInfo()
    {
        if ($this->map_file_loaded) {
            return;
        }
        $this->Logger->debug('Loading results of previous generations...');

        $map_file = $this->getGeneratedMapFile();
        $this->Logger->debug("  Generated map file is '{$map_file}'");

        if (!\Badoo\Jira\Helpers\Files::exists($map_file)) {
            $this->Logger->warning(
                "Map file '{$map_file}' does not exist." .
                " Ignore this warning if you use Generator in '{$this->getTargetPath()}' for the first time"
            );
            $this->map_file_loaded = true;
            return;
        }

        try {
            $this->Logger->debug("    Getting map file contents...");
            $json = \Badoo\Jira\Helpers\Files::fileGetContents($map_file);

            $this->Logger->debug("    Decoding map file as JSON...");
            $map = \Badoo\Jira\Helpers\Json::decode($json, true);
        } catch (\RuntimeException | \UnexpectedValueException $e) {
            $this->Logger->critical($e->getMessage());
            throw $e;
        }

        $this->generated_fields = $map['fields-classes'] ?? [];
        $this->generated_classes = $map['classes-fields'] ?? [];

        $this->map_file_loaded = true;
        $this->Logger->debug("  ...loaded " . count($this->generated_fields) . " field records and " . count($this->generated_classes) . " class records.");
    }

    /**
     * Save generation results to file.
     */
    protected function saveGeneratedFieldsInfo()
    {
        $map_file = $this->getGeneratedMapFile();

        $json = \Badoo\Jira\Helpers\Json::encode(
            [
                'fields-classes' => $this->generated_fields,
                'classes-fields' => $this->generated_classes,
            ],
            JSON_PRETTY_PRINT
        );

        \Badoo\Jira\Helpers\Files::filePutContents($map_file, $json);
    }

    /**
     * Mark field as generated. Store class name we created and field ID we treated.
     * This is required to keep consistent generation results after field renames and to detect class name collisions
     *
     * @param \stdClass $FieldInfo - JIRA custom field info as it is returned from JIRA API
     * @param string $class_name - name of generated class
     */
    protected function markGenerated(\stdClass $FieldInfo, string $class_name)
    {
        $this->Logger->debug("  field {$FieldInfo->id} is generated with class name '{$class_name}'");

        $this->generated_fields[$FieldInfo->id] = [
            'class_name' => $class_name,
            'field_id' => $FieldInfo->id,
            'field_name' => $FieldInfo->name,
        ];

        $this->generated_classes[$class_name] = [
            'class_name' => $class_name,
            'field_id' => $FieldInfo->id,
            'field_name' => $FieldInfo->name,
        ];
    }

    /**
     * Check if the class name we want to generate is already used by some other field with similar name
     *
     * @param string $class_name - class name we want to check.
     * @param \stdClass $FieldInfo - JIRA custom field info as it is returned from JIRA API
     *
     * @return bool - true when ID of field stored in <class name> differs from <FieldInfo> ID
     */
    protected function hasCollision(string $class_name, \stdClass $FieldInfo)
    {
        $generated_class_info = $this->generated_classes[$class_name] ?? [];
        $field_id = $generated_class_info['field_id'] ?? $FieldInfo->id;

        return $field_id !== $FieldInfo->id;
    }

    /**
     * Get name of class for field. Uses class name generator used earlier if it already generated the field.
     *
     * @param \stdClass $FieldInfo - JIRA custom field info as it is returned from JIRA API
     *
     * @return string - name of class to generate for this field
     */
    protected function getFieldClass(\stdClass $FieldInfo) : string
    {
        $generated_field_info = $this->generated_fields[$FieldInfo->id] ?? null;
        if ($generated_field_info !== null) {
            return $generated_field_info['class_name'];
        }

        return \Badoo\Jira\Helpers\Strings::toCamelCasePHPLabel($FieldInfo->name);
    }

    /**
     * Check if we are going to write to file that was not generated by Generator earlier
     * (e.g. was created manually by developer)
     *
     * @param string $class_name
     * @param string $class_file
     * @return bool
     */
    protected function isNotGeneratedFileWrite(string $class_name, string $class_file) : bool
    {
        $generated_class_info = $this->generated_classes[$class_name] ?? null;

        if (isset($generated_class_info)) {
            return false;
        }

        return \Badoo\Jira\Helpers\Files::exists($class_file);
    }

    /**
     * Get path to file near Generator location.
     *
     * @param string[] $subpath
     * @return string
     */
    protected function getLocalPath(string ...$subpath) : string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $subpath);
    }

    /**
     * Get path to file/directory within <target directory> configured by setTargetDirectory() method
     *
     * @param string[] $subpath
     * @return string - path within target directory.
     */
    protected function getTargetPath(string ... $subpath) : string
    {
        $target_dir = $this->target_directory ?: '.';
        return $target_dir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $subpath);
    }

    /**
     * Get configured client to JIRA generator uses for field classes generation
     */
    public function getJira() : \Badoo\Jira\REST\Client
    {
        return $this->Jira;
    }

    public function setLogger(\Psr\Log\LoggerInterface $Logger) : Generator
    {
        $this->Logger = $Logger;
        return $this;
    }

    /**
     * Store generated files in this directory.
     *
     * @param string $target_directory - path to target directory. All generated classes will be located inside.
     *
     * @return $this
     */
    public function setTargetDirectory(string $target_directory) : Generator
    {
        $this->target_directory = rtrim($target_directory, DIRECTORY_SEPARATOR);
        $this->map_file_loaded = false;

        return $this;
    }

    /**
     * Override default generated map file path. This path used as-is: reltive would be relative to CWD, not to target
     * dir path.
     *
     * You should commit this file with all the generated classes to track classes changes between generations even
     * after field renames.
     * This file also allows you to avoid class name collisions for custom fields with similar names
     *
     * @param string $generated_map_file - path to generated file
     *
     * @return $this
     */
    public function setGeneratedMapFile(string $generated_map_file) : Generator
    {
        $this->generated_map_file = $generated_map_file;
        $this->map_file_loaded = false;

        return $this;
    }

    /**
     * Set the PHP namespace for all generated classes.
     *
     * @param string $namespace - namespace for generated classes
     *
     * @return $this
     */
    public function setTargetNamespace(string $namespace) : Generator
    {
        $this->target_namespace = trim($namespace, '\\');
        return $this;
    }

    /**
     * Bind field with <field_id> to class template.
     * This particular field class will be generated using template with given name
     *
     * NOTE: this overrides settings made by mapTypeToTemplate() for particular fields.
     * @see Generator::mapTypeToTemplate()
     *
     * @param string $field_id - ID of field to map to template (e.g. customfield_12345)
     * @param ITemplate $Template - template to use for field
     *
     * @return $this
     */
    public function mapFieldToTemplate(string $field_id, ITemplate $Template)
    {
        $this->field_template_map[$field_id] = $Template;
        return $this;
    }

    /**
     * Bind field <type>  to class template.
     * Template with <template name> will be used for all fields of this type if there is not template bound
     * to particular field by field id
     * @see Generator::mapFieldToTemplate()
     *
     * @param string $type - name of custom field type (e.g. com.atlassian.jira.plugin.system.customfieldtypes:textfield)
     * @param ITemplate $Template - template to use for field
     *
     * @return $this
     */
    public function mapTypeToTemplate(string $type, ITemplate $Template)
    {
        $this->type_template_map[$type] = $Template;
        return $this;
    }

    /**
     * Skip field generation.
     * Class for field with <field_id> should (not) be generated.
     *
     * @param string $field_id - ID of field to skip (e.g. customfield_12345)
     * @param bool $skip - true: skip the field,
     *                     false: don't skip. This setting overrides skipType()
     *
     * @return $this
     */
    public function skipField(string $field_id, bool $skip = true) : Generator
    {
        $this->skip_fields[$field_id] = $skip;
        return $this;
    }

    /**
     * Skip class generation for fields of given type (exact match).
     * Class for field of type <type> should (not) be generated.
     *
     * @param string $type - type to skip (e.g. com.atlassian.jira.plugin.system.customfieldtypes:textfield)
     * @param bool $skip - true: skip all fields of this type.
     *                           This seetting can be overriden by skipField() for particular fields
     *                     false: don't skip fields of this type.
     *                            This does not affect situations when field has no matching template or the generated class is empty
     *
     * @return $this
     */
    public function skipType(string $type, bool $skip = true) : Generator
    {
        $this->skip_types[$type] = $skip;
        return $this;
    }

    /**
     * Skip class generation for fields with type matching regex.
     * The first matching regex is used.
     *
     * @param string $regex
     * @param bool $skip
     *
     * @return $this
     */
    public function skipTypePattern(string $regex, bool $skip = true) : Generator
    {
        $this->skip_patterns[$regex] = $skip;
        return $this;
    }

    /**
     * Forget all skip rules configured for fields
     *
     * @return $this
     */
    public function clearSkipRules() : Generator
    {
        $this->skip_fields = [];
        $this->skip_types = [];
        $this->skip_patterns = [];

        return $this;
    }

    /**
     * Get template for custom field discribed in <FieldInfo>
     *
     * @param \stdClass $FieldInfo - JIRA custom field info as it is returned from JIRA API
     *
     * @return ITemplate|null - template found for field.
     *                          null is returned when no templates configured for field's ID and type
     */
    public function getTemplateFor(\stdClass $FieldInfo) : ?ITemplate
    {
        $this->Logger->debug("  getting template for field {$FieldInfo->id}...");

        $field_id = $FieldInfo->id;
        $field_type = $FieldInfo->schema->custom;

        if (isset($this->field_template_map[$field_id])) {
            $this->Logger->debug("    field for {$FieldInfo->id} found by ID.");
            return $this->field_template_map[$field_id];
        }

        if (isset($this->type_template_map[$field_type])) {
            $this->Logger->debug("    field for {$FieldInfo->id} found by Type.");
            return $this->type_template_map[$field_type];
        }

        $this->Logger->debug("    no custom templates found for {$FieldInfo->id}, trying default templates.");
        return $this->default_templates[$field_type] ?? null;
    }

    /**
     * Check if generator should skip the field.
     * Field is skipped by ID or by type
     *
     * @see Generator::skipField()
     * @see Generator::skipType()
     *
     * @param \stdClass $FieldInfo - JIRA custom field info as it is returned from JIRA API
     * @return bool
     */
    public function isFieldSkipped(\stdClass $FieldInfo) : bool
    {
        $this->Logger->debug("  checking if {$FieldInfo->id} field is skipped");
        $field_id = $FieldInfo->id;
        if (isset($this->skip_fields[$field_id])) {
            $skip = $this->skip_fields[$field_id];

            $skipped = $skip ? 'skipped' : 'allowed';
            $this->Logger->debug("    field {$FieldInfo->id} is {$skipped} by ID");

            return $skip;
        }

        $field_type = $FieldInfo->schema->custom;
        if (isset($this->skip_types[$field_type])) {
            $skip = $this->skip_types[$field_type];

            $skipped = $skip ? 'skipped' : 'allowed';
            $this->Logger->debug("    field {$FieldInfo->id} is {$skipped} by type");

            return $skip;
        }

        foreach ($this->skip_patterns as $regex => $skip) {
            if (preg_match($regex, $field_type)) {
                $skipped = $skip ? 'skipped' : 'allowed';
                $this->Logger->debug("    field {$FieldInfo->id} is {$skipped} by pattern '{$regex}'");

                return $skip;
            }
        }

        $this->Logger->debug("    field {$FieldInfo->id} is not controlled by any rule");
        return false;
    }

    /**
     * Render field class using given template and class name
     *
     * @param \stdClass $FieldInfo      - field to generate class for
     * @param ITemplate|null $Template  - Template to use.
     *                                    Defaults to the one generator could find for field using configuration
     * @param string|null $class_name   - class name to use for field
     *                                    Defaults to the name generator could find in generated-field.lock or create by itself
     *
     * @return string - class definition
     */
    public function renderFieldClass(\stdClass $FieldInfo, ITemplate $Template = null, string $class_name = null) : string
    {
        $this->loadGeneratedFieldsInfo();

        $this->Logger->debug("  rendering {$FieldInfo->id} class...");

        if (!isset($Template)) {
            $Template = $this->getTemplateFor($FieldInfo);
        }
        if (!isset($class_name)) {
            $class_name = $this->getFieldClass($FieldInfo);
        }

        if (!isset($Template)) {
            $this->Logger->critical(
                "No template found for '{$FieldInfo->name}' ({$FieldInfo->id}) field. Can't render class"
            );

            throw new \RuntimeException(
                "No template found for '{$FieldInfo->name}' ({$FieldInfo->id}) field. Can't render class"
            );
        }

        $full_class_name = $class_name;
        if ($this->target_namespace) {
            $full_class_name = "{$this->target_namespace}\\$class_name";
        }
        $this->Logger->debug("   full class name for field {$FieldInfo->id} (with namespace): {$full_class_name}");

        return $Template->render($FieldInfo, $full_class_name);
    }

    /**
     * Generate single field class without any checks, put the contents to the corresponding target file
     * according to generator configuration.
     *
     * @param \stdClass $FieldInfo
     * @param ITemplate|null $Template
     * @param string|null $class_name
     *
     * @return bool - true - field was successfully generated
     *                false - renderer returned an empty string instead of class definition
     */
    public function generateField(\stdClass $FieldInfo, ITemplate $Template = null, string $class_name = null) : bool
    {
        $this->loadGeneratedFieldsInfo();

        if (!isset($class_name)) {
            $class_name = $this->getFieldClass($FieldInfo);
        }

        $class_file = $this->getTargetPath($class_name . '.php');
        $this->Logger->debug("  going to save class for field {$FieldInfo->id} to '$class_file'");

        $class_definition = $this->renderFieldClass($FieldInfo, $Template, $class_name);

        if (empty($class_definition)) {
            $this->Logger->error(
                "Template generated empty class for '{$FieldInfo->name}' ({$FieldInfo->id}) field"
            );
            return false;
        }

        $this->Logger->debug("  saving class for {$FieldInfo->id} to {$class_file}...");
        \Badoo\Jira\Helpers\Files::filePutContents($class_file, $class_definition);

        $this->markGenerated($FieldInfo, $class_name);

        $this->saveGeneratedFieldsInfo();
        return true;
    }

    /**
     * Generate classes for all fields we can
     *
     * Class will not be generated in next cases:
     *  - field is marked as 'skipped', @see Generator::skipField(), Generator::skipType()
     *  - no template found for field, @see Generator::mapFieldToTemplate(), Generator::mapTypeToTemplate()
     *  - template returned empty string for field after rendering
     *  - field has class name collision with another field
     *
     * @return bool - true when all fields generator _tried to generate_ (not skipped) were generated:
     *                  there were no collisions, no empty classes and so on. In other words - you have no errors in log
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function generateAll() : bool
    {
        $this->loadGeneratedFieldsInfo();

        $all_ok = true;

        $this->Logger->debug('Loading list of custom fields from JIRA...');
        $fields = $this->Jira->field()->listCustom(true);
        $this->Logger->debug('...loaded ' . count($fields) . ' fields');

        $this->Logger->debug('Generating classes...');
        foreach ($fields as $FieldInfo) {
            $this->Logger->debug("  treating field " . json_encode($FieldInfo));

            if ($this->isFieldSkipped($FieldInfo)) {
                $this->Logger->info("Field '{$FieldInfo->name}' ({$FieldInfo->id}) is skipped");
                continue;
            }

            $class_name = $this->getFieldClass($FieldInfo);
            $this->Logger->debug("  field class for {$FieldInfo->id}} is '{$class_name}'");

            if ($this->hasCollision($class_name, $FieldInfo)) {
                $collision = $this->generated_classes[$class_name];
                $this->Logger->error(
                    "Field '{$FieldInfo->name}' ({$FieldInfo->id}) collided with '{$collision['field_name']}'" .
                    " ({$collision['field_id']}) in class '{$class_name}'"
                );
                $all_ok = false;
                continue;
            }

            $Template = $this->getTemplateFor($FieldInfo);

            if (!isset($Template)) {
                $this->Logger->error(
                    "Template for '{$FieldInfo->name}' ({$FieldInfo->id}) not found\n" .
                    "\tfield type: {$FieldInfo->schema->custom}"
                );
                $all_ok = false;
                continue;
            }

            $class_file = $this->getTargetPath($class_name . '.php');
            $this->Logger->debug("  going to save class for field {$FieldInfo->id} to '$class_file'");

            if ($this->isNotGeneratedFileWrite($class_name, $class_file)) {
                $this->Logger->error(
                    "File {$class_file} exists on disk and was not initially generated by Generator." .
                    " Can't write '{$FieldInfo->name}' ({$FieldInfo->id}) field class there." .
                    " Remove file manually if you want generator to write new class into it"
                );
                $all_ok = false;
                continue;
            }

            $class_definition = $this->renderFieldClass($FieldInfo, $Template, $class_name);

            if (empty($class_definition)) {
                $this->Logger->error(
                    "Template generated empty class for '{$FieldInfo->name}' ({$FieldInfo->id}) field"
                );
                $all_ok = false;
                continue;
            }

            $this->Logger->debug("  saving class for {$FieldInfo->id} to {$class_file}...");
            \Badoo\Jira\Helpers\Files::filePutContents($class_file, $class_definition);
            $this->markGenerated($FieldInfo, $class_name);
        }

        $this->saveGeneratedFieldsInfo();

        return $all_ok;
    }
}
