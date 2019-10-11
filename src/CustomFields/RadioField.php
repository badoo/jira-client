<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class RadioButtons
 * @package Badoo\Jira\CustomFields\Abstracts
 *
 * Wrapper class for 'radio' type custom firld
 *
 * Actually I can't find any difference between RadioButtons and SingleSelectField in terms of interaction with API
 */
abstract class RadioField extends SingleSelectField
{
}
