<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class IssueAttachment extends Section
{
    protected $attachments = [];

    protected function cacheAttachments(\stdClass $IssueInfo) : array
    {
        $attachments = $IssueInfo->fields->attachment ?? [];

        $this->attachments[$IssueInfo->key] = $attachments;
        $this->attachments[$IssueInfo->id] = $attachments;

        return $attachments;
    }

    protected function getCached(string $issue_key) : ?array
    {
        return $this->attachments[$issue_key] ?? null;
    }

    /**
     * List all issue attachments.
     *
     * NOTE: this is synthetic method, JIRA API has no special method for listing issue attachments.
     *       They are listed as part of issue in 'attachment' field
     *
     * @param string $issue_key
     * @param bool $reload_cache - force internal cache reload.
     *                             When you try to load attachments for the same issue twice, it will cause only one
     *                             real API request if <reload_cache> is false
     *
     * @return \stdClass[] - list of files attached to issue
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(string $issue_key, bool $reload_cache = false) : array
    {
        $attachments = $this->getCached($issue_key);

        if (!isset($attachments) || $reload_cache) {
            $IssueInfo = $this->Jira->get(
                "issue/{$issue_key}",
                [ 'fields' => "id,key,attachment"]
            );
            $attachments = $this->cacheAttachments($IssueInfo);
        }

        return $attachments;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue/{issueIdOrKey}/attachments-addAttachment
     *
     * Attach new file to issue
     *
     * @param string $issue_key - key of issue to attach files to.
     * @param string $file_path - path to file to upload to Jira as attachment to an issue.
     * @param string $file_type - file's mime type
     * @param string $file_name - name of file to be sent to Jira.
     *
     * @return \stdClass - attachment info
     *                       [
     *                         'id'         => <unique commend ID, int>,
     *                         'filename'   => <name of file, string>,
     *                         'author'     => <JIRA user info>,
     *                         'created'    => <attach date, ISO formatted string>,
     *                         'size'       => <file size in bytes, int>,
     *                         'mimeType'   => <file mime type, string>,
     *                         'content'    => <file content URL, string>,
     *                         'thumbnail'  => <thumbnail URL, string>
     *                       ]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(string $issue_key, string $file_path, ?string $file_name = null, ?string $file_type = null) : \stdClass
    {
        $File = new \CURLFile($file_path, $file_type, $file_name);
        $response = $this->Jira->multipart("issue/{$issue_key}/attachments", ['file' => $File]);

        return reset($response);
    }

    /**
     * Get specific issue attachment info
     *
     * NOTE: this is synthetic method, JIRA API has no special method
     *
     * @param string $issue_key
     * @param int $id
     * @param bool $reload_cache
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $issue_key, int $id, bool $reload_cache = false) : \stdClass
    {
        $attachments = $this->list($issue_key, $reload_cache);

        foreach ($attachments as $AttachmentInfo) {
            if ((int)$AttachmentInfo->id === $id) {
                return $AttachmentInfo;
            }
        }

        throw new \Badoo\Jira\REST\Exception(
            "Attachment with ID {$id} not found in issue {$issue_key}"
        );
    }
}
