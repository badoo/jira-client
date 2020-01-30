<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Group extends Section
{
    protected $groups = [];
    protected $users = [];

    protected function cacheGroup(\stdClass $GroupInfo)
    {
        $this->groups[$GroupInfo->name] = $GroupInfo;
    }

    protected function cacheGroupUsers(string $group_name, array $users)
    {
        $this->users[$group_name] = $users;
    }

    protected function dropCache(string $name)
    {
        unset($this->groups[$name]);
        unset($this->users[$name]);
    }

    /**
     * Create a new JIRA user group
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/group-createGroup
     *
     * @param string $name - the new group name
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(
        string $name
    ) : \stdClass {
        $GroupInfo = $this->Jira->post(
            'group',
            [
                "name" => $name,
            ]
        );

        $this->cacheGroup($GroupInfo);
        return $GroupInfo;
    }

    /**
     * Remove exiting JIRA user group, optionally transfering its restrictions to another group
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/group-removeGroup
     *
     * @param string $name - unique group name to be removed
     * @param string|null $swap_group - transfer restrictions to another group (replace deleted group settings to
     *                                  this one to keep comments/worklogs viewable after remove)
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function remove(string $name, string $swap_group = null) : void
    {
        $parameters = [
            'groupname' => $name
        ];

        if (!empty($swap_group)) {
            $parameters['swapGroup'] = $swap_group;
        }

        $this->Jira->delete('group', $parameters);
        $this->dropCache($name);
    }

    /**
     * Get existing user group info
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/group-getGroup
     *
     * @param string    $name - unique group key to identify what you want to get
     * @param bool      $reload_cache - force cache reload and get the fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $name, bool $reload_cache = false) : \stdClass
    {
        $GroupInfo = $this->groups[$name] ?? null;
        if (!isset($GroupInfo) || $reload_cache) {
            $GroupInfo = $this->Jira->get('group', ['groupname' => $name]);
            $this->cacheGroup($GroupInfo);
        }

        return $GroupInfo;
    }

    /**
     * Get list of users in group with pagination of max 50 user in a response
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/group-getUsersFromGroup
     *
     * @param string    $name - group name
     * @param int       $start_at - starts from 0
     * @param int       $max_results - maximum value is 50
     * @param bool      $include_inactive - list inactive group members as well
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listUsers(
        string $name,
        int $start_at = 0,
        int $max_results = 50,
        bool $include_inactive = false
    ) : \stdClass {
        $users = $this->Jira->get(
            'group/member',
            [
                'groupname'       => $name,
                'startAt'         => $start_at,
                'maxResults'      => $max_results,
                'includeInactive' => $include_inactive ? 'true' : 'false',
            ]
        );

        return $users;
    }

    /**
     * Get full list of users in group.
     * Causes several sequental requests when group has more than 50 members
     *
     * WARNING: huge groups (hundreds of users, including inactive) can take long time to load.
     *          E.g. group with 400 users can take up to 6 seconds, use with caution.
     *
     * @param string $name              - name of group
     * @param bool   $include_inactive  - list inactive group members as well
     * @param bool   $reload_cache      - force cache reload. Client caches groups info to prevent duplicate requests,
     *                                    considering groups info changes rarely
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listAllUsers(
        string $name,
        bool $include_inactive = false,
        bool $reload_cache = false
    ) : array {
        if (!isset($this->users[$name]) || $reload_cache) {
            $this->users[$name] = [];

            $page_size = 50;
            $page_start = 0;
            $is_last = false;

            while (!$is_last) {
                $chunk = $this->listUsers($name, $page_start, $page_size, true);
                $page_start += $page_size;
                $is_last = $chunk->isLast;

                foreach ($chunk->values as $UserInfo) {
                    $this->users[$name][$UserInfo->name] = $UserInfo;
                }
            }
        }

        if ($include_inactive) {
            return $this->users[$name];
        }

        $users = [];
        foreach ($this->users[$name] as $name => $UserInfo) {
            if ($UserInfo->active) {
                $users[$name] = $UserInfo;
            }
        }
        return $users;
    }

    /**
     * Add user to group
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/group-addUserToGroup
     *
     * @param string $groupname - a name of group you want to add new user to
     * @param string $username - login of user to add
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function addUser(string $groupname, string $username) : \stdClass
    {
        $GroupInfo = $this->Jira->post(
            'group/user?' . http_build_query(['groupname' => $groupname]),
            ['name' => $username]
        );

        unset($this->users[$groupname]);
        $this->cacheGroup($GroupInfo);

        return $GroupInfo;
    }

    /**
     * Remove user from group
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/group-removeUserFromGroup
     *
     * @param string $groupname - a name of group you want to remove user from
     * @param string $username - login of user to remove
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function removeUser(string $groupname, string $username) : void
    {
        $this->Jira->delete(
            'group/user',
            [
                'groupname' => $groupname,
                'username' => $username
            ]
        );

        if (isset($this->users[$groupname])) {
            unset($this->users[$groupname][$username]);
        }
    }
}
