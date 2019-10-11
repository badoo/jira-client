<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class IssueTransitions extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getTransitions
     *
     * List all transitions of given issue available for current user.
     *
     * @param string $issue_key - list transitions for this issue
     * @param bool $expand_fields - provide list of fields available on transition screen.
     *                              This fields you can set during a transition.
     *
     * @return \stdClass[] - list of transitions available
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(string $issue_key, bool $expand_fields = false) : array
    {
        $args = [];

        if ($expand_fields) {
            $args = ['expand' => 'transitions.fields'];
        }

        return $this->Jira->get("/issue/{$issue_key}/transitions", $args)->transitions;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getTransitions
     *
     * Get information on particular issue transition
     *
     * @param string $issue_key - list transitions for this issue
     * @param int $transition_id - get this transition info only
     * @param bool $expand_fields - provide list of fields available on transition screen.
     *                              This fields you can set during a transition.
     *
     * @return \stdClass - info for transition with given ID
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $issue_key, int $transition_id, bool $expand_fields = false) : \stdClass
    {
        $args = [
            'transitionId' => $transition_id,
        ];

        if ($expand_fields) {
            $args = ['expand' => 'transitions.fields'];
        }

        $transitions = $this->Jira->get("/issue/{$issue_key}/transitions", $args)->transitions;

        if (empty($transitions)) {
            $user = $this->Jira->getLogin();
            throw new \Badoo\Jira\REST\Exception(
                "Transition '{$transition_id}' of '{$issue_key}' is not available for '{$user}' in current issue status"
            );
        }

        return $transitions[0];
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-doTransition
     *
     * Perform transition for issue.
     *
     * @see \Badoo\Jira\REST\Section\Issue::edit DocBlock for parameters description
     *
     * @param string $issue_key     - perform transition for this issue.
     * @param int    $transition_id - unique transition numeric ID.
     * @param array  $fields        - this parameter has the same meaning as in Issue->edit()
     * @param array  $update        - this parameter has the same meaning as in Issue->edit()
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function do(string $issue_key, int $transition_id, array $fields = [], array $update = []) : void
    {
        $args = [
            'transition' => ['id' => $transition_id]
        ];

        if (!empty($fields)) {
            $args['fields'] = $fields;
        }

        if (!empty($update)) {
            $args['update'] = $update;
        }

        $this->Jira->post("issue/{$issue_key}/transitions", $args);
    }

    /**
     * Perform a transition, but silently filter all fields that are not settable during the transition.
     * This allows to change issue status even when you have excess fields in you can't set because of transition
     * screen configuration
     *
     * NOTE: this is synthetic method. JIRA API has no appropriate method for ignoring fields that can't be set.
     *
     * @see IssueTransitions::do() for parameters description
     *
     * @param string $issue_key
     * @param int $transition_id
     * @param array $fields
     * @param array $update
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function do_safe(string $issue_key, int $transition_id, array $fields = [], array $update = []) : void
    {
        $TransitionInfo = $this->get($issue_key, $transition_id, true);

        foreach ($fields as $field_id => $value) {
            if (!isset($TransitionInfo->fields->{$field_id})) {
                unset($fields[$field_id]);
            }
        }

        foreach ($update as $field_id => $value) {
            if (!isset($TransitionInfo->fields->{$field_id})) {
                unset($update[$field_id]);
            }
        }

        $this->do($issue_key, $transition_id, $fields, $update);
    }

    /**
     * Perform a transition on issue by step name instead of ID (e.g. by text shown on button in Jira Web UI)
     *
     * NOTE: this is synthetic method. JIRA API has no appropriate method for ignoring fields that can't be set.
     *
     * @see \Badoo\Jira\REST\Section\Issue::edit DocBlock for parameters description
     *
     * @param string $issue_key - key of issue to be progressed
     * @param string $step_name - transition (step) name. E.g. the text of a button in Jira Web interface.
     * @param array  $fields - this parameter has the same meaning as in Issue->edit()
     * @param array  $update - this parameter has the same meaning as in Issue->edit()
     * @param bool   $step_same_status - perform transition even if it leads to the same issue status (e.g. from Open to Open)
     * @param bool   $use_do_safe - use ::do_safe method, filter out fields that can't be set during transition.
     *
     * @throws \Badoo\Jira\REST\Exception
     *
     */
    public function step(string $issue_key, string $step_name, array $fields = [], array $update = [], $use_do_safe = false, bool $step_same_status = false) : void
    {
        $IssueInfo = $this->Jira->get("/issue/{$issue_key}", ['fields' => 'status']);

        $issue_status_id = $IssueInfo->fields->status->id;

        $available_transitions = $this->list($issue_key);

        $transition_names = [];
        foreach ($available_transitions as $TransitionInfo) {
            $transition_names[] = $TransitionInfo->name;

            if ($TransitionInfo->name === $step_name) {
                $target_status_id   = $TransitionInfo->to->id;
                $target_status_name = $TransitionInfo->to->name;

                if ($issue_status_id != $target_status_id || $step_same_status) {
                    if ($use_do_safe) {
                        $this->do_safe($IssueInfo->key, $TransitionInfo->id, $fields, $update);
                    } else {
                        $this->do($IssueInfo->key, $TransitionInfo->id, $fields, $update);
                    }
                    return;
                }

                throw new \Badoo\Jira\REST\Exception(
                    "Issue '{$IssueInfo->key}' is already in '{$target_status_name}' status"
                );
            }
        }

        throw new \Badoo\Jira\REST\Exception(
            "Can't make '{$step_name}' step for issue '{$IssueInfo->key}' in status '{$IssueInfo->fields->status->name}'."
            . " Workflow Transition with name '{$step_name}' is not available in this status."
            . " List of issue steps available in current status: '" . implode("', '", $transition_names) . "'"
        );
    }
}
