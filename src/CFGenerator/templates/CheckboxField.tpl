<?= "<?php\n" ?>
/**
 * This is a generated wrapper class for JIRA custom field '<?= $field_name ?>'
 */
<?php
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}
?>

class <?= $class_name ?> extends \Badoo\Jira\CustomFields\CheckboxField
{
    /**
     * @deprecated use {@link getID getID} instead
     */
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

    public function getCheckboxesList() : array
    {
        return static::VALUES;
    }
}
