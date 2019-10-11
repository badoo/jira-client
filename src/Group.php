<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira;

class Group
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var string */
    protected $name;    // Developers

    /** @var string */
    protected $self;    // https://<jira host>/rest/api/2/group?groupname=Developers"

    /** @var \Badoo\Jira\User[] */
    protected $users;

    public static function fromStdClass(\stdClass $GroupInfo, \Badoo\Jira\REST\Client $Jira = null) : Group
    {
        $Instance = new static($GroupInfo->name, $Jira);
        $Instance->init($GroupInfo);

        return $Instance;
    }

    public function __construct(string $name, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->name = $name;
        $this->Jira = $Jira;
    }

    public function __toString()
    {
        return $this->getName();
    }

    protected function init(\stdClass $GroupInfo)
    {
        $this->OriginalObject = $GroupInfo;

        $this->name = $GroupInfo->name;
        $this->self = $GroupInfo->self;
    }

    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Get all users in group
     * WARNING: for large groups can take long time
     *
     * @see \Badoo\Jira\REST\Section\Group::listAllUsers for more information
     */
    public function getAllUsers() : array
    {
        if (!isset($this->users)) {
            $users = $this->Jira->group()->listAllUsers($this->getName());

            $this->users = [];
            foreach ($users as $UserInfo) {
                $User = \Badoo\Jira\User::fromStdClass($UserInfo);
                $this->users[$User->getName()] = $User;
            }
        }

        return $this->users;
    }
}
