<?php

namespace Badoo\Jira\REST\Section;

use Badoo\Jira\REST\ClientRaw;
use PHPUnit\Framework\TestCase;

class IssueTest extends TestCase
{
    private const LEGACY_RESPONSE_JSON = <<<JSON
{
  "expand": "schema,names",
  "startAt": 0,
  "maxResults": 1000,
  "total": 3,
  "issues": [
    {
      "expand": "operations,versionedRepresentations,editmeta,changelog,renderedFields",
      "id": "3815086",
      "self": "https://jira.com/rest/api/latest/issue/3815086",
      "key": "SOME-44370",
      "fields": {
        "summary": "Update Android Gradle Plugin to 8.1",
        "customfield_18762": "a"
      }
    },
    {
      "expand": "operations,versionedRepresentations,editmeta,changelog,renderedFields",
      "id": "3759463",
      "self": "https://jira.com/rest/api/latest/issue/3759463",
      "key": "SOME-44407",
      "fields": {
        "summary": "Automate Xcode performance report",
        "customfield_18762": "b"
      }
    },
    {
      "expand": "operations,versionedRepresentations,editmeta,changelog,renderedFields",
      "id": "3881457",
      "self": "https://jira.com/rest/api/latest/issue/3881457",
      "key": "SOME-52193",
      "fields": {
        "summary": "Investigate potential benefit of establishing connection with CDN as early as possible",
        "customfield_18762": "c"
      }
    }
  ],
  "names": {
    "summary": "Summary",
    "customfield_18762": "Parent Link"
  }
}
JSON;

    /**
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-post
     */
    private const BASIC_CLOUD_RESPONSE_JSON = <<<JSON
{
  "isLast": true,
  "names": {
    "summary": "Summary",
    "customfield_18762": "Parent Link"
  },
  "issues": [
    {
      "id": "10002",
      "key": "ED-1",
      "self": "https://your-domain.atlassian.net/rest/api/3/issue/10002",
      "fields": {
        "summary": "Main order flow broken",
        "customfield_18762": "ED-2",
        "description": "Main order flow broken"
      }
    }
  ]
}
JSON;


    public function testSearchLegacy()
    {
        $rawClient = $this->getMockBuilder(ClientRaw::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['post'])
            ->getMock();
        $rawClient
            ->method('post')
            ->willReturn(json_decode(self::LEGACY_RESPONSE_JSON));

        $response = (new Issue($rawClient, false))->search("some jql with expand=names");
        foreach ($response as $issue) {
            $this->assertNotNull($issue->names ?? null);
            $this->assertEquals($issue->names->summary ?? null, 'Summary');
            $this->assertEquals($issue->names->customfield_18762 ?? null, 'Parent Link');
        }
    }

    public function testSearchBasicCloud(): void
    {
        $rawClient = $this->getMockBuilder(ClientRaw::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['post'])
            ->getMock();

        $rawClient->method('post')->willReturn(json_decode(self::BASIC_CLOUD_RESPONSE_JSON));

        /**
         * @var ClientRaw $rawClient
         */
        $response = (new Issue($rawClient, false))->search("jql here");
        foreach ($response as $issue) {
            $this->assertNotNull($issue->names ?? null);
            $this->assertSame('Summary', $issue->names->summary ?? null);
            $this->assertSame('Parent Link', $issue->names->customfield_18762 ?? null);
        }
    }

    /**
     * @dataProvider searchModeRoutingDataProvider
     */
    public function testSearchModeRouting(
        string $mode,
        bool $isCloudJira,
        int $enhancedCalls,
        int $legacyCalls,
        ?\Throwable $enhancedCallError = null,
        ?string $expectedExceptionClass = null,
        ?string $expectedExceptionMessage = null,
    ): void {
        $jql        = "some JQL";
        $fields     = ['summary', 'customfield_18762'];
        $expand     = ['names'];
        $maxResults = 1000;
        $startAt    = 0;

        $rawClient = $this->getMockBuilder(ClientRaw::class)
            ->disableOriginalConstructor()
            ->getMock();

        $issueMock = $this->getMockBuilder(Issue::class)
            ->setConstructorArgs([$rawClient, false])
            ->onlyMethods(['searchLegacy', 'searchEnhanced', 'isCloudJira'])
            ->getMock();
        $issueMock
            ->expects($this->exactly($enhancedCalls))
            ->method('searchEnhanced')
            ->with($jql, $fields, $expand, $maxResults, $startAt)
            ->will(
                $enhancedCallError
                    ? $this->throwException($enhancedCallError)
                    : $this->returnValue([])
            );
        $issueMock
            ->expects($this->exactly($legacyCalls))
            ->method('searchLegacy')
            ->with($jql, $fields, $expand, $maxResults, $startAt)
            ->willReturn([]);
        $issueMock
            ->method('isCloudJira')
            ->willReturn($isCloudJira);

        /**
         * @var Issue $issueMock
         */
        $issueMock->setSearchMode($mode);

        if ($expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }
        if ($expectedExceptionMessage) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }
        $issueMock->search($jql, $fields, $expand, $maxResults, $startAt);
    }

    public static function searchModeRoutingDataProvider(): array
    {
        $change2046Error = <<<'ERROR'
            Jira REST API call error: The requested API has been removed. Please migrate
            to the /rest/api/3/search/jql API. A full migration guideline is available
            at https://developer.atlassian.com/changelog/#CHANGE-2046
            ERROR;

        return [
            'auto mode, cloud jira, enhanced call success' => [
                'mode'                     => Issue::SEARCH_MODE_AUTO,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 0,
                'enhancedCallError'        => null,
                'expectedExceptionClass'   => null,
                'expectedExceptionMessage' => null,
            ],
            'auto mode, cloud jira, enhanced call fails with CHANGE-2046' => [
                'mode'                     => Issue::SEARCH_MODE_AUTO,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 1,
                'enhancedCallError'        => new \Badoo\Jira\REST\Exception($change2046Error),
                'expectedExceptionClass'   => null,
                'expectedExceptionMessage' => null,
            ],
            'auto mode, cloud jira, enhanced call fails with code 404' => [
                'mode'                     => Issue::SEARCH_MODE_AUTO,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 1,
                'enhancedCallError'        => new \Badoo\Jira\REST\Exception('not found', 404),
                'expectedExceptionClass'   => null,
                'expectedExceptionMessage' => null,
            ],
            'auto mode, cloud jira, enhanced call fails with code 410' => [
                'mode'                     => Issue::SEARCH_MODE_AUTO,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 1,
                'enhancedCallError'        => new \Badoo\Jira\REST\Exception('gone', 410),
                'expectedExceptionClass'   => null,
                'expectedExceptionMessage' => null,
            ],
            'auto mode, cloud jira, enhanced call fails with unknown REST error' => [
                'mode'                     => Issue::SEARCH_MODE_AUTO,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 0,
                'enhancedCallError'        => new \Badoo\Jira\REST\Exception('some jql error'),
                'expectedExceptionClass'   => \Badoo\Jira\REST\Exception::class,
                'expectedExceptionMessage' => 'some jql error',
            ],
            'auto mode, non-cloud jira' => [
                'mode'                     => Issue::SEARCH_MODE_AUTO,
                'isCloudJira'              => false,
                'enhancedCalls'            => 0,
                'legacyCalls'              => 1,
                'enhancedCallError'        => null,
                'expectedExceptionClass'   => null,
                'expectedExceptionMessage' => null,
            ],
            'enhanced mode, cloud jira, enhanced call success' => [
                'mode'                     => Issue::SEARCH_MODE_ENHANCED,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 0,
                'enhancedCallError'        => null,
                'expectedExceptionClass'   => null,
                'expectedExceptionMessage' => null,
            ],
            'enhanced mode, cloud jira, enhanced call fails' => [
                'mode'                     => Issue::SEARCH_MODE_ENHANCED,
                'isCloudJira'              => true,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 0,
                'enhancedCallError'        => new \Exception('Enhanced search failed'),
                'expectedExceptionClass'   => \Exception::class,
                'expectedExceptionMessage' => 'Enhanced search failed',
            ],
            'enhanced mode, non-cloud jira, enhanced call fails with not supported error' => [
                'mode'                     => Issue::SEARCH_MODE_ENHANCED,
                'isCloudJira'              => false,
                'enhancedCalls'            => 1,
                'legacyCalls'              => 0,
                'enhancedCallError'        => new \Exception('Enhanced search is not supported on non-cloud Jira instances'),
                'expectedExceptionClass'   => \Exception::class,
                'expectedExceptionMessage' => 'Enhanced search is not supported on non-cloud Jira instances',
            ],
        ];
    }

    /**
     * @dataProvider setSearchModeProvider
     */
    public function testSetSearchMode(
        string $mode,
        bool $expectException,
    ): void {
        $rawClient = $this->getMockBuilder(ClientRaw::class)
            ->disableOriginalConstructor()
            ->getMock();

        $issue = new Issue($rawClient, false);

        if ($expectException) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage("Invalid search mode '{$mode}'");
        }

        $issue->setSearchMode($mode);
        self::assertSame($mode, $issue->getSearchMode());
    }

    public static function setSearchModeProvider(): array
    {
        return [
            'valid mode: auto'     => [Issue::SEARCH_MODE_AUTO, false],
            'valid mode: enhanced' => [Issue::SEARCH_MODE_ENHANCED, false],
            'valid mode: legacy'   => [Issue::SEARCH_MODE_LEGACY, false],
            'invalid mode'         => ['some invalid mode', true],
            'empty mode'           => ['', true],
        ];
    }
}
