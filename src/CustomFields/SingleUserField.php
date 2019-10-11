<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class SingleUserField
 * @package Badoo\Jira\CustomFields\Abstracts
 *
 * Wrapper class for 'user picker' type custom field
 */
abstract class SingleUserField extends CustomField
{
    /** @var \Badoo\Jira\User */
    protected $User;

    public function dropCache()
    {
        $this->User = null;
        return parent::dropCache();
    }

    /**
     * @return \Badoo\Jira\User|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getValue() : ?\Badoo\Jira\User
    {
        if ($this->isEmpty()) {
            return null;
        }

        if (!isset($this->User)) {
            $UserInfo = $this->getOriginalObject();
            $this->User = \Badoo\Jira\User::fromStdClass($UserInfo, $this->Issue, $this->Issue->getJira());
        }

        return $this->User;
    }

    /**
     * @param string|null $user - name of user
     * @return array
     */
    public static function generateSetter($user) : array
    {
        if (!isset($user)) {
            return [ [ 'set' => null ] ];
        }

        return [ [ 'set' => ['name' => $user] ] ];
    }

    /**
     * @param $user
     * @return \Badoo\Jira\User
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\CustomField
     */
    protected function loadUser($user) : \Badoo\Jira\User
    {
        if ($user instanceof \Badoo\Jira\User) {
            return $user;
        }

        if (is_string($user)) {
            $users = \Badoo\Jira\User::search($user, $this->Issue->getJira());

            if (!empty($users)) {
                return reset($users);
            }
        }

        throw new \Badoo\Jira\Exception\CustomField(
            "User '{$user}' not found in Jira. Can't change '{$this->getName()}' field value."
        );
    }

    /**
     * @param \Badoo\Jira\User|string $user
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\CustomField
     */
    public function setValue($user)
    {
        if (!isset($user)) {
            parent::setValue(null);
            return $this;
        }

        $User = $this->loadUser($user);
        parent::setValue($User->getName());

        return $this;
    }
}
