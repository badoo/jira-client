<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST;

/**
 * Class ClientRaw
 * Raw client to JIRA REST API. Provides the most direct access to API possible, without any bindings.
 * Supports authorization, allows to just send HTTP requests to API and get responses parsed as JSON or raw
 * response data when needed.
 *
 * Treats API error responses and throws exceptions. That's all.
 */
class ClientRaw
{
    const DEFAULT_JIRA_URL          = 'https://jira.localhost/';
    const DEFAULT_JIRA_API_PREFIX   = '/rest/api/latest/';

    const
        REQ_GET       = 'GET',
        REQ_POST      = 'POST',
        REQ_PUT       = 'PUT',
        REQ_DELETE    = 'DELETE',
        REQ_MULTIPART = 'MULTIPART';

    protected static $instance = null;

    protected $jira_url;
    protected $api_prefix;

    /** @var string - login of user to use in API requests */
    private $login = '';
    /** @var string - login's authentication secret. It can be API token (good) or bare user password (deprecated) */
    private $secret = '';

    protected $request_timeout = 60;

    public static function instance() : ClientRaw
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct(
        $jira_url   = self::DEFAULT_JIRA_URL,
        $api_prefix = self::DEFAULT_JIRA_API_PREFIX
    ) {
        $this->setJiraUrl($jira_url);
        $this->setApiPrefix($api_prefix);
    }

    //
    // BEGIN - API client settings
    //

    /**
     * Set credentials to use in each request to Jira REST API.
     * @param string $login  - user login
     * @param string $secret - raw user passowrd (deprecated) or API token (good)
     *
     * @return ClientRaw
     */
    public function setAuth(string $login, string $secret) : ClientRaw
    {
        $this->login    = $login;
        $this->secret   = $secret;

        return $this;
    }

    public function getLogin() : string
    {
        return $this->login;
    }

    /**
     * Jira URL is a URL to root Jira Web UI (it does not contain API path)
     *
     * Jira URL always ends with '/' character.
     */
    public function getJiraUrl() : string
    {
        return $this->jira_url;
    }

    public function setJiraUrl(string $url) : ClientRaw
    {
        // force URL to end with '/': e.g. 'https://jira.example.com/'
        $this->jira_url = rtrim($url, '/') . '/';
        return $this;
    }

    /**
     * API prefix is a URI that points to Jira API root and is added to each API method request.
     * It usually looks like /rest/api/v2/ or /rest/api/latest/.
     *
     * API prefix always ends with '/' character.
     */
    public function getApiPrefix() : string
    {
        return $this->api_prefix;
    }

    public function setApiPrefix(string $api_prefix) : ClientRaw
    {
        // force URI to have no '/' at the beginning and to HAVE '/' at the end: e.g. 'rest/api/latest/'
        $this->api_prefix = trim($api_prefix, '/') . '/';
        return $this;
    }

    public function getRequestTimeout() : int
    {
        return $this->request_timeout;
    }

    public function setRequestTimeout(int $request_timeout) : ClientRaw
    {
        $this->request_timeout = $request_timeout;
        return $this;
    }

    //
    // END - API client settings
    //

    /**
     * Request Jira REST API with HTTP GET request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $api_method, array $arguments = [], bool $raw = false)
    {
        return $this->request(self::REQ_GET, $api_method, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP POST request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param mixed  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Badoo\Jira\REST\Exception
     */
    public function post(string $api_method, $arguments = [], bool $raw = false)
    {
        return $this->request(self::REQ_POST, $api_method, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP POST request type and multipart request body encoding.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Badoo\Jira\REST\Exception
     */
    public function multipart(string $api_method, array $arguments, bool $raw = false)
    {
        return $this->request(self::REQ_MULTIPART, $api_method, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP PUT request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Badoo\Jira\REST\Exception
     */
    public function put(string $api_method, array $arguments = [], bool $raw = false)
    {
        return $this->request(self::REQ_PUT, $api_method, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP DELETE request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(string $api_method, array $arguments = [], bool $raw = false)
    {
        return $this->request(self::REQ_DELETE, $api_method, $arguments, $raw);
    }

    /**
     * Make a request to Jira REST API and parse response.
     * Return array with response data parsed as JSON or null for empty response body.
     *
     * @param string $http_method - HTTP request method (e.g. HEAD/PUT/GET...)
     * @param string $api_method  - API method path (e.g. issue/<key>)
     * @param mixed  $arguments   - request data (parameters)
     * @param bool   $raw         - don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null - raw response (string), response parsed as JSON (array, \stdClass) or
     *                                             null for responses with empty body
     *
     * @throws \Badoo\Jira\REST\Exception - on JSON parse errors, on warning HTTP codes and other errors.
     */
    private function request(string $http_method, string $api_method, $arguments = [], bool $raw = false)
    {
        $url = $this->getJiraUrl() . $this->getApiPrefix() . ltrim($api_method, '/');
        if (in_array($http_method, [self::REQ_GET, self::REQ_DELETE]) && !empty($arguments)) {
            $url = $url . '?' . http_build_query($arguments);
        }

        $curl_options = [
            CURLOPT_USERPWD        => $this->login . ':' . $this->secret,
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => $this->request_timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        $header_options = [
            'Accept'            => 'application/json',
            'Content-Type'      => 'application/json',
        ];

        switch ($http_method) {
            case self::REQ_POST:
                $arguments = json_encode($arguments);
                $curl_options[CURLOPT_POST]       = true;
                $curl_options[CURLOPT_POSTFIELDS] = $arguments;
                break;

            case self::REQ_MULTIPART:
                $header_options['Content-Type']         = 'multipart/form-data';
                $header_options['X-Atlassian-Token']    = 'no-check';

                $curl_options[CURLOPT_POST]       = true;
                $curl_options[CURLOPT_POSTFIELDS] = $arguments;
                break;

            case self::REQ_PUT:
                $arguments = json_encode($arguments);
                $curl_options[CURLOPT_CUSTOMREQUEST] = self::REQ_PUT;
                $curl_options[CURLOPT_POST]          = true;
                $curl_options[CURLOPT_POSTFIELDS]    = $arguments;
                break;

            case self::REQ_DELETE:
                $curl_options[CURLOPT_CUSTOMREQUEST] = self::REQ_DELETE;
                break;

            default:
        }

        $headers = [];
        foreach ($header_options as $opt_name => $opt_value) {
            $headers[] = "$opt_name: $opt_value";
        }
        $curl_options[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);

        $result_raw = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($result_raw === false) {
            throw new \Badoo\Jira\REST\Exception("Request to '{$url}' timeouted after {$this->getRequestTimeout()} seconds");
        }

        $http_code = $info['http_code'];
        $content_type = $info['content_type'];

        $is_json = strpos($content_type, 'application/json') === 0;

        if (in_array($http_code, [200, 201, 204]) and empty($result_raw)) {
            return null; // empty response body is OK of some API methods
        }

        $result     = json_decode($result_raw);
        $error      = json_last_error();
        $json_error = $error !== JSON_ERROR_NONE;

        if ($is_json && $json_error) {
            throw new \Badoo\Jira\REST\Exception(
                "Jira REST API interaction error, failed to parse JSON: " . json_last_error_msg()
                . ". Raw API response: " . var_export($result_raw, 1)
            );
        }

        $this->handleAPIError($info, $result_raw, $result);

        if ($raw) {
            return $result_raw;
        }

        if ($json_error) {
            throw new \Badoo\Jira\REST\Exception(
                "Jira REST API responded with non-JSON data. " .
                "Use <raw> parameter if you want to get the result as a string"
            );
        }

        return $result;
    }

    protected function renderExceptionMessage(\stdClass $ErrorResponse) : string
    {
        if (!empty($ErrorResponse->message)) {
            return "Jira REST API returned an error: " . $ErrorResponse->message;
        }

        $errors = array_merge(
            (array)($ErrorResponse->errorMessages ?? []),
            (array)($ErrorResponse->errors ?? [])
        );

        return "Jira REST API returned an error:\n\t" . implode("\n\t", $errors);
    }

    /**
     * @param array $response_info
     * @param string $response_raw
     * @param $response
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\REST\Exception\Authorization
     */
    protected function handleAPIError(array $response_info, string $response_raw, $response)
    {
        $url          = $response_info['url'];
        $http_code    = $response_info['http_code'];
        $content_type = $response_info['content_type'];

        $is_html = strpos($content_type, 'text/html') === 0;
        $is_json = strpos($content_type, 'application/json') === 0;

        if ($http_code === 401 && $is_html) {
            throw new \Badoo\Jira\REST\Exception\Authorization(
                "Jira API authorization failed for URL $url. Used '$this->login' user. Please check credentials"
            );
        }

        if ($http_code === 403 && $is_html) {
            throw new \Badoo\Jira\REST\Exception\Authorization(
                "Access to the API method is forbidden. URL: $url. Used '$this->login' user. "
                    . "You either have not enough privileges or the captcha shown to your user"
            );
        }

        if ($http_code >= 400 && !$is_json) {
            throw new \Badoo\Jira\REST\Exception(
                "Jira REST API responded with code {$http_code} and content type {$content_type}. "
                    . "URL: $url. API answer: " . var_export($response_raw, 1)
            );
        }

        if (!$is_json) {
            return;
        }

        if (!empty($response->errorMessages)) {
            $Error = new \Badoo\Jira\REST\Exception("Jira REST API call error: " . implode('; ', $response->errorMessages));
            $Error->setApiResponse($response);
            throw $Error;
        }

        if ($http_code >= 400) {
            $Error = new \Badoo\Jira\REST\Exception($this->renderExceptionMessage($response));
            $Error->setApiResponse($response);

            throw $Error;
        }
    }
}
