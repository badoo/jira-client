<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class Status
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var StatusCategory */
    protected $StatusCategory;

    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    /**
     * Initialize Status object on data loaded from API
     *
     * @param \stdClass $StatusInfo         - status information received from JIRA API.
     * @param \Badoo\Jira\Issue $Issue      - when current Status object represents current status of some issue.
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     * @return static
     */
    public static function fromStdClass(
        \stdClass $StatusInfo,
        \Badoo\Jira\Issue $Issue = null,
        \Badoo\Jira\REST\Client $Jira = null
    ) : Status {
        $Instance = new static((int)$StatusInfo->id, $Jira);

        $Instance->OriginalObject = $StatusInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Status info by ID.
     *
     * This method makes an API request immediately, while
     *     $Status = new Status(<id>, <Client);
     * requests JIRA only when you really need the data (e.g. the first time you call $Status->getName()).
     *
     * @param int $id - ID of status to get from API
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
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $StatusInfo = $Jira->status()->get($id);

        return static::fromStdClass($StatusInfo, null, $Jira);
    }

    public function __construct(int $id, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->id = $id;
        $this->Jira = $Jira;
    }

    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->status()->get($this->getId());
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

    public function getStatusCategory() : StatusCategory
    {
        if (!isset($this->StatusCategory)) {
            $this->StatusCategory = StatusCategory::fromStdClass($this->getOriginalObject()->statusCategory, $this->Jira);
        }

        return $this->StatusCategory;
    }

    public function getIconUrl() : string
    {
        return $this->getOriginalObject()->iconUrl;
    }

    public function getSelf() : string
    {
        return $this->getOriginalObject()->self;
    }
}
