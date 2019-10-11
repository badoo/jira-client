<?= "<?php\n" ?>
/**
 * This is a generated wrapper class for JIRA custom field '<?= $field_name ?>'
 */
<?php
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}
?>

class <?= $class_name ?> extends \Badoo\Jira\CustomFields\SingleSelectField
{
    const ID    = '<?= $field_id ?>';
    const NAME  = '<?= $field_name ?>';

    /* Available field values. */
<?php foreach ($options as $option): ?>
    const <?= $option['const_name'] ?> = '<?= $option['value'] ?>';
<?php endforeach ?>

    const VALUES = [
<?php foreach ($options as $option): ?>
        self::<?= $option['const_name'] ?>,
<?php endforeach ?>
    ];

    public function getItemsList() : array
    {
        return static::VALUES;
    }
<?php
    $yes_opt = $options['yes'] ?? $options['Yes'] ?? $options['YES'] ?? null;

    if (isset($yes_opt)):
?>

    public function isYes() : bool
    {
        return $this->getValue() === self::<?= $yes_opt['const_name'] ?>;
    }
<?php endif ?>
<?php
    $no_opt = $options['no'] ?? $options['No'] ?? $options['NO'] ?? null;
    if (isset($no_opt)):
?>

    public function isNo() : bool
    {
        return $this->getValue() === self::<?= $no_opt['const_name'] ?>;
    }
<?php endif ?>
}
