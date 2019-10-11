<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class Comment
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    /** @var int $id */
    protected $id;

    /** @var array */
    protected $cache = [];

    /**
     * Initialize Comment object on data obtained from API
     *
     * @param \stdClass $CommentInfo   - issue comment information received from JIRA API.
     * @param \Badoo\Jira\Issue $Issue - when current Comment object represents current comment of some issue.
     *
     * @return static
     */
    public static function fromStdClass(\stdClass $CommentInfo, \Badoo\Jira\Issue $Issue) : Comment
    {
        $Instance = new static($CommentInfo->id, $Issue);
        $Instance->OriginalObject = $CommentInfo;

        return $Instance;
    }

    /**
     * Get Comment info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Comment = new Comment(<id>, <Issue>);
     * requests JIRA only when you really need the data (e.g. the first time you call $Comment->getText()).
     *
     * @param int $id           - ID of comment you want to get
     * @param \Badoo\Jira\Issue - issue that contains the comment
     *
     * @return static
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function get(int $id, \Badoo\Jira\Issue $Issue)
    {
        $Instance = new static($id, $Issue);
        $Instance->getOriginalObject();

        return $Instance;
    }

    public function __construct(int $id, \Badoo\Jira\Issue $Issue)
    {
        $this->id = $id;
        $this->Issue = $Issue;
    }

    protected function getOriginalObject($expand_rendered = false) : \stdClass
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Issue->getJira()->issue()->comment()->get(
                $this->Issue->getKey(),
                $this->id,
                $expand_rendered
            );
        }

        return $this->OriginalObject;
    }

    protected function dropCache() : void
    {
        $this->OriginalObject = null;
        $this->cache = [];
    }

    public function getIssue() : \Badoo\Jira\Issue
    {
        return $this->Issue;
    }

    public function getID() : int
    {
        return $this->id;
    }

    public function getText() : string
    {
        return $this->getOriginalObject()->body;
    }

    public function getRendered() : string
    {
        if (isset($this->OriginalObject) && !isset($this->OriginalObject->renderedBody)) {
            $this->dropCache();
        }

        return $this->getOriginalObject(true)->renderedBody;
    }

    public function getCreated() : int
    {
        $timestamp = $this->cache['created'] ?? null;

        if (!isset($timestamp)) {
            $timestamp = strtotime($this->getOriginalObject()->created) ?: 0;
            $this->cache['created'] = $timestamp;
        }

        return $timestamp;
    }

    public function getUpdated() : int
    {
        $timestamp = $this->cache['updated'] ?? null;

        if (!isset($timestamp)) {
            $timestamp = strtotime($this->getOriginalObject()->updated) ?: 0;
            $this->cache['updated'] = $timestamp;
        }

        return $timestamp;
    }

    public function getAuthor() : \Badoo\Jira\User
    {
        $User = $this->cache['Author'] ?? null;

        if (!isset($User)) {
            $UserInfo = $this->getOriginalObject()->author;
            $User = \Badoo\Jira\User::fromStdClass($UserInfo);

            $this->cache['Author'] = $User;
        }

        return $User;
    }

    public function getUpdateAuthor() : \Badoo\Jira\User
    {
        $User = $this->cache['UpdateAuthor'] ?? null;

        if (!isset($User)) {
            $UserInfo = $this->getOriginalObject()->updateAuthor;
            $User = \Badoo\Jira\User::fromStdClass($UserInfo);

            $this->cache['UpdateAuthor'] = $User;
        }

        return $User;
    }

    /**
     * Check if comment body contains text. The search is performed on original body value, not the rendered one
     */
    public function contains(string $text) : bool
    {
        return strpos($this->getText(), $text) !== false;
    }

    public function update(string $new_text, array $visibility = []) : Comment
    {
        $CommentInfo = $this->Issue->getJira()->issue()->comment()->update(
            $this->Issue->getKey(),
            $this->id,
            $new_text,
            $visibility
        );

        $this->dropCache();
        $this->OriginalObject = $CommentInfo;

        return $this;
    }

    public function delete() : void
    {
        $this->Issue->getJira()->issue()->comment()->delete($this->Issue->getKey(), $this->id);
    }
}
