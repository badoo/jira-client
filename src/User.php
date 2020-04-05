<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira;

class User
{
    const AVATAR_L      = '48x48';
    const AVATAR_M      = '32x32';
    const AVATAR_S      = '24x24';
    const AVATAR_XS     = '16x16';

    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;
    /** @var string[] */
    protected $expanded = [];

    /** @var string */
    protected $name;

    /** @var \Badoo\Jira\Group[] */
    protected $groups;
    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    /**
     * Initialize User object on data from API
     *
     * @param \stdClass $UserInfo - user information received from JIRA API.
     * @param \Badoo\Jira\Issue $Issue - when current user somehow related to an issue: e.g. is Assignee or is listed
     *                                   in some custom field.
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     */
    public static function fromStdClass(
        \stdClass $UserInfo,
        \Badoo\Jira\Issue $Issue = null,
        \Badoo\Jira\REST\Client $Jira = null
    ) : User {
        $Instance = new static($UserInfo->name ?? $UserInfo->accountId, $Jira);
        $Instance->Issue = $Issue;
        $Instance->OriginalObject = $UserInfo;

        return $Instance;
    }

    /**
     * Get user from API by ID.
     *
     * This method makes an API request immediately, while
     *     $User = new User(<name>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call $User->getDisplayName()).
     *
     * @param string $user_name - name of user in JIRA. Don't mess with display name you see in UI!
     * @param \Badoo\Jira\REST\Client $Jira
     *
     * @return static
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function get(string $user_name, \Badoo\Jira\REST\Client $Jira = null) : User
    {
        $Instance = new static($user_name, $Jira);
        $Instance->getOriginalObject();

        return $Instance;
    }

    /**
     * Search for users by login. display name or email.
     * This gives you a result similar to the one you get in 'Uses' administration page of JIRA Web UI.
     *
     * @param string $pattern - user login, display name or email
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function search(string $pattern, \Badoo\Jira\REST\Client $Jira = null) : array
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $users = $Jira->user()->search($pattern);

        $result = [];
        foreach ($users as $UserInfo) {
            $User = static::fromStdClass($UserInfo, null, $Jira);
            $result[$User->getName()] = $User;
        }

        return $result;
    }

    /**
     * Search for user by exact match in email address
     *
     * @param string $email - user email
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Badoo\Jira\REST\Exception - on JIRA API interaction errors
     * @throws \Badoo\Jira\Exception\User - when no user with given email found in JIRA
     */
    public static function byEmail(string $email, \Badoo\Jira\REST\Client $Jira = null) : User
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $users = $Jira->user()->search($email);

        foreach ($users as $UserInfo) {
            if ($UserInfo->emailAddress === $email) {
                return static::fromStdClass($UserInfo, null, $Jira);
            }
        }

        throw new \Badoo\Jira\Exception\User(
            "User with email '{$email}' not found in Jira"
        );
    }

    /**
     * User constructor.
     *
     * @param string $name - name of user in JIRA. Don't mess with display name you see in UI!
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     */
    public function __construct(string $name, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->name = $name;
        $this->Jira = $Jira;
    }

    /**
     * @param string[] $expand - ask JIRA to provide additional information in response
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getOriginalObject(array $expand = []) : \stdClass
    {
        $new_expand = false;
        foreach ($expand as $item) {
            if (!array_key_exists($item, $this->expanded)) {
                $this->expanded[$item] = null;
                $new_expand = true;
            }
        }

        if (!isset($this->OriginalObject) || $new_expand) {
            $this->OriginalObject = $this->Jira->user()->get($this->getName(), array_keys($this->expanded), true);
            $this->groups = null;
        }

        return $this->OriginalObject;
    }

    public function __toString()
    {
        return "{$this->getDisplayName()} ({$this->getName()})";
    }

    public function getKey() : string
    {
        return $this->getOriginalObject()->key;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getDisplayName() : string
    {
        return $this->getOriginalObject()->displayName;
    }

    public function getEmail() : string
    {
        // Email field can be omitted in some JIRA API responses (e.g. in components)
        if (!isset($this->getOriginalObject()->emailAddress)) {
            $this->OriginalObject = null; // drop cache to force data reload
        }

        return (string)$this->getOriginalObject()->emailAddress;
    }

    public function isActive() : bool
    {
        return $this->getOriginalObject()->active;
    }

    /**
     * Check if user belongs to at least one of listed groups
     *
     * @param string|string[] $group_names
     *
     * @return bool - true when user is member of any of given groups
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function isMemberOf($group_names) : bool
    {
        $group_names = (array)$group_names;
        foreach ($this->getGroups() as $Group) {
            if (in_array($Group->getName(), $group_names)) {
                return true;
            }
        }

        return false;
    }

    /**
     * List all groups user is member of
     *
     * @return \Badoo\Jira\Group[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getGroups() : array
    {
        // Groups list can be omitted in some JIRA API responses (e.g. in comments)
        if (!isset($this->groups)) {
            $UserInfo = $this->getOriginalObject(['groups']); // force user data reload to initialize groups list

            $this->groups = [];
            if (isset($UserInfo->groups)) {
                foreach ($UserInfo->groups->items as $GroupInfo) {
                    $Group = Group::fromStdClass($GroupInfo, $this->Jira);
                    $this->groups[$Group->getName()] = $Group;
                }
            }
        }

        return $this->groups;
    }

    /**
     * Start or stop watching issue (add/remove myself from issue's watchers list)
     *
     * @param string $issue_key - key of issue to start watching
     * @param bool $watch - should current user watch the issue?
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function watchIssue(string $issue_key, bool $watch = true) : User
    {
        if ($watch) {
            $this->Jira->issue()->watchers()->add($issue_key, $this->getName());
        } else {
            $this->Jira->issue()->watchers()->remove($issue_key, $this->getName());
        }
        return $this;
    }

    /**
     * Assign issue to current user.
     * NOTE: action is applied immediately (causes API call)
     *
     * @param string $issue_key
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function assign(string $issue_key) : User
    {
        $this->Jira->issue()->assign($issue_key, $this->getName());
        return $this;
    }
}
