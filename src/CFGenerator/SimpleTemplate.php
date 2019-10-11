<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CFGenerator;

class SimpleTemplate implements ITemplate
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var string */
    protected $name;
    protected $load_options = false;

    /** @var string */
    protected $template_path;

    public function __construct(string $name, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->name = $name;
        $this->Jira = $Jira;
    }

    protected function quoteOptionValue(string $value) : string
    {
        // drop quotes: '"Value\\With\\'slashes"' => 'Value\\With\\'slashes'
        $value = trim($value, '"');

        // drop backslashes (just to look nicer) 'Value\\With\\'slashes' => 'Value\With\'slashes'
        $value = str_replace("\\\\", "\\", $value);

        // escape single quotes: 'Value\With\'slashes' => 'Value\With\\'slashes'
        $value = str_replace("'", "\'", $value);

        // handle edge case of initial value: backslash followed by single qoute
        $value = str_replace("\\\\'", "\\\\\'", $value);

        return $value;
    }

    public function setTemplatePath(string $path) : SimpleTemplate
    {
        $this->template_path = $path;

        if (!\Badoo\Jira\Helpers\Files::exists($path)) {
            trigger_error(
                "Template file '{$path}' not found for template '{$this->name}'",
                E_USER_WARNING
            );
        }

        return $this;
    }

    public function setLoadOptions(bool $load_options = true) : SimpleTemplate
    {
        $this->load_options = $load_options;
        return $this;
    }

    public function render(\stdClass $FieldInfo, string $full_class_name) : string
    {
        $options = [];
        if ($this->load_options) {
            foreach ($this->Jira->jql()->getFieldSuggestions($FieldInfo->name) as $OptionInfo) {
                $capital_name = \Badoo\Jira\Helpers\Strings::toCapitalPHPLabel($OptionInfo->value);

                $options[$OptionInfo->value] = [
                    'capital_name'   => $capital_name,
                    'camelcase_name' => \Badoo\Jira\Helpers\Strings::toCamelCasePHPLabel($OptionInfo->value),
                    'const_name'     => 'VALUE_' . ltrim($capital_name, '_'),
                    'value'          => $this->quoteOptionValue($OptionInfo->value),
                ];
            }
        }

        $class_parts    = explode('\\', $full_class_name);
        $class_name     = array_pop($class_parts);
        $namespace      = implode('\\', $class_parts);

        try {
            return $this->renderTemplate(
                $this->template_path,
                [
                    'namespace'     => $namespace,
                    'class_name'    => $class_name,
                    'field_id'      => $FieldInfo->id,
                    'field_name'    => $FieldInfo->name,
                    'options'       => $options,
                ]
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to render template {$this->name} for field {$FieldInfo->id}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    protected function renderTemplate(string $template_path, array $params)
    {
        ob_start();

        extract($params);
        /** @noinspection PhpIncludeInspection */
        include $template_path;

        return ob_get_clean();
    }
}
