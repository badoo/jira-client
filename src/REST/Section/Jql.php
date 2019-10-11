<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Jql extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/jql/autocompletedata-getFieldAutoCompleteForQueryString
     *
     * @param string $field_name      - list possible values for field
     * @param string $field_value     - list only values starting with this text
     * @param string $predicate_name  - see API Web documentation
     * @param string $predicate_value - see API Web documentation
     *
     * @return array
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getFieldSuggestions(
        string $field_name,
        string $field_value = '',
        string $predicate_name = '',
        string $predicate_value = ''
    ) {
        $parameters = [
            'fieldName' => $field_name,
        ];

        if (!empty($field_value)) {
            $parameters['fieldValue'] = $field_value;
        }

        if (!empty($predicate_name)) {
            $parameters['predicateName'] = $predicate_name;
        }

        if (!empty($predicate_value)) {
            $parameters['predicateValue'] = $predicate_value;
        }

        $Response = $this->Jira->get('jql/autocompletedata/suggestions', $parameters);
        return $Response->results;
    }
}
