<?php
/**
 * @package Jira
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Exception;

class Issue extends \Badoo\Jira\Exception
{
    const ERROR_CODE_UNKNOWN_COMPONENT   = 0x01;
    const ERROR_CODE_UNKNOWN_FIELD       = 0x02;
    const ERROR_CODE_UNKNOWN_TYPE        = 0x03;
    const ERROR_CODE_UNKNOWN_PRIORITY    = 0x04;
    const ERROR_CODE_UNKNOWN_FIELD_VALUE = 0x05;

    const ERROR_CODE_UNKNOWN_ERROR       = 0xff;
}
