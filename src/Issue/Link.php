<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class Link
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var array */
    protected $cache = [];

    /**
     * Initialize Link object on data obtained from API
     *
     * @param \stdClass $LinkInfo           - issue link information received from JIRA API.
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     */
    public static function fromStdClass(\stdClass $LinkInfo, \Badoo\Jira\REST\Client $Jira = null) : Link
    {
        $Instance = new static($LinkInfo->id, $Jira);
        $Instance->OriginalObject = $LinkInfo;

        return $Instance;
    }

    /**
     * Issue link info, returned by JIRA API in issue fields has only one issue (inward or outward) information.
     * That is because the second end is always current issue itself.
     *
     * Because of this optimization we have to hack initializer to return second issue info without requesting
     * API once again.
     *
     * @param \stdClass $LinkInfo
     * @param \Badoo\Jira\Issue $Issue
     *
     * @return static
     *
     * @throws \Badoo\Jira\Exception\Link
     */
    public static function fromIssueField(\stdClass $LinkInfo, \Badoo\Jira\Issue $Issue) : Link
    {
        $Instance = static::fromStdClass($LinkInfo, $Issue->getJira());

        if (isset($LinkInfo->inwardIssue) && isset($LinkInfo->outwardIssue)) {
            throw new \Badoo\Jira\Exception\Link(
                "Wrong method usage. Both inward and outward issues defined in LinkInfo object. Use ::fromStdClass() method instead"
            );
        }

        if (isset($LinkInfo->inwardIssue)) {
            $cache_key = 'OutwardIssue';
        } else {
            $cache_key = 'InwardIssue';
        }

        $Instance->cache[$cache_key] = $Issue;

        return $Instance;
    }

    /**
     * Issue link info, returned by JIRA API in issue fields has only one issue (inward or outward) information.
     * That is because the second end is always current issue itself.
     *
     * Because of this optimization we have to hack initializer to return second issue info without requesting
     * API once again.
     *
     * @see \Badoo\Jira\REST\Section\IssueLink::create() for parameters description
     *
     * @param string $type
     * @param string $outward_issue
     * @param string $inward_issue
     * @param string $comment
     * @param array $visibility
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Badoo\Jira\Exception\Link
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function create(
        string $type,
        string $outward_issue,
        string $inward_issue,
        string $comment = '',
        array $visibility = [],
        \Badoo\Jira\REST\Client $Jira = null
    ) : Link {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $Jira->issueLink()->create($type, $outward_issue, $inward_issue, $comment, $visibility);

        // actualize key, we can't be sure issue was not renamed some time ago
        $inward_issue = $Jira->issue()->get($inward_issue, ['key'])->key;

        $links = $Jira->issueLink()->listForIssue($outward_issue, $type);
        foreach ($links as $LinkInfo) {
            if ($LinkInfo->inwardIssue->key === $inward_issue) {
                return static::get($LinkInfo->id, $Jira); // we have to get it because of half data in links info :(
            }
        }

        throw new \Badoo\Jira\Exception\Link(
            "Failed to create new link or load its info from API after creation"
        );
    }

    /**
     * Get Link info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Link = new Link(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call $Link->getName()).
     *
     * @param int $id                       - ID of link you want to get
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
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getOriginalObject() : \stdClass
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->issueLink()->get($this->id);
        }

        return $this->OriginalObject;
    }

    /**
     * @param \stdClass $IssueInfo
     * @return \Badoo\Jira\Issue
     */
    protected function getIssueFromLinkInfo(\stdClass $IssueInfo) : \Badoo\Jira\Issue
    {
        return \Badoo\Jira\Issue::fromStdClass(
            $IssueInfo,
            [
                'id',
                'key',
                'self',
                'summary',
                'status',
                'priority',
                'issuetype'
            ],
            [],
            $this->Jira
        );
    }

    /**
     * Drop internal object cache
     * @return $this
     */
    public function dropCache() : Link
    {
        $this->OriginalObject = null;
        $this->cache = [];

        return $this;
    }

    public function getId() : int
    {
        return $this->id;
    }

    /**
     * Get link type information
     *
     * @return LinkType
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getType() : LinkType
    {
        $Type = $this->cache['Type'] ?? null;
        if (!isset($Type)) {
            $Type = LinkType::fromStdClass($this->getOriginalObject()->type, $this->Jira);
            $this->cache['Type'] = $Type;
        }

        return $Type;
    }

    /**
     * Get inward issue of link
     *
     * @return \Badoo\Jira\Issue
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getInwardIssue()
    {
        $Issue = $this->cache['InwardIssue'] ?? null;
        if (!isset($Issue)) {
            $Issue = $this->getIssueFromLinkInfo($this->getOriginalObject()->inwardIssue);
            $this->cache['InwardIssue'] = $Issue;
        }

        return $Issue;
    }

    /**
     * Get outward issue of link
     *
     * @return \Badoo\Jira\Issue
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getOutwardIssue()
    {
        $Issue = $this->cache['OutwardIssue'] ?? null;
        if (!isset($Issue)) {
            $Issue = $this->getIssueFromLinkInfo($this->getOriginalObject()->outwardIssue);
            $this->cache['OutwardIssue'] = $Issue;
        }

        return $Issue;
    }

    public function delete() : Link
    {
        $this->Jira->issueLink()->delete($this->getId());

        return $this;
    }
}
