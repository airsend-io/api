<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Validators;

use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use GUMP;
use CodeLathe\Core\Objects\Path;

use HTMLPurifier;
use HTMLPurifier_Config;
use Psr\Log\LoggerInterface;

function endsWith($a_path, $a_ext, $size = 0)
{
    if ($size == 0)
        $size = strlen($a_ext);
    return $a_ext === "" || substr($a_path, -$size) === $a_ext;
}

InputValidator::add_validator("valid_fsname", function($field, $input, $param = NULL)  {
    $fsname = $input[$field];

    if ($fsname == ".." || $fsname == ".")
        return false;

    if (preg_match('/[^\PC\s]/u', $fsname))
        return false;

    if (preg_match('#[\\\\:*?"<>|/]#', $fsname) || endsWith($fsname,'.'))
        return false;

    return true;
}, "Invalid Name");

InputValidator::add_validator("valid_channel_name", function($field, $input, $param = NULL)  {
    $fsname = $input[$field];

    if (strlen($fsname) > 50) {
        return false;
    }

    if ($fsname == ".." || $fsname == ".") {
        return false;
    }

    if (preg_match('/[^\PC\s]/u', $fsname)) {
        return false;
    }

    if (preg_match('#[\\\\:*?"<>|/]#', $fsname) || endsWith($fsname,'.')) {
        return false;
    }



    return true;
}, "Invalid Channel Name (Name longer than 50 characters or contains invalid characters)");




InputValidator::add_validator("valid_comma_sep_email_list", function($field, $input, $param = NULL)  {

    $emailslist = $input[$field];

    $emails = explode(',', $emailslist);
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            return false;
        }
    }

    return true;
}, "Invalid Email List");



InputValidator::add_validator("valid_action_user_ids", function($field, $input, $param = NULL)  {

    $userids = $input[$field];
    if (!empty($userids)) {
        $arr = explode(',', $userids);
        foreach ($arr as $uid) {
            if (!filter_var($uid, FILTER_VALIDATE_INT)) {
                return false;
            }
        }
    }
    return true;
}, "Invalid User List");



InputValidator::add_validator("valid_comma_sep_email_or_phone_list", function($field, $input, $param = NULL)  {

    $emailOrPhones = $input[$field];

    $items = explode(',', $emailOrPhones);
    $email_pattern = '/^[_a-z0-9-]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i';

    foreach ($items as $item) {
        if (!filter_var($item, FILTER_VALIDATE_EMAIL) &&
            !preg_match('/^[0-9\-\(\)\/\+\s]*$/', $item, $matches)) {
            return false;
        }
    }

    return true;
}, "Invalid Phone or Email List");


InputValidator::add_validator("valid_phone_number", function($field, $input, $param = NULL)  {

    $numbers = $input[$field];
    // TODO: use num verify to check the phone number
    if (!preg_match('/^[0-9\-\(\)\/\+\s]*$/',$numbers, $matches)) {
        return false;
    }
    if ($matches[0] != $numbers)
        return false;

    return true;
}, "Invalid Phone Number");

InputValidator::add_validator("valid_email_or_phone_number", function($field, $input, $param = NULL)  {

    $emailOrPhone = $input[$field];

    //if (!empty($emailOrPhone))
    {
        if (filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        // TODO: use num verify to check the phone number
        if (preg_match('/^[0-9\-\(\)\/\+\s]*$/', $emailOrPhone, $matches)) {
            return true;
        }
    }

    return false;
}, "Invalid Email or Phone Number");

InputValidator::add_validator("valid_user_display_name", function($field, $input, $param = NULL)  {

    $data = $input[$field];

    //if (!empty($emailOrPhone))
    {
        if (filter_var($data, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        // TODO: use num verify to check the phone number
        if (preg_match('/^[0-9\-\(\)\/\+\s]*$/', $data, $matches)) {
            return true;
        }

        // check display name characters allows alpha space and apostrophe
        $pattern = '/^[A-Za-z][A-Za-z\'\-]+([\ A-Za-z][A-Za-z\'\-]+)*/';

        // to skip apostrophe use this
        //$pattern = '/^[A-Za-z]+([\ A-Za-z]+)*/';
        if (preg_match($pattern, $data, $matches)) {
            return true;
        }

    }

    return false;
}, "Invalid User Display Name");


InputValidator::add_validator("valid_fspath_allow_empty", function($field, $input, $param = NULL) {
    $fspath = $input[$field];

    // ... Empty path is valid under some conditions
    if ($fspath == "" || $fspath == '/cf')
        return true;

    $path = Path::createFromPath($fspath);
    if (!$path->isValid())
        return false;

    if (!preg_match('/^\/f\/[\d]+/', $fspath) && !preg_match('/^\/cf\/[\d]+/', $fspath) && !preg_match('/^\/wf\/[\d]+/', $fspath))
        return false;

    return true;
}, "Invalid Path");

InputValidator::add_validator("valid_fspath", function($field, $input, $param = NULL) {
    $fspath = $input[$field];

    $path = Path::createFromPath($fspath);
    if (!$path->isValid())
        return false;

    if (!preg_match('/^\/f\/[\d]+/', $fspath) && !preg_match('/^\/cf\/[\d]+/', $fspath) && !preg_match('/^\/wf\/[\d]+/', $fspath))
        return false;

    return true;
}, "Invalid Path");

InputValidator::add_filter("xss", function($value, $params = NULL) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.SerializerPath', CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'scratch'.DIRECTORY_SEPARATOR.'tmp');
    $purifier = new HTMLPurifier($config);
    $purified = $purifier->purify($value);
    if (strlen($purified) != strlen($value) ) {
        $logger = ContainerFacade::get(LoggerInterface::class);
        //$logger->info("Warning: XSS Cleaned Input: Orig Size: ".strlen($value). " -> New Size: ".strlen($purified).' '.$value ."=>".$purified);
        // ... Any HTML Character entity replacement should be allowd
        if (htmlspecialchars($value) == $purified) {
            $purified = $value;
            $logger->info("Warning: Allow HTML Entities: ".$value ."=>".$purified);
        }
    }
    return $purified;
});



InputValidator::add_validator('valid_notification_option', function ($value, $params = []) {
    return in_array($params[$value], array_keys(User::NOTIFICATIONS_CONFIG_MAP));
}, 'Invalid notification option.');

InputValidator::add_filter('integer', function ($value, $params = []) {
    return (int) $value;
});

InputValidator::add_filter('filter_chat_message', function ($value, $params = []) {

    if (strpos($value, '```') !== false) {
        return (string) $value;
    }

    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.SerializerPath', CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'scratch'.DIRECTORY_SEPARATOR.'tmp');
    $purifier = new HTMLPurifier($config);
    $purified = $purifier->purify($value);
    if (strlen($purified) != strlen($value) ) {
        $logger = ContainerFacade::get(LoggerInterface::class);
        //$logger->info("Warning: XSS Cleaned Input: Orig Size: ".strlen($value). " -> New Size: ".strlen($purified).' '.$value ."=>".$purified);
        // ... Any HTML Character entity replacement should be allowd
        if (htmlspecialchars($value) == $purified) {
            $purified = $value;
            $logger->info("Warning: Allow HTML Entities: ".$value ."=>".$purified);
        }
    }
    return $purified;

});

InputValidator::add_validator("valid_email_or_phone_number_or_name", function($field, $input, $param = NULL)  {

    $data = $input[$field];

    //if (!empty($emailOrPhone))
    {
        if (filter_var($data, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        // TODO: use num verify to check the phone number
        if (preg_match('/^[0-9\-\(\)\/\+\s]*$/', $data, $matches)) {
            return true;
        }


    }

    return false;
}, "Invalid Email or Phone Number");

InputValidator::add_validator('date_or_timestamp', function ($field, $input, $param = null) {
    $value = $input[$field];

    // if the value is numeric, check if it's an integer (which is enough to consider it a valid timestamp)
    if (is_numeric($value)) {
        return ((int) $value) == $value;
    }

    // if it's not numeric, match agains a dete regex
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', trim($value));
}, 'Invalid date format.');

/**
 * Class InputValidator
 * Uses Wixel/GUMP to validate inputs
 * For a full list of available validators please see https://github.com/Wixel/GUMP
 * @package CodeLathe\Core\Validators
 */
class InputValidator extends GUMP
{
    protected $validationMap;

    public function __construct($lang = 'en')
    {
        parent::__construct($lang);
        $this->validationMap = [
            'user' => ['v_emailOrPhone', 'f_emailOrPhone'],
            'users' => ['v_emailOrPhone_list', 'f_emailOrPhone_list'],
            'name' => ['v_user_name', 'f_user_name'],
            'password' => ['v_password', 'f_password'],
            'current_password' => ['v_password', 'f_password'],
            'new_password' => ['v_password', 'f_password'],
            'email' => ['v_email', 'f_email'],
            'topic_name' => ['v_topicname', 'f_topicname'],
            'message' => ['v_message', 'f_message'],
            'fsparent' => ['v_fspath', 'f_fspath'],
            'fsname' => ['v_fsname', 'f_fsname'],
            'fspath' => ['v_fspath', 'f_fspath'],
            'fspath_allow_empty' => ['v_fspath_allow_empty', 'f_fspath'],
            'fsfrompath' => ['v_fspath', 'f_fspath'],
            'fstopath' => ['v_fspath', 'f_fspath'],
            'channel_name' => ['v_cname', 'f_cname'],
            'team_id' => ['v_teamid', 'f_teamid'],
            'channel_id' => ['v_channelid', 'f_channelid'],
            'call_channel_id' => ['v_call_channelid', 'f_call_channelid'],
            'call_hash' => ['v_call_hash', 'f_call_hash'],
            'rtm_token' => ['v_rtm_token', 'f_rtm_token'],
            'userid' => ['v_userid', 'f_userid'],
            'text' => ['v_message_text', 'f_message_text'],
            'emails' => ['v_email_list', 'f_email_list'],
            'user_id' => ['v_userid', 'f_userid'],
            'start' => ['v_start', 'f_start'],
            'limit' => ['v_limit', 'f_limit'],
            'limit_newer' => ['v_limit', 'f_limit'],
            'scope' => ['v_scope', 'f_scope'],
            'complete' => ['v_complete', 'f_complete'],
            'versionid' => ['v_versionid', 'f_versionid'],
            'cursor' => ['v_cursor', 'f_cursor'],
            'twidth' => ['v_pixelsize', 'f_pixelsize'],
            'theight' => ['v_pixelsize', 'f_pixelsize'],
            'reset_code' => ['v_resetCode', 'f_resetCode'],
            'opt_email' => ['v_optionalEmail', 'f_optionalEmail'],
            'opt_user_id' => ['v_optionalUserid', 'f_userid'],
            'opt_phone' => ['v_optionalPhone', 'f_optionalPhone'],
            'message_id' => ['v_messageid', 'f_messageid'],
            'attachments'=> ['v_attachments', 'f_attachments'],
            'quote_message_id'=> ['v_quote_message_id', 'f_quote_message_id'],
            'user_signature' => ['v_user_signature', 'f_user_signature'],
            'emoji_value' => ['v_emoji_value', 'f_emoji_value'],
            'search' => ['v_search', 'f_search'],
            'phone' => ['v_phone', 'f_phone'],
            'image_class' => ['v_image_class', 'f_image_class'],
            'keyword' => ['v_noValidation','f_trimSanitize'],
            'account_status' => ['v_optionalInteger', 'f_trimSanitize'],
            'approval_status' => ['v_optionalInteger', 'f_trimSanitize'],
            'user_role' => ['v_optionalInteger', 'f_trimSanitize'],
            'trust_level' => ['v_optionalInteger', 'f_trimSanitize'],
            'is_locked' => ['v_optionalInteger', 'f_trimSanitize'],
            'is_email_verified' => ['v_optionalInteger', 'f_trimSanitize'],
            'is_phone_verified' => ['v_optionalInteger', 'f_trimSanitize'],
            'is_auto_pwd' => ['v_optionalInteger', 'f_trimSanitize'],
            'channel_status' => ['v_optionalInteger', 'f_trimSanitize'],
            'alert_id' => ['v_alertid', 'f_alertid'],
            'auto_close_days' => ['v_auto_close_days', 'f_auto_close_days'],
            'exclude_closed' => ['v_exclude_closed', 'f_exclude_closed'],
            'channel_asset_type' => ['v_channel_asset_type', 'f_channel_asset_type'],
            'channel_user_role' => ['v_user_role', 'f_user_role'],
            'is_favorite' => ['v_favorite', 'f_favorite'],
            'optional_user_role' => ['v_optional_user_role', 'f_user_role'],
            'notification_option' => ['v_notification_option', 'f_notification_option'],
            'verify_code' => ['v_resetCode', 'f_resetCode'],
            'reporter_name' => ['v_reporter_name', 'f_reporter_name'],
            'reporter_email' => ['v_reporter_email', 'f_reporter_email'],
            'report_text' => ['v_report_text', 'f_report_text'],
            'query' => ['v_query', 'f_query'],
            'action_id' => ['v_actionid', 'f_actionid'],
            'action_name' => ['v_aname', 'f_aname'],
            'action_due_date' => ['v_optionaldate', 'f_optionaldate'],
            'action_desc' => ['v_adesc', 'f_adesc'],
            'action_type' => ['v_action_type', 'f_action_type'],
            'action_status' => ['v_action_status', 'f_action_status'],
            'action_user_ids' => ['v_action_user_ids', 'f_action_user_ids'],
            'clear_asset' => ['v_clear_asset', 'f_clear_asset'],
            'search_scope' => ['v_search_scope', 'f_search_scope'],
            'search_limit' => ['v_search_limit', 'f_search_limit'],
            'search_channel' => ['v_search_channel', 'f_search_channel'],
            'copy_from_channel_id' => ['v_copy_from_channel_id', 'f_copy_from_channel_id'],
            'public_hash' => ['v_public_hash', 'f_public_hash'],
            'form_title' => ['v_contact_form_title', 'f_contact_form_title'],
            'confirmation_message' => ['v_contact_form_confirmation_message', 'f_contact_form_confirmation_message'],
            'form_id' => ['v_contact_form_id', 'f_contact_form_id'],
            'form_filler_name' => ['v_form_filler_name', 'f_form_filler_name'],
            'form_filler_email' => ['v_form_filler_email', 'f_form_filler_email'],
            'form_filler_message' => ['v_form_filler_message', 'f_form_filler_message'],
            'send_email' => ['v_send_email', 'f_send_email'],
            'lock_id' => ['v_lock_id', 'f_lock_id'],
            'expires_after_sec' => ['v_expires_after_sec', 'f_expires_after_sec'],
            'remove' => ['v_remove', 'f_remove'],
            'finger_print' => ['v_finger_print', 'f_finger_print'],
            'ping_token' => ['v_ping_token', 'f_ping_token'],
            'is_public' => ['v_is_public', 'f_is_public'],
            'allowed_users' => ['v_allowed_users', 'f_allowed_users'],
            'accept' => ['v_accept_invite', 'f_accept_invite']

        ];
    }

    /**
     * @return string
     */
    public function v_accept_invite(): string
    {
        return 'required|boolean';
    }

    public function f_accept_invite(): string
    {
        return 'trim|xss';
    }

    /**
     * @return string
     */
    public function v_user_name(): string
    {
        return 'valid_user_display_name';
    }

    public function f_user_name(): string
    {
        return 'trim|xss';
    }

    public function v_user_signature(): string
    {
        return 'required';
    }

    public function f_user_signature(): string
    {
        return 'trim|xss';
    }

    public function v_ping_token(): string
    {
        return 'required';
    }

    public function f_ping_token(): string
    {
        return 'trim|xss';
    }

    public function v_finger_print(): string
    {
        return 'required';
    }

    public function f_finger_print(): string
    {
        return 'trim|xss';
    }


    public function v_password(): string
    {
        return 'required|max_len,100|min_len,6';
    }

    public function f_password(): string
    {
        return 'trim';
    }

    public function v_email(): string
    {
        return 'valid_email';
    }

    public function f_email(): string
    {
        return 'trim|sanitize_email|xss';
    }

    public function v_topicname(): string
    {
        return 'required';
    }

    public function f_topicname(): string
    {
        return 'trim|xss';
    }


    public function v_message(): string
    {
        return 'required';
    }

    public function f_message(): string
    {
        return 'trim|xss';
    }

    public function v_fspath(): string
    {
        return 'required|valid_fspath';
    }

    public function v_fspath_allow_empty(): string
    {
        return 'valid_fspath_allow_empty';
    }


    public function f_fspath(): string
    {
        return 'trim|xss';
    }

    public function v_fsname(): string
    {
        return 'required|valid_fsname';
    }

    public function f_fsname(): string
    {
        return 'trim|xss';
    }

    public function v_cname(): string
    {
        return 'required|valid_channel_name';
    }

    public function f_cname(): string
    {
        return 'trim|xss';
    }

    public function f_channel_asset_type(): string
    {
        return 'trim|xss';
    }

    public function v_channel_asset_type(): string
    {
        return 'required|contains, logo background';
    }

    public function v_teamid(): string
    {
        return '';
    }

    public function f_teamid(): string
    {
        return 'trim';
    }

    public function v_search(): string
    {
        return 'required';
    }

    public function f_search(): string
    {
        return 'trim|xss';
    }

    public function v_channelid(): string
    {
        return 'integer|required';
    }

    public function f_channelid(): string
    {
        return 'trim|xss';
    }

    public function v_call_channelid(): string
    {
        return 'integer';
    }

    public function f_call_channelid(): string
    {
        return 'trim|xss';
    }

    public function v_copy_from_channel_id(): string
{
    return 'integer';
}

    public function f_copy_from_channel_id(): string
    {
        return 'trim|xss';
    }

    public function v_public_hash(): string
    {
        return 'required|regex,/[a-z0-9]{32}/';
    }

    public function f_public_hash(): string
    {
        return 'trim|xss';
    }

    public function v_contact_form_id(): string
    {
        return 'required|integer';
    }

    public function f_contact_form_id(): string
    {
        return 'trim|xss';
    }

    public function v_form_filler_name(): string
    {
        return 'required';
    }

    public function f_form_filler_name(): string
    {
        return 'trim|xss';
    }

    public function v_form_filler_email(): string
    {
        return 'required|valid_email';
    }

    public function f_form_filler_email(): string
    {
        return 'trim|xss';
    }

    public function v_form_filler_message(): string
    {
        return 'required';
    }

    public function f_form_filler_message(): string
    {
        return 'trim|xss';
    }

    public function v_contact_form_title(): string
    {
        return 'required';
    }

    public function f_contact_form_title(): string
    {
        return 'trim|xss';
    }

    public function v_contact_form_confirmation_message(): string
    {
        return 'required';
    }

    public function f_contact_form_confirmation_message(): string
    {
        return 'trim|xss';
    }


    public function v_userid(): string
    {
        return 'required';
    }

    public function f_userid(): string
    {
        return 'trim|xss';
    }

    public function v_alertid(): string
    {
        return 'required|integer';
    }

    public function f_alertid(): string
    {
        return 'trim';
    }


    public function v_optionalUserid(): string
    {
        return 'integer';
    }

    public function v_message_text(): string
    {
        return 'chat_text';
    }

    public function f_message_text(): string
    {
        return 'filter_chat_message';
    }

    function  v_email_list(): string
    {
        return 'valid_comma_sep_email_list';
    }

    public function f_email_list(): string
    {
        return 'trim|xss';
    }

    public function f_aname(): string
    {
        return 'trim|xss';
    }

    public function v_aname(): string
    {
        return 'min_len,1';
    }

    public function f_adesc(): string
    {
        return 'trim|xss';
    }

    public function v_adesc(): string
    {
        return 'min_len,0';
    }


    public function f_optionaldate() : string
    {
        return 'trim|xss';
    }

    public function v_optionaldate() : string
    {
        return 'date_or_timestamp';
    }


    public function v_actionid(): string
    {
        return 'required|integer';
    }

    public function f_actionid(): string
    {
        return 'trim|xss';
    }

    public function v_start(): string
    {
        return 'integer';
    }

    public function f_start(): string
    {
        return 'trim|xss';
    }

    public function v_complete(): string
    {
        return 'integer';
    }

    public function f_complete(): string
    {
        return 'trim|xss';
    }

    public function v_limit(): string
    {
        return 'integer';
    }

    public function f_limit(): string
    {
        return 'trim|xss';
    }

    public function v_scope(): string
    {
        return 'alpha_numeric';
    }

    public function f_scope(): string
    {
        return 'trim|xss';
    }

    public function v_versionid(): string
    {
        return 'min_len,6';
    }

    public function f_versionid(): string
    {
        return 'trim|xss';
    }
    public function v_cursor(): string
    {
        return 'alpha_numeric';
    }

    public function f_cursor(): string
    {
        return 'trim|xss';
    }

    public function v_pixelsize(): string
    {
        return 'required|integer';
    }

    public function f_pixelsize(): string
    {
        return 'trim|xss';
    }

    public function v_resetCode(): string
    {
        return 'required|alpha_numeric';
    }

    public function f_resetCode(): string
    {
        return 'trim';
    }

    public function v_optionalEmail(): string
    {
        return 'valid_email';
    }

    public function f_optionalEmail(): string
    {
        return 'trim|sanitize_email|xss';
    }

    public function v_optionalPhone(): string
    {
        return 'valid_phone_number';
    }

    public function f_optionalPhone(): string
    {
        return 'trim|xss';
    }

    public function v_phone(): string
    {
        return 'valid_phone_number';
    }

    public function f_phone(): string
    {
        return 'trim|xss';
    }


    public function v_messageid(): string
    {
        return 'required|integer';
    }

    public function f_messageid(): string
    {
        return 'trim|xss';
    }

    public function v_quote_message_id(): string
    {
        return 'integer';
    }

    public function f_quote_message_id(): string
    {
        return 'trim|xss';
    }


    public function v_attachments(): string
    {
        return 'encoded_json_string';
    }

    public function f_attachments(): string
    {
        return 'trim|xss';
    }


    public function validate_chat_text($field, $input, $param = null)
    {
        // TODO: Filter chat text?
        // Nothing to filter as of yet.
    }

    public function v_emoji_value(): string
    {
        return 'required';
    }

    public function f_emoji_value(): string
    {
        return 'trim|xss';
    }

    public function v_image_class(): string
    {
        return 'required';
    }

    public function f_image_class(): string
    {
        return 'trim';
    }

    public function v_noValidation() : string
    {
        return '';
    }

    public function f_trimSanitize() : string
    {
        return 'trim|xss';
    }

    public function v_optionalInteger() : string
    {
        return 'integer';
    }

    public function v_emailOrPhone() : string
    {
        return 'valid_email_or_phone_number';
    }

    public function f_emailOrPhone(): string
    {
        return 'trim|xss';
    }

    function  v_emailOrPhone_list(): string
    {
        return 'valid_comma_sep_email_or_phone_list';
    }

    public function f_emailOrPhone_list(): string
    {
        return 'trim|xss';
    }

    public function v_auto_close_days(): string
    {
        return 'integer';
    }

    public function f_auto_close_days(): string
    {
        return 'trim|xss';
    }

    public function v_exclude_closed(): string
    {
        return 'boolean';
    }

    public function f_exclude_closed(): string
    {
        return 'trim|xss';
    }

    public function v_favorite(): string
    {
        return 'required|boolean';
    }

    public function f_favorite(): string
    {
        return 'trim|xss';
    }


    public function v_user_role(): string
    {
        return 'required|contains,100 50 30 20 10';
    }

    public function f_user_role(): string
    {
        return 'trim|xss';
    }

    public function v_optional_user_role(): string
    {
        return 'contains,100 50 20 10';
    }

    public function f_notification_option():string
    {
        return 'trim|xss|integer';
    }

    public function v_notification_option(): string
    {
        return 'required|integer|valid_notification_option';
    }

    public function v_reporter_name(): string
    {
        return 'required';
    }

    public function f_reporter_name(): string
    {
        return 'trim|xss';
    }

    public function v_reporter_email(): string
    {
        return 'required';
    }

    public function f_reporter_email(): string
    {
        return 'trim|xss';
    }

    public function v_report_text(): string
    {
        return 'required';
    }

    public function f_report_text(): string
    {
        return 'trim|xss';
    }

    public function v_query(): string
    {
        return 'required';
    }

    public function f_query(): string
    {
        return 'trim|xss';
    }

    public function v_action_type(): string
    {
        return 'required|contains,1 2 3 4';
    }

    public function f_action_type(): string
    {
        return 'trim|xss|integer';
    }

    public function v_action_status(): string
    {
        return 'contains,0 1';
    }

    public function f_action_status(): string
    {
        return 'trim|xss|integer';
    }

    public function v_action_user_ids(): string
    {
        return 'valid_action_user_ids';
    }

    public function f_action_user_ids(): string
    {
        return 'trim|xss';
    }


    public function v_clear_asset(): string
    {
        return 'contains,true false 0 1';
    }

    public function f_clear_asset(): string
    {
        return 'trim|xss';
    }

    public function v_search_scope(): string
    {
        return 'contains,message action file user channel';
    }

    public function f_search_scope(): string
    {
        return 'trim|xss';
    }

    public function v_search_limit(): string
    {
        return 'integer';
    }

    public function f_search_limit(): string
    {
        return 'trim|xss';
    }

    public function v_search_channel(): string
    {
        return 'integer';
    }

    public function f_search_channel(): string
    {
        return 'trim|xss';
    }

    public function v_send_email(): string
    {
        return 'contains,true false 0 1';
    }

    public function f_send_email(): string
    {
        return 'trim|xss';
    }

    public function v_lock_id(): string
    {
        return 'integer';
    }

    public function f_lock_id(): string
    {
        return 'trim|xss';
    }

    public function v_expires_after_sec(): string
    {
        return 'integer';
    }

    public function f_expires_after_sec(): string
    {
        return 'trim|xss';
    }

    public function v_remove(): string
    {
        return 'contains,true false 0 1';
    }

    public function f_remove(): string
    {
        return 'trim|xss';
    }

    public function v_is_public(): string
    {
        return 'boolean|required';
    }

    public function f_is_public(): string
    {
        return 'trim|xss';
    }

    function  v_allowed_users(): string
    {
        return 'valid_action_user_ids';
    }

    public function f_allowed_users(): string
    {
        return 'trim|xss';
    }

    function  v_call_hash(): string
    {
        return 'required';
    }

    public function f_call_hash(): string
    {
        return 'trim|xss';
    }

    function  v_rtm_token(): string
    {
        return 'required';
    }

    public function f_rtm_token(): string
    {
        return 'trim|xss';
    }

    /**
     * Sample Custom Filter Function for ChannelIDs
     * @param $value
     * @param null $param
     */
    public function filter_channelid($value, $param = null)
    {
        // does nothing
    }

    /**
     * Sample Custom Validator for ChannelIDs
     * @param $field
     * @param $input
     * @param null $param
     */
    public function validate_channelid($field, $input, $param = null)
    {
        // does nothing
    }


    public function validate_encoded_json_string($field, $input, $param = null)
    {
        $json_enc = $input[$field];
        try
        {
            $ar = json_decode($json_enc,true);
        }
        catch (\Exception $ex) {
            return false;
        }

        return true;
    }


    public function setupRules(array $fields, array $field_name_mapping = null): void
    {
        $validationRules = array();
        $filterRules = array();
        foreach ($fields as $item) {
            $mapping = $item;
            if (isset($field_name_mapping))
            {
                if (isset($field_name_mapping[$item]))
                    $mapping = $field_name_mapping[$item];
            }

            if (isset($this->validationMap[$mapping])) {
                $validationRules[$item] = call_user_func(array($this, $this->validationMap[$mapping][0]));
                $filterRules[$item] = call_user_func(array($this, $this->validationMap[$mapping][1]));
            } else {
                // Should we throw here ?
                throw new \Exception("Validator rule for '$item' not found, add it first to the validation map");
            }
        }

        if (count($validationRules) > 0) {
            $this->validation_rules($validationRules);
        }
        if (count($filterRules) > 0) {
            $this->filter_rules($filterRules);
        }
    }
}

