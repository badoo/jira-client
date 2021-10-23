<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CLI;

class Configuration
{
    const DEFAULT_CONFIG_FILE = './jira-generator-config.yaml';

    /** @var array */
    protected $Config = [];
    protected $config_file_path = self::DEFAULT_CONFIG_FILE;

    protected function readCredentials(string $file_path) : array
    {
        $creds = \Badoo\Jira\Helpers\Files::fileGetContents($file_path);
        $creds = rtrim($creds, "\n"); // drop newlines after password

        list($user, $password) = explode(':', $creds, 2);
        return [$user, $password];
    }

    public function resolvePath(string ...$path_parts) : string
    {
        $path_parts = array_filter($path_parts); // drop empty parts
        $path = implode(DIRECTORY_SEPARATOR, $path_parts); // join into single path

        if (empty($path)) {
            return $this->getConfigFileDir();
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            // Absolute path
            return $path;
        }

        // relative non-empty path
        return $this->getConfigFileDir() . DIRECTORY_SEPARATOR . $path;
    }

    public function load(string $config_path = self::DEFAULT_CONFIG_FILE)
    {
        $this->config_file_path = realpath($config_path);
        $this->Config = \Symfony\Component\Yaml\Yaml::parseFile($config_path);
    }

    public function getConfigFilePath() : string
    {
        return $this->config_file_path;
    }

    public function getConfigFileDir() : string
    {
        return dirname($this->config_file_path);
    }

    public function setLogLevel(string $log_level) : Configuration
    {
        $this->Config['log-level'] = $log_level;
        return $this;
    }

    public function getLogLevel()
    {
        return $this->Config['log-level'] ?? \Psr\Log\LogLevel::WARNING;
    }

    public function setJiraUrl(string $jira_url) : Configuration
    {
        $this->Config['JIRA']['URL'] = $jira_url;
        return $this;
    }

    public function getJiraURL() : string
    {
        return $this->Config['JIRA']['URL'] ?? 'https://jira.localhost/';
    }

    public function setJiraCredentialsFile(string $jira_credentials_file) : Configuration
    {
        $this->Config['JIRA']['credentials-file'] = $jira_credentials_file;
        return $this;
    }

    public function setJiraUser(string $jira_user) : Configuration
    {
        $this->Config['JIRA']['user'] = $jira_user;
        return $this;
    }

    public function getJiraUser() : string
    {
        if (isset($this->Config['JIRA']['user'])) {
            return $this->Config['JIRA']['user'];
        }

        if (isset($this->Config['JIRA']['credentials-file'])) {
            return $this->readCredentials($this->Config['JIRA']['credentials-file'])[0];
        }

        return 'automation';
    }

    public function setJiraPassword(string $jira_password) : Configuration
    {
        $this->Config['JIRA']['password'] = $jira_password;
        return $this;
    }

    public function getJiraPassword() : string
    {
        if (isset($this->Config['JIRA']['password'])) {
            return $this->Config['JIRA']['password'];
        }

        if (isset($this->Config['JIRA']['credentials-file'])) {
            return $this->readCredentials($this->Config['JIRA']['credentials-file'])[1];
        }

        return '';
    }

    public function setJiraTimeout(int $jira_timeout) : Configuration
    {
        $this->Config['JIRA']['timeout'] = $jira_timeout;
        return $this;
    }

    public function getJiraTimeout() : string
    {
        return $this->Config['JIRA']['timeout'] ?? 60;
    }

    public function getJiraClient() : \Badoo\Jira\REST\Client
    {
        $JiraRaw = new \Badoo\Jira\REST\ClientRaw($this->getJiraURL());
        $JiraRaw->setAuth(
            $this->getJiraUser(),
            $this->getJiraPassword()
        )
            ->setRequestTimeout(
                $this->getJiraTimeout()
            );

        return new \Badoo\Jira\REST\Client($JiraRaw);
    }

    public function setGeneratorTargetDirectory(string $path) : Configuration
    {
        $this->Config['Generator']['target-directory'] = $path;
        return $this;
    }

    public function getGeneratorTargetDirectory() : string
    {
        return $this->resolvePath($this->Config['Generator']['target-directory'] ?? '');
    }

    public function setGeneratorMapFile(string $path) : Configuration
    {
        $this->Config['Generator']['map-file'] = $path;
        return $this;
    }

    public function getGeneratorMapFile() : string
    {
        $path = $this->Config['Generator']['map-file'] ?? '';
        if (empty($path)) {
            return ''; // let Generator decide whan name should be default
        }

        return $this->resolvePath($path);
    }

    public function setGeneratorTargetNamespace(string $path) : Configuration
    {
        $this->Config['Generator']['target-namespace'] = $path;
        return $this;
    }

    public function getGeneratorTargetNamespace() : string
    {
        return $this->Config['Generator']['target-namespace'] ?? '.';
    }

    public function getGeneratorCustomTemplates() : array
    {
        return $this->Config['Generator']['custom-templates'] ?? [];
    }

    /**
     * @return bool[]
     */
    public function getGeneratorSkipFields() : array
    {
        return $this->Config['Generator']['skip-fields'] ?? [];
    }

    /**
     * @return bool[]
     */
    public function getGeneratorSkipTypes() : array
    {
        return $this->Config['Generator']['skip-types'] ?? [];
    }

    /**
     * @return bool[]
     */
    public function getGeneratorSkipTypePatterns() : array
    {
        return $this->Config['Generator']['skip-type-patterns'] ?? [];
    }
}
