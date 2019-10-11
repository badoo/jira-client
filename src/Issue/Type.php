<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class Type
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var \Badoo\Jira\Issue */
    private $Issue;

    /**
     * Initialize Type object on data obtained from API
     *
     * @param \stdClass $TypeInfo           - issue type information received from JIRA API.
     * @param \Badoo\Jira\Issue $Issue      - when current Type object represents current type of some issue.
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     */
    public static function fromStdClass(
        \stdClass $TypeInfo,
        \Badoo\Jira\Issue $Issue = null,
        \Badoo\Jira\REST\Client $Jira = null
    ) : Type {
        $Instance = new static((int)$TypeInfo->id, $Jira);

        $Instance->OriginalObject = $TypeInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Type info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Type = new Type(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call $Type->getName()).
     *
     * @param int $id                       - ID of type you want to get
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function get(int $id, \Badoo\Jira\REST\Client $Jira = null)
    {
        $Instance = new static($id, $Jira);
        $Instance->getOriginalObject();

        return $Instance;
    }

    public function __construct(int $id, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->id = $id;
        $this->Jira = $Jira;
    }

    /**
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->issueType()->get($this->getId());
        }

        return $this->OriginalObject;
    }

    public function getIssue() : ?\Badoo\Jira\Issue
    {
        return $this->Issue;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getName() : string
    {
        return $this->getOriginalObject()->name;
    }

    public function getDescription() : string
    {
        return $this->getOriginalObject()->description ?? '';
    }

    public function isSubtask() : bool
    {
        return $this->getOriginalObject()->subtask ?? false;
    }

    public function getSelf() : string
    {
        return $this->getOriginalObject()->self;
    }

    public function getIconUrl() : string
    {
        return $this->getOriginalObject()->iconUrl ?? '';
    }

    public function getAvatarId() : string
    {
        return $this->getOriginalObject()->avatarId ?? '';
    }
}
