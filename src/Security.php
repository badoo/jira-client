<?php
/**
 * @package Jira
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira;

class Security
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;            // 10000

    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    public static function fromStdClass(
        \stdClass $SecurityLevel,
        \Badoo\Jira\Issue $Issue = null,
        \Badoo\Jira\REST\Client $Jira = null
    ) : Security {
        $Instance = new static((int)$SecurityLevel->id, $Jira);

        $Instance->OriginalObject = $SecurityLevel;
        $Instance->Issue = $Issue;

        return $Instance;
    }

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

    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->securityLevel()->get($this->getId());
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

    public function getSelf() : string
    {
        return $this->getOriginalObject()->self;
    }
}
