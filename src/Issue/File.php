<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class File
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

    public static function fromStdClass(
        \stdClass $AttachmentInfo,
        \Badoo\Jira\Issue $Issue,
        \Badoo\Jira\REST\Client $Jira = null
    ) : File {
        $Instance = new static((int)$AttachmentInfo->id, $Issue, $Jira);
        $Instance->OriginalObject = $AttachmentInfo;

        return $Instance;
    }

    public function __construct(int $id, \Badoo\Jira\Issue $Issue = null, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->id = $id;
        $this->Issue = $Issue;
        $this->Jira = $Jira;
    }

    protected function getOriginalObject() : \stdClass
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->attachment()->get($this->id);
        }

        return $this->OriginalObject;
    }

    protected function dropCache() : void
    {
        $this->OriginalObject = null;
        $this->cache = [];
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getName() : string
    {
        return $this->getOriginalObject()->filename;
    }

    public function getSize() : int
    {
        return (int)$this->getOriginalObject()->size;
    }

    public function getMimeType() : string
    {
        return $this->getOriginalObject()->mimeType;
    }

    public function getContentLink() : string
    {
        return $this->getOriginalObject()->content;
    }

    public function getThumbnailLink() : string
    {
        return $this->getOriginalObject()->thumbnail;
    }

    public function getCreated() : int
    {
        $key = 'created';

        if (!isset($this->cache[$key])) {
            $time = $this->getOriginalObject()->created;
            $this->cache[$key] = (int)strtotime($time);
        }

        return $this->cache[$key];
    }

    public function getAuthor() : \Badoo\Jira\User
    {
        $key = 'Author';

        if (!isset($this->cache[$key])) {
            $UserInfo = $this->getOriginalObject()->author;
            $this->cache[$key] = \Badoo\Jira\User::fromStdClass($UserInfo, $this->Issue, $this->Jira);
        }

        return $this->cache[$key];
    }

    public function delete()
    {
        $this->Jira->attachment()->delete($this->getId());
    }
}
