<?php
/**
 * @package REST
 */

namespace Badoo\Jira\CustomFields;

class LabelField extends CustomField
{
    /**
     * @inheritDoc
     * @return array
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getValue()
    {
        return (array)$this->getOriginalObject();
    }

    /**
     * @inheritDoc
     * @param $value array|null
     */
    public static function generateSetter($value): array
    {
        if (!\is_array($value)) {
            $value = [$value];
        }
        return [['set' => $value]];
    }

    /**
     * @param string $label
     * @return $this
     */
    public function addLabel(string $label): self
    {
        $this->getIssue()->edit($this->getID(), [['add' => $label]]);
        return $this;
    }

    /**
     * @param array $labels
     * @return $this
     */
    public function addLabels(array $labels): self
    {
        $updates = [];
        foreach ($labels as $label) {
            $updates[] = ['add' => $label];
        }
        $this->getIssue()->edit($this->getID(), $updates);
        return $this;
    }

    /**
     * @param array $labels
     * @return $this
     */
    public function setLabels(array $labels): self
    {
        $this->getIssue()->edit($this->getID(), self::generateSetter(\array_filter(\array_unique($labels))));
        return $this;
    }
}