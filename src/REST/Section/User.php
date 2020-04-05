<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class User extends Section
{
    protected $by_key = [];
    protected $by_name = [];

    protected function cacheUser(\stdClass $UserInfo)
    {
        $this->by_key[$UserInfo->key]   = $UserInfo;
        $this->by_name[$UserInfo->name] = $UserInfo;
    }

    protected function getCached(string $key) : ?\stdClass
    {
        return $this->by_key[$key] ?? $this->by_name[$key] ?? null;
    }

    protected function dropCached(string $key)
    {
        $CachedUser = $this->getCached($key);
        if (!isset($CachedUser)) {
            return;
        }

        unset($this->by_key[$CachedUser->key]);
        unset($this->by_name[$CachedUser->name]);
    }

    /**
     * Create a new JIRA user
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/user-createUser
     *
     * @param string    $name - user login
     * @param string    $password
     * @param string    $email
     * @param string    $display_name - the name to display in UI
     * @param string[]  $applications - list of Atlassian applications user has access to (for Crowd installation)
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(
        string $name,
        string $password,
        string $email,
        string $display_name,
        array $applications = ["jira-software"]
    ) : \stdClass {
        $UserInfo = $this->Jira->post(
            'user',
            [
                "name"              => $name,
                "password"          => $password,
                "emailAddress"      => $email,
                "displayName"       => $display_name,
                "applicationKeys"   => $applications,
            ]
        );
        $this->cacheUser($UserInfo);
        return $UserInfo;
    }

    /**
     * Update exiting JIRA user
     * You can put 'null' in place of any parameter if you want to leave it unchanged
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/user-updateUser
     *
     * @param string    $name - user login to identify whom you want to update
     * @param string    $email - new user email
     * @param string    $display_name - the name to display in UI
     * @param string[]  $applications - list of Atlassian applications user has access to (for Crowd installation)
     * @param string    $new_name - new user login.
     *                              NOTE: this parameter can be immutable depending on your JIRA configuartion
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function update(
        string $name,
        string $email = null,
        string $display_name = null,
        array  $applications = null,
        string $new_name = null
    ) : \stdClass {
        $request = [];

        if (isset($email)) {
            $request["emailAddress"] = $email;
        }
        if (isset($display_name)) {
            $request["displayName"] = $display_name;
        }
        if (isset($applications)) {
            $request["applicationKeys"] = $applications;
        }
        if (isset($new_name)) {
            $request["name"] = $new_name;
        }

        $UserInfo = $this->Jira->put('user?' . http_build_query(['username' => $name]), $request);
        $this->cacheUser($UserInfo);
        return $UserInfo;
    }

    /**
     * Remove exiting JIRA user
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/user-removeUser
     *
     * @param string $name - user login to identify whom you want to update
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function remove(string $name) : void
    {
        $this->Jira->delete('user', ['username' => $name]);
        $this->dropCached($name);
    }

    /**
     * Get existing user info by username
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/user-getUser
     *
     * @param string    $name - user login to identify whom you want to get
     * @param string[]  $expand - provide additional fields information in response
     *                              E.g.: 'groups', 'applicationRoles'
     * @param bool      $reload_cache - force cache reload and get the fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $name, array $expand = [], bool $reload_cache = false) : \stdClass
    {
        $CachedUser = $this->getCached($name);
        if (isset($CachedUser) && !$reload_cache) {
            return $CachedUser;
        }

        $parameters = [
            'username' => $name
        ];

        if (!empty($expand)) {
            $parameters['expand'] = implode(',', $expand);
        }

        $UserInfo = $this->Jira->get('user', $parameters);
        $this->cacheUser($UserInfo);
        return $UserInfo;
    }

    /**
     * Get existing user info by key
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/user-getUser
     *
     * @param string $key
     * @param string[] $expand - provide additional fields information in response
     *                              E.g.: 'groups', 'applicationRoles'
     * @param bool $reload_cache - force cache reload and get the fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getByKey(string $key, array $expand = [], bool $reload_cache = false) : \stdClass
    {
        $CachedUser = $this->getCached($key);
        if (isset($CachedUser) && !$reload_cache) {
            return $CachedUser;
        }

        $parameters = [
            'key' => $key
        ];

        if (!empty($expand)) {
            $parameters['expand'] = implode(',', $expand);
        }

        $UserInfo = $this->Jira->get('user', $parameters);
        $this->cacheUser($UserInfo);
        return $UserInfo;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/user-findUsers
     *
     * @param string    $pattern - user login, display name or email
     * @param int       $start_at - starts from 0
     * @param int       $max_results - maximum value is 1000. Values higher than 1000 have the same effect as 1000
     * @param bool      $include_active
     * @param bool      $include_inactive
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function search(
        string $pattern,
        int $start_at = 0,
        int $max_results = 50,
        bool $include_active = true,
        bool $include_inactive = false
    ) : array {
        $users = $this->Jira->get(
            'user/search',
            [
                'username'        => $pattern,
                'startAt'         => $start_at,
                'maxResults'      => $max_results,
                'includeActive'   => $include_active ? 'true' : 'false',
                'includeInactive' => $include_inactive ? 'true' : 'false',
            ]
        );

        foreach ($users as $UserInfo) {
            $this->cacheUser($UserInfo);
        }
        return $users;
    }
}
