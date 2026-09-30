<?php
/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in.
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

/**
 * Required Libraries
 */

use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\Actions;
use Adlexone\support\Communications;


/**
 * action package specific constants
 */
if (!defined('NOT_BASE_URL')) {
    define('NOT_BASE_URL', 'index.php?manage=workflow');
}
/**
 * Action methods for sending an email when a custom field value changes to a defined value.
 */

/**
 * Displays the setup page for configuring the "send email upon defined custom field value change" action.
 *
 * This function generates a form for setting up or updating an action that sends an email
 * when a custom field value changes to a specified value. It dynamically populates the form
 * with existing action data if an action ID is provided.
 *
 * @param string $actionID The ID of the action to update. If empty, a new action setup form is displayed.
 * @return void
 */
function showSetupSendEmail($actionID = ''): void
{
    // Get default field values if we use this form for updating the action
    if ($actionID != '') {
        // Retrieve action information from the database
        $columnArray = array('*');
        $condition = "WHERE action_id = '" . $actionID . "'";
        $fieldValues = Database::first('action_definitions', $columnArray, $condition);
        $action = NOT_BASE_URL . '&option=update_action&action_package=Email_Notifications&descriptor_name=SendEmail&action_id=' . $actionID;
    } else {
        // Set default values for a new action
        $subject = 'Include ITEM_ID in the subject to include the item id';
        $content = 'Include ITEM_ID in the email content to include the item id';
        $action = NOT_BASE_URL . '&option=add_action&action_package=Email_Notifications&descriptor_name=SendEmail';
    }

    $actionField = Actions::startNewAction($actionID, @$fieldValues, false, false);

    // Parse action data for default values
    $fieldValueArray = explode('}-{', @$fieldValues['action_data']);
    if (count($fieldValueArray) < 2) {
        @$fieldValueArray[1] = 'Include ITEM_ID in the email subject to include the item id';
        @$fieldValueArray[2] = 'Include ITEM_ID in the email content to include the item id';
    }

    // Populate form fields
    $actionField[ACT_PAK_59] = RenderViews::buildTextInput('from_address_name', @$fieldValueArray[3]);
    $actionField[ACT_PAK_8] = RenderViews::buildTextInput('from_address', @$fieldValueArray[0]);
    $emailAddressArray = explode('}-{', @$fieldValues['action_parameters']);
    $optionArray = array('recipients', 'creator', 'owner', 'creator_owner', 'creator_groups', 'owner_groups', 'creator_owner_groups', 'groups');
    $displayNameArray = array(ACT_PAK_29, ACT_PAK_70, ACT_PAK_30, ACT_PAK_68, ACT_PAK_69, ACT_PAK_32, ACT_PAK_71, ACT_PAK_31);
    $actionField[ACT_PAK_33] = RenderViews::buildSelectDropdown('recipient_options', $optionArray, $displayNameArray, @$emailAddressArray[1]);
    $actionField[ACT_PAK_3] = RenderViews::buildTextInput('email_addresses', $emailAddressArray[0]) . ' * ' . ACT_PAK_4;
    $actionField[ACT_PAK_73] = RenderViews::buildSelectDropdown('logged_on_user', array('yes', 'no'), array(TXT_93, TXT_94), @$emailAddressArray[2]);
    $actionField[TXT_649] = RenderViews::buildTextInput('username', @$fieldValueArray[4]);
    $actionField[TXT_650] = RenderViews::buildPasswordInput('password', @$fieldValueArray[5]);
    $actionField[ACT_PAK_9] = RenderViews::buildTextInput('subject', $fieldValueArray[1]);
    $actionField[ACT_PAK_7] = RenderViews::buildTextArea('email_contents', $fieldValueArray[2], '15');
    $actionField[''] = RenderViews::buildHiddenInput('action_id', @$fieldValues['action_id']);

    $sql = "SHOW COLUMNS FROM items";
        $result = Database::rows($sql);
    $excludeArray = array('create_date', 'core_log_updated', 'item_type_id', 'creator_security', 'user_security', 'group_security');
    $dynamicValues = 'LOG_ENTRY, ITEM_CREATOR, ITEM_OWNER';
    foreach ($result as $row) {
        if (!in_array($row[0], $excludeArray)) {
            @$dynamicValues .= ', ' . strtoupper($row[0]);
        }
    }
    $actionField[ACT_PAK_60] = $dynamicValues;

    $jsFieldNameArray = "['action_name','from_address','email_addresses','subject','email_contents']";
    $jsTestTypeArray = "['','email','','','']";
    $jsErrorMsgArray = "['','" . ACT_PAK_41 . "','','','']";
    $jsRequiredMsgArray = "['" . ACT_PAK_36 . "','" . ACT_PAK_37 . "', '" . ACT_PAK_38 . "','" . ACT_PAK_39 . "', '" . ACT_PAK_40 . "']";
    $jsRequiredArray = "[true,true,false,true,true]";
    $javascript = "onClick=\"javascript:return fieldCheck('" . TXT_468 . "'," . $jsTestTypeArray . "," . $jsFieldNameArray . "," . $jsErrorMsgArray . "," . $jsRequiredMsgArray . "," . $jsRequiredArray . ");\"";

    define('BODY_CONTENT', RenderViews::buildForm(
        ACT_PAK_6,
        $action,
        $actionField,
        [
            RenderViews::buildFormButton('submit', 'submit_button', TXT_74, $javascript),
            RenderViews::buildFormButton('reset', 'reset', TXT_75),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Sends an email based on custom field values.
 *
 * This function dynamically constructs and sends an email using SMTP based on the provided
 * item ID, data array, and action parameters. It supports placeholder replacement in the
 * subject, message, and recipient fields, and resolves recipients based on predefined options.
 *
 * @param int $itemID The ID of the item triggering the email.
 * @param array $dataArray An associative array containing data for placeholder replacement.
 * @param array $preCondition Pre-action condition array (not used in this implementation).
 * @param array $triggerCondition Trigger condition array (not used in this implementation).
 * @param string $actionParameters A string defining recipient options and other parameters.
 * @param string $actionData A string defining the email's sender, subject, and message.
 * @param string $actionType (Optional) The type of action being executed.
 * @param string $requestingAction (Optional) The action requesting the email execution.
 * @return bool Returns true if the email was successfully sent, false otherwise.
 */
function executeSendEmail($itemID, $dataArray, $preCondition, $triggerCondition, $actionParameters, $actionData, $actionType = '', $requestingAction = ''): bool
{
    if (!is_array($dataArray)) return false;

    // Parse action data and parameters
    $paramaterArray = explode('}-{', $actionData);
    $toArray = explode('}-{', $actionParameters);

    // Replace placeholders in subject, message, and recipients
    $subject = str_replace('ITEM_ID', $itemID, $paramaterArray[1]);
    $message = str_replace(['ITEM_ID', 'URL'], [$itemID, FULL_SCRIPT_PATH], $paramaterArray[2]);
    $recipients = $toArray[0];

    foreach ($dataArray as $key => $value) {
        if (!is_int($key)) {
            $pattern = "/\b" . strtoupper($key) . "\b/";
            $subject = preg_replace($pattern, $value, $subject);
            $message = preg_replace($pattern, $value, $message);
            $recipients = preg_replace($pattern, $value, $recipients);
        }
    }

    // Replace ITEM_CREATOR and ITEM_OWNER placeholders
    $subject = preg_replace("/\bITEM_CREATOR\b/", getUserName($dataArray['creator_security']), $subject);
    $message = preg_replace("/\bITEM_CREATOR\b/", getUserName($dataArray['creator_security']), $message);
    $subject = preg_replace("/\bITEM_OWNER\b/", getUserName($dataArray['user_security']), $subject);
    $message = preg_replace("/\bITEM_OWNER\b/", getUserName($dataArray['user_security']), $message);

    // Resolve recipients based on the provided option
    $to = resolveRecipients($itemID, $toArray[1], $recipients, $dataArray);

    // Optionally remove the logged-in user from the recipient list
    if (isset($toArray[2]) && $toArray[2] == 'no') {
        $to = str_replace(getUserEmail($_SESSION['access_user_id']), '', $to);
    }

    $to = trim($to, ',');
    if ($to && $paramaterArray[0]) {
        $subject = '[Alexone-' . $itemID . '] ' . html_entity_decode($subject, ENT_COMPAT, 'UTF-8');
        Communications::sendSMTPMail(
            SET_SMTP_HOST,
            SET_SMTP_PORT,
            SET_SMTP_USER,
            SET_SMTP_PASSWORD,
            $paramaterArray[0],
            $subject,
            $message,
            $paramaterArray[3]
        );
    }
    return true;
}

/**
 * Retrieves the email address for a given user ID.
 *
 * @param int $userID The ID of the user.
 * @return string The email address of the user, or an empty string if not found.
 */
function getUserEmail($userID) {
    $sql = "SELECT email FROM users WHERE user_id = '$userID'";
        $result = Database::rows($sql);
    $row = $result[0] ?? null;
    return $row['email'] ?? '';
}

/**
 * Retrieves the username for a given user ID.
 *
 * @param int $userID The ID of the user.
 * @return string The username of the user, or an empty string if not found.
 */
function getUserName($userID)
{
    $sql = "SELECT user_name FROM users WHERE user_id = '$userID'";
        $result = Database::rows($sql);
    $row = $result[0] ?? null;
    return $row['user_name'] ?? '';
}

/**
 * Resolves the recipients for the email based on the specified option.
 *
 * @param int $itemID The ID of the item triggering the email.
 * @param string $option The recipient option (e.g., 'recipients', 'owner', 'creator').
 * @param string $recipients A comma-separated list of recipients.
 * @param array $dataArray An associative array containing data for recipient resolution.
 * @return string A comma-separated list of resolved email addresses.
 */
function resolveRecipients($itemID, $option, $recipients, $dataArray)
{
    switch ($option) {
        case 'recipients':
            return $recipients;
        case 'owner':
            return getUserEmail($dataArray['user_security']);
        case 'creator':
            return getUserEmail($dataArray['creator_security']);
        case 'creator_owner':
            return implode(',', array_filter([
                getUserEmail($dataArray['user_security']),
                getUserEmail($dataArray['creator_security'])
            ]));
        // Add simplified group logic as needed
        default:
            return $recipients;
    }
}

/**
 * action Descriptors provide the base action name and associated information to the
 * administration_Actions controller file so it can create a package Actions list
 */
$actionName['SendEmail'] = ACT_PAK_6;
$actionDescription['SendEmail'] = ACT_PAK_11;
