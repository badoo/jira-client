<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira;

class UsersList
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    /** @var User[] */
    protected $by_name = [];
    /** @var User[] */
    protected $by_email = [];

    public static function fromStdClass(array $users_info, \Badoo\Jira\Issue $Issue = null, \Badoo\Jira\REST\Client $Jira = null) : UsersList
    {
        $users = [];
        foreach ($users_info as $UserInfo) {
            $users[] = User::fromStdClass($UserInfo, $Issue, $Jira);
        }

        $Instance = new static($users, $Jira);
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * @param User[] $users                 - users in list
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     */
    public function __construct(array $users, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->addUsers(...$users);
        $this->Jira = $Jira;
    }

    /**
     * Clear list from all users
     *
     * @return $this
     */
    public function clearList() : UsersList
    {
        $this->by_name = [];
        $this->by_email = [];

        return $this;
    }

    /**
     * Get full list of users
     *
     * @return User[]
     */
    public function getUsers() : array
    {
        return $this->by_name ?? [];
    }

    /**
     * Add user to list
     *
     * @param User ...$users
     *
     * @return $this
     */
    public function addUsers(User ...$users) : UsersList
    {
        foreach ($users as $User) {
            $this->by_name[$User->getName()] = $User;
            $this->by_email[$User->getEmail()] = $User;
        }
        return $this;
    }

    /**
     * @param User ...$users
     * @return UsersList
     */
    public function removeUsers(User ...$users) : UsersList
    {
        foreach ($users as $User) {
            // Check if we have user in caches. If not - it is better not to call ->getEmail or other methods to not
            // trigger useless background API requests under $User object.
            if ($this->hasName($User->getName())) {
                unset($this->by_name[$User->getName()]);
                unset($this->by_email[$User->getEmail()]);
            }
        }

        return $this;
    }

    public function hasName(string $name) : bool
    {
        return isset($this->by_name[$name]);
    }

    public function hasEmail(string $email) : bool
    {
        return isset($this->by_email[$email]);
    }
}
