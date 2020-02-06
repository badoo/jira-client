<?php
/**
 * @team QA <qa@corp.badoo.com>
 * @maintainer Petr Travkin <petr.travkin@corp.badoo.com>
 * @package Jira
 */

namespace Badoo\Jira;

/**
 * Class Client
 * @package Badoo\Jira
 */
class Client
{
    /**
     * @var \Badoo\Jira\REST\Client
     */
    private $RESTClient;

    /**
     * Client constructor.
     *
     * @param \Badoo\Jira\REST\Client $RESTClient
     */
    public function __construct(\Badoo\Jira\REST\Client $RESTClient)
    {
        $this->RESTClient = $RESTClient;
    }

    /**
     * @param string $issue_key
     *
     * @return \Badoo\Jira\Issue
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getIssue(string $issue_key) : \Badoo\Jira\Issue
    {
        return \Badoo\Jira\Issue::byKey($issue_key, [], [], $this->RESTClient);
    }

    /**
     * @param string ...$keys
     *
     * @return array
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     *
     * @see \Badoo\Jira\Issue::ByKeys
     */
    public function getIssues(string ...$keys) : array
    {
        return \Badoo\Jira\Issue::byKeys($keys);
    }

    /**
     * @param string $jql
     * @param int $limit
     * @param int $offset
     *
     * @return array
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     *
     * @see \Badoo\Jira\Issue::search
     */
    public function searchIssue(string $jql, int $limit, int $offset) : array
    {
        return \Badoo\Jira\Issue::search($jql, [], [], $limit, $offset, $this->RESTClient);
    }

    /**
     * @param string $project_key - key of project for new issue (e.g. EX, TEST, IOS)
     * @param string|int $issue_type - textual name or ID of type for issue you are going to create (e.g. 'Bug' or 34)
     *
     * @return \Badoo\Jira\Issue\CreateRequest
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     *
     */
    public function createIssue(string $project_key, $issue_type) : \Badoo\Jira\Issue\CreateRequest
    {
        return new \Badoo\Jira\Issue\CreateRequest($project_key, $issue_type, $this->RESTClient);
    }

    /**
     * @param string $issue_key
     * 
     * @throws \Badoo\Jira\Exception\Issue
     * 
     * @throws \Badoo\Jira\REST\Exception
     */
    public function deleteIssue(string $issue_key) : void 
    {
        $this->getIssue($issue_key)->delete();;
    }

    /**
     * @param string $name
     *
     * @return \Badoo\Jira\User
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getUser(string $name) : \Badoo\Jira\User
    {
        return \Badoo\Jira\User::get($name, $this->RESTClient);
    }

    /**
     * @param string $email
     *
     * @return \Badoo\Jira\User
     *
     * @throws \Badoo\Jira\Exception\User
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getUserByEmail(string $email) : \Badoo\Jira\User
    {
        return \Badoo\Jira\User::byEmail($email, $this->RESTClient);

    }

    /**
     * @param string $pattern
     *
     * @return \Badoo\Jira\User[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function searchUser(string $pattern) : array
    {
        return \Badoo\Jira\User::search($pattern, $this->RESTClient);
    }

    /**
     * @param int $id
     *
     * @return \Badoo\Jira\Component
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getComponent(int $id) : \Badoo\Jira\Component
    {
        return \Badoo\Jira\Component::get($id, $this->RESTClient);
    }

    /**
     * @param $project
     *
     * @return \Badoo\Jira\Component[]
     *
     * @throws REST\Exception
     */
    public function getComponentsForProject($project) : array
    {
        return \Badoo\Jira\Component::forProject($project, $this->RESTClient);
    }

    /**
     * Search for component in a project by name instead of getting it directly by ID.
     *
     * @param $project - project key or id
     * @param string $name - name of component you want to get
     *
     * @return \Badoo\Jira\Component
     *
     * @throws Exception
     * @throws Exception\Component
     */
    public function getComponentByName($project, string $name) : \Badoo\Jira\Component
    {
        return \Badoo\Jira\Component::byName($project, $name, $this->RESTClient);
    }

    /**
     * @param $project
     * @param string $component_name
     *
     * @return bool
     *
     * @throws REST\Exception
     */
    public function isComponentExists($project, string $component_name) : bool
    {
        return \Badoo\Jira\Component::exists($project, $component_name, $this->RESTClient);
    }

    /**
     * @param string $name
     *
     * @return \Badoo\Jira\Group
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getGroup(string $name) : \Badoo\Jira\Group
    {
        return \Badoo\Jira\Group::fromStdClass($this->RESTClient->group()->get($name));
    }


}