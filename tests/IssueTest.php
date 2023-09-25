<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\UTests;

class IssueTest extends \PHPUnit\Framework\TestCase
{
    public static function getBaseIssue(string $key) : \stdClass
    {
        $BaseIssue = new \stdClass();
        $BaseIssue->key = $key;

        $BaseIssue->fields = new \stdClass();
        return $BaseIssue;
    }

    protected function getIssueMock(\stdClass $BaseIssue, string $key = 'ISSUE-1') : \Badoo\Jira\Issue
    {
        $IssueMockBuilder = $this->getMockBuilder(\Badoo\Jira\Issue::class);
        $IssueMockBuilder
            ->setConstructorArgs([$key])
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethods(['getBaseIssue']);


        $IssueMock = $IssueMockBuilder->getMock();
        $IssueMock->method('getBaseIssue')->willReturn($BaseIssue);
        $IssueMock->method('getEditMeta')->willReturn([
            "fields" => [
                "created" => [
                    "required" => false,
                    "schema" => [
                        "type" => "datetime",
                        "custom" => "com.atlassian.jira.plugin.system.customfieldtypes:datetime",
                        "customId" => 10460
                    ],
                    "name" => "Created date",
                    "fieldId" => "created",
                    "operations" => [
                        "set"
                    ]
                ]
            ]
        ]);

        /** @var \Badoo\Jira\Issue $IssueMock */
        return $IssueMock;
    }

    public function testGetFieldValue_originalValue()
    {
        $expected_value = 'value from jira';

        $Base = static::getBaseIssue('EX-1');
        $Base->fields->summary = $expected_value;

        $Issue = $this->getIssueMock($Base);

        self::assertEquals($expected_value, $Issue->getSummary());
    }

    public function summariesForCache()
    {
        return [
            'non empty string'  => ['non empty string in summary cache'],
            'empty string'      => [''],
            'null'              => [null],
        ];
    }

    /**
     * @dataProvider summariesForCache
     */
    public function testGetFieldValue_cachedValue(?string $expected_value)
    {
        $jira_value = 'value from jira';

        $Base = static::getBaseIssue('EX-1');
        $Base->fields->summary = $jira_value;

        $Issue = $this->getIssueMock($Base);

        $cacheData = new \ReflectionMethod($Issue, 'cacheData');
        $cacheData->setAccessible(true);

        $cacheData->invokeArgs($Issue, ['summary', $expected_value]);

        self::assertNotEquals($expected_value, $jira_value);
        self::assertEquals($expected_value, $Issue->getSummary());
    }

    public function datesToParse()
    {
        return [
            'empty date'        => ['', 0],
            'meaningful date'   => ['2018-06-19T12:53:31.000+0000', 1529412811],
            'null date'         => [null, 0],
        ];
    }

    /**
     * @dataProvider datesToParse
     */
    public function testGetDateField_isParsed(?string $date_to_parse, int $expected_value)
    {
        $Base = static::getBaseIssue('EX-1');
        $Base->fields->created = $date_to_parse;

        $Issue = $this->getIssueMock($Base);

        self::assertEquals($expected_value, $Issue->getCreatedDate(), 'Incorrect created date after parsing');

        $getCachedData = new \ReflectionMethod($Issue, 'getCachedData');
        $getCachedData->setAccessible(true);

        $cached = $getCachedData->invokeArgs($Issue, ['created', $expected_value]);

        self::assertEquals($expected_value, $cached, 'The created date timestamp seems not cached after parsing');
        self::assertEquals($expected_value, $Issue->getCreatedDate(), 'Strange created date timestamp after second call');
    }

    public function testGetPriority_empty()
    {
        $Base = static::getBaseIssue('EX-1');

        $Issue = $this->getIssueMock($Base);

        self::assertEquals(null, $Issue->getPriority());
    }

    public function testPartialFieldsInit()
    {
        $jira_id = 12345;
        $jira_key = 'EX-1';
        $jira_cf_value = 'value 12345';

        $cached_id = 54321;
        $cached_key = 'EX-2';
        $cached_self_link = 'https://self.issue.link/';
        $cached_cf_value = 'value 54321';

        $JiraIssue = static::getBaseIssue($jira_key);
        $JiraIssue->id = $jira_id;
        $JiraIssue->fields->customfield_12345 = $jira_cf_value;

        $IssueToCache = static::getBaseIssue($cached_key);
        $IssueToCache->id = $cached_id;
        $IssueToCache->self = $cached_self_link;
        $IssueToCache->fields->customfield_54321 = $cached_cf_value;

        $IssueMock = $this->getIssueMock($JiraIssue);

        $Issue = $IssueMock::fromStdClass($IssueToCache, ['id', 'key', 'self', 'customfield_54321']);
        $Issue->method('getBaseIssue')->willReturn($JiraIssue);

        self::assertEquals($cached_id, $Issue->getId());
        self::assertEquals($cached_key, $Issue->getKey());
        self::assertEquals($cached_cf_value, $Issue->getFieldValue('customfield_54321'));
        self::assertEquals($cached_self_link, $Issue->getSelfUrl());

        $dropCache = new \ReflectionMethod($Issue, 'dropCache');
        $dropCache->setAccessible(true);
        $dropCache->invokeArgs($Issue, []); // drop cache. We hacked getBaseIssue() and this breaks thing we try to check

        self::assertEquals($jira_cf_value, $Issue->getFieldValue('customfield_12345'));
        self::assertEquals($jira_id, $Issue->getId());
    }

    public function testUpdateKey()
    {
        $key_before_update = 'IS-1';
        $key_after_update = 'EX-1';

        $BaseIssue = $this->getBaseIssue($key_after_update);
        $Issue = $this->getIssueMock($BaseIssue, $key_before_update);

        self::assertEquals($key_before_update, $Issue->getKey());

        $Issue->updateKey();
        self::assertEquals($key_after_update, $Issue->getKey());
    }
}
