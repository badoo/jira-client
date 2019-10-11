<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

class Attachments
{
    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    /** @var File[] */
    protected $files;

    public static function fromStdClass(
        array $files,
        \Badoo\Jira\Issue $Issue
    ) : Attachments {
        $Instance = new static($Issue);

        foreach ($files as $AttachmentInfo) {
            $Instance->files[] = File::fromStdClass($AttachmentInfo, $Issue, $Issue->getJira());
        }

        return $Instance;
    }

    public static function forIssue(string $issue_key, \Badoo\Jira\REST\Client $Jira = null) : Attachments
    {
        $Issue = \Badoo\Jira\Issue::byKey($issue_key, ['attachment'], [], $Jira);
        return $Issue->getAttachments();
    }

    public function __construct(\Badoo\Jira\Issue $Issue)
    {
        $this->Issue = $Issue;
    }

    protected function getJira() : \Badoo\Jira\REST\Client
    {
        return $this->Issue->getJira();
    }

    /**
     * @return File[]
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getFiles() : array
    {
        if (!isset($this->files)) {
            $this->files = [];

            $attachments = $this->getJira()->issue()->attachment()->list($this->Issue->getKey());
            foreach ($attachments as $AttachmentInfo) {
                $this->files[] = File::fromStdClass($AttachmentInfo, $this->Issue, $this->getJira());
            }
        }

        return $this->files;
    }

    public function attach(string $file_path, ?string $file_name = null, ?string $file_type = null) : File
    {
        if (!\Badoo\Jira\Helpers\Files::exists($file_path)) {
            throw new \Badoo\Jira\Exception\File(
                "File {$file_path} not found on disk. Can't upload it to JIRA"
            );
        }

        $AttachmentInfo = $this->getJira()->issue()->attachment()->create($this->Issue->getKey(), $file_path, $file_name, $file_type);
        $File = File::fromStdClass($AttachmentInfo, $this->Issue, $this->getJira());

        if (isset($this->files)) {
            $this->files[] = $File;
        }

        return $File;
    }
}
