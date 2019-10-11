<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

$Jira = \Badoo\Jira\REST\Client::instance();
$Jira->setJiraUrl('https://jira.localhost/');
$Jira->setAuth('user', 'password');

$Request = new \Badoo\Jira\Issue\CreateRequest('SMPL', 'Task');
$Request
    ->setSummary('Summary')
    ->setDescription('Description')
    ->setAssignee('username')
    ->addComponents('Component1', 'Component2', 12345)
    ->setDateField('customfield_10500', time())
    ->setDateField('My custom field name', 'yesterday')
    ->setSecurityLevel(123)
    ->setLabels('label1', 'label2', 'label3')
    ->setFieldValue('My custom field name 2', 'some value here')
    ->setFieldValue('customfield_10200', ['select1', 'select2'])
    ->setFieldValue('another custom name', 'radio1');

// ...

$Issue = $Request->create();

print_r(
    [
        'id' => $Issue->getId(),
        'key' => $Issue->getKey(),
        'summary' => $Issue->getSummary(),
        'labels' => $Issue->getLabels(),
        // ...
    ]
);
