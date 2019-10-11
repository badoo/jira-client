<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Watchers extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getIssueWatchers
     *
     * List issue watchers
     *
     * @param string $issue_key
     * @return \stdClass[] - list of <Jira user info> objects
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(string $issue_key)
    {
        $response = $this->Jira->get("issue/{$issue_key}/watchers");

        if (!isset($response->watchers)) {
            return [];
        }

        return $response->watchers;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-addWatcher
     *
     * Add watcher to issue
     *
     * @param string $issue_key
     * @param string $user_login
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function add(string $issue_key, string $user_login) : void
    {
        $this->Jira->post("issue/{$issue_key}/watchers", $user_login);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-removeWatcher
     *
     * Stop watching issue
     *
     * @param string $issue_key
     * @param string $user_login
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function remove(string $issue_key, string $user_login) : void
    {
        $this->Jira->delete("issue/{$issue_key}/watchers", ['username' => $user_login]);
    }
}
