<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST;

class Client extends \Badoo\Jira\REST\Section\Section
{
    /**
     * @var static|null
     */
    protected static $instance = null;

    /**
     * @return static
     */
    public static function instance() : Client
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Client constructor.
     * @param string $jira_url
     * @param string $api_prefix
     */
    public function __construct(
        $jira_url   = ClientRaw::DEFAULT_JIRA_URL,
        $api_prefix = ClientRaw::DEFAULT_JIRA_API_PREFIX
    ) {
        $Jira = ClientRaw::instance()
            ->setJiraUrl($jira_url)
            ->setApiPrefix($api_prefix);
        parent::__construct($Jira);
    }

    /**
     * @return ClientRaw
     */
    public function getRawClient() : ClientRaw
    {
        return $this->Jira;
    }

    /**
     * @param string $url
     * @return static
     */
    public function setJiraUrl(string $url) : Client
    {
        $this->getRawClient()->setJiraUrl($url);
        return $this;
    }

    /**
     * @param string $login
     * @param string $secret
     * @return static
     */
    public function setAuth(string $login, string $secret) : Client
    {
        $this->getRawClient()->setAuth($login, $secret);
        return $this;
    }

    /**
     * @return \Badoo\Jira\REST\Section\Jql
     */
    public function jql() : \Badoo\Jira\REST\Section\Jql
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('jql', \Badoo\Jira\REST\Section\Jql::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\Attachment
     */
    public function attachment() : \Badoo\Jira\REST\Section\Attachment
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('attachment', \Badoo\Jira\REST\Section\Attachment::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\Project
     */
    public function project() : \Badoo\Jira\REST\Section\Project
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('project', \Badoo\Jira\REST\Section\Project::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\Component
     */
    public function component() : \Badoo\Jira\REST\Section\Component
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('component', \Badoo\Jira\REST\Section\Component::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\Issue
     */
    public function issue() : \Badoo\Jira\REST\Section\Issue
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issue', \Badoo\Jira\REST\Section\Issue::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\IssueType
     */
    public function issueType() : \Badoo\Jira\REST\Section\IssueType
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuetype', \Badoo\Jira\REST\Section\IssueType::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\IssueLink
     */
    public function issueLink() : \Badoo\Jira\REST\Section\IssueLink
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuelink', \Badoo\Jira\REST\Section\IssueLink::class);
    }

    /**
     * @return \Badoo\Jira\REST\Section\IssueLinkType
     */
    public function issueLinkType() : \Badoo\Jira\REST\Section\IssueLinkType
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuelinktype', \Badoo\Jira\REST\Section\IssueLinkType::class);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/search-search
     *
     * Search for issues using JQL.
     *
     * @param string    $jql
     * @param string[]  $fields
     * @param string[]  $expand
     * @param int       $max_results
     * @param int       $start_at
     * @param bool      $validate_query
     *
     * @return array - API response, parsed as JSON. You need to use 'issues' key to get actual list of issues from response
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function search(string $jql, $fields = [], $expand = [], int $max_results = 50, int $start_at = 0, $validate_query = true)
    {
        $args = [
            'jql'           => $jql,
            'startAt'       => $start_at,
            'maxResults'    => $max_results,
            'validateQuery' => $validate_query,
        ];

        if (!empty($fields)) {
            $args['fields'] = $fields;
        }
        if (!empty($expand)) {
            $args['expand'] = $expand;
        }

        return $this->Jira->post('/search', $args);
    }

    /**
     * Get interface for operations with Jira issue fields
     */
    public function field() : \Badoo\Jira\REST\Section\Field
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('field', \Badoo\Jira\REST\Section\Field::class);
    }

    /**
     * Get interface for operations with Jira resolutions
     */
    public function resolution() : \Badoo\Jira\REST\Section\Resolution
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('resolution', \Badoo\Jira\REST\Section\Resolution::class);
    }

    /**
     * Get interface for operations with JIRA users
     */
    public function user() : \Badoo\Jira\REST\Section\User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('user', \Badoo\Jira\REST\Section\User::class);
    }

    /**
     * Get interface for operations with JIRA user groups
     */
    public function group() : \Badoo\Jira\REST\Section\Group
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('group', \Badoo\Jira\REST\Section\Group::class);
    }

    /**
     * Get interface for operations with JIRA issue security levels
     */
    public function securityLevel() : \Badoo\Jira\REST\Section\SecurityLevel
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('securitylevel', \Badoo\Jira\REST\Section\SecurityLevel::class);
    }

    /**
     * Get interface for operations with JIRA issue priorities
     */
    public function priority() : \Badoo\Jira\REST\Section\Priority
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('priority', \Badoo\Jira\REST\Section\Priority::class);
    }

    /**
     * Get interface for operations with JIRA status categories
     */
    public function statusCategory() : \Badoo\Jira\REST\Section\StatusCategory
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('statuscategory', \Badoo\Jira\REST\Section\StatusCategory::class);
    }

    /**
     * Get interface for operations with JIRA statuses
     */
    public function status() : \Badoo\Jira\REST\Section\Status
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('status', \Badoo\Jira\REST\Section\Status::class);
    }

    public function version() : \Badoo\Jira\REST\Section\Version
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('version', \Badoo\Jira\REST\Section\Version::class);
    }
}
