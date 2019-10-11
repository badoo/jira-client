<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class WatchersList extends \Badoo\Jira\UsersList
{
    protected $initialized = false;
    protected $loaded = false;

    /**
     * @param array $users_info
     * @param \Badoo\Jira\Issue $Issue
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Badoo\Jira\Exception
     */
    public static function fromStdClass(array $users_info, \Badoo\Jira\Issue $Issue = null, \Badoo\Jira\REST\Client $Jira = null) : \Badoo\Jira\UsersList
    {
        if (!isset($Issue)) {
            throw new \Badoo\Jira\Exception("Watchers list requires parent Issue object to work properly");
        }

        $users = [];
        foreach ($users_info as $UserInfo) {
            $users[] = \Badoo\Jira\User::fromStdClass($UserInfo, $Issue, $Jira);
        }

        $Instance = new static($users, $Jira);
        $Instance->Issue = $Issue;
        $Instance->initialized = true;

        return $Instance;
    }

    /**
     * @param string $issue_key
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return WatchersList
     *
     * @throws \Badoo\Jira\Exception
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function forIssue(string $issue_key, \Badoo\Jira\REST\Client $Jira = null) : WatchersList
    {
        $Issue = new \Badoo\Jira\Issue($issue_key, $Jira);
        return $Issue->getWatchers();
    }

    /**
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function clearList() : \Badoo\Jira\UsersList
    {
        if ($this->initialized) {
            foreach ($this->getUsers() as $User) {
                $User->watchIssue($this->Issue->getKey(), false);
            };
        }

        $this->loaded = false;
        return \Badoo\Jira\UsersList::clearList();
    }

    /**
     * @return \Badoo\Jira\User[]
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getUsers() : array
    {
        if (!$this->loaded) {
            $watchers = $this->Jira->issue()->watchers()->list($this->Issue->getKey());

            foreach ($watchers as $UserInfo) {
                $Watcher = \Badoo\Jira\User::fromStdClass($UserInfo, $this->Issue, $this->Jira);
                parent::addUsers($Watcher);
            }

            $this->loaded = true;
        }

        return parent::getUsers();
    }

    /**
     * Add user to list of issue's watchers, using user's name (login)
     *
     * @param string ...$names
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function addUsersByName(string ...$names) : WatchersList
    {
        $users = [];
        foreach ($names as $name) {
            $users[] = new \Badoo\Jira\User($name, $this->Jira);
        }
        return $this->addUsers(...$users);
    }

    /**
     * Add user to list of issue's watchers
     *
     * @param \Badoo\Jira\User ...$users
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function addUsers(\Badoo\Jira\User ...$users) : \Badoo\Jira\UsersList
    {
        if ($this->initialized) {
            foreach ($users as $User) {
                $User->watchIssue($this->Issue->getKey());
            }
        }

        return \Badoo\Jira\UsersList::addUsers(...$users);
    }

    /**
     * Remove user from list of issue's watchers, using user's name (login)
     *
     * @param string ...$names
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function removeUsersByName(string ...$names) : WatchersList
    {
        $users = [];
        foreach ($names as $name) {
            $users[] = new \Badoo\Jira\User($name, $this->Jira);
        }
        ;
        return $this->removeUsers($users);
    }

    /**
     * Remove user from list of issue's watchers
     *
     * @param \Badoo\Jira\User ...$users
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function removeUsers(\Badoo\Jira\User ...$users) : \Badoo\Jira\UsersList
    {
        if ($this->initialized) {
            foreach ($users as $User) {
                $User->watchIssue($this->Issue->getKey(), false);
            }
        }

        return \Badoo\Jira\UsersList::removeUsers(...$users);
    }
}
