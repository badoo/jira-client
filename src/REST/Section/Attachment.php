<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Attachment extends \Badoo\Jira\REST\Section\Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/attachment-getAttachment
     *
     * Get attachment file metadata by file ID
     *
     * @param int $id - ID of attachment you want to load
     *
     * @return \stdClass - attachment metadata
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id) : \stdClass
    {
        return $this->Jira->get("attachment/{$id}");
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/attachment-removeAttachment
     *
     * Delete attachment file from JIRA.
     *
     * @param int $id - ID of file to delete
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(int $id) : void
    {
        $this->Jira->delete("attachment/{$id}");
    }
}
