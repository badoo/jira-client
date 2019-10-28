<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

$Jira = \Badoo\Jira\REST\Client::instance();

// Configure client here:
$Jira
    ->setJiraUrl('https://jira.example.com/')
    ->setAuth('user', 'password');

$issue_keys = [
    'SMPL-1',
    'SMPL-2',
];

function measure(\Badoo\Jira\REST\Client $Jira, string $issue_key, array $fields = [])
{
    $iterations = 10;

    $time = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $Jira->issue()->get($issue_key, $fields);
        $time += microtime(true) - $start;
    }

    return $time/$iterations;
}

$times = [];

foreach ($issue_keys as $issue_key) {
    // trigger JIRA's DB and engine to cache all data on issue for us
    // to make measurement 'fair'
    $Jira->issue()->get($issue_key);

    $times[$issue_key] = [
        'single' => measure($Jira, $issue_key, ['description']),
        'all'    => measure($Jira, $issue_key),
    ];
}

print_r($times);
