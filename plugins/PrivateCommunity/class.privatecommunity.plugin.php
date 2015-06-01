<?php if (!defined('APPLICATION')) exit();
/**
 * PrivateCommunity Plugin.
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package PrivateCommunity
 */

// Define the plugin:
$PluginInfo['PrivateCommunity'] = array(
    'Name' => 'Private Community',
    'Description' => 'Adds an option to Roles & Permissions to make all pages only visible for signed-in community members.',
    'Version' => '1.0',
    'Author' => "Mark O'Sullivan",
    'AuthorEmail' => 'mark@vanillaforums.com',
    'AuthorUrl' => 'http://markosullivan.ca',
    'SettingsUrl' => '/dashboard/role',
);

/**
 * Class PrivateCommunityPlugin
 */
class PrivateCommunityPlugin extends Gdn_Plugin {

    /**
     *
     *
     * @param $Sender
     */
    public function RoleController_AfterRolesInfo_Handler($Sender) {
        if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
            return;

        $Private = C('Garden.PrivateCommunity');
        echo '<div style="padding: 10px 0;">';
        $Style = array('style' => 'background: #ff0; padding: 2px 4px; margin: 0 10px 2px 0; display: inline-block;');
        if ($Private) {
            echo Wrap('Your community is currently <strong>PRIVATE</strong>.', 'span', $Style);
            echo Wrap(Anchor('Switch to PUBLIC', 'settings/privatecommunity/on/'.Gdn::Session()->TransientKey(), 'SmallButton').'(Everyone will see inside your community)', 'div');
        } else {
            echo Wrap('Your community is currently <strong>PUBLIC</strong>.', 'span', $Style);
            echo Wrap(Anchor('Switch to PRIVATE', 'settings/privatecommunity/off/'.Gdn::Session()->TransientKey(), 'SmallButton').'(Only members will see inside your community)', 'div');
        }
        echo '</div>';
    }

    /**
     *
     *
     * @param $Sender
     */
    public function SettingsController_PrivateCommunity_Create($Sender) {
        $Session = Gdn::Session();
        $Switch = GetValue(0, $Sender->RequestArgs);
        $TransientKey = GetValue(1, $Sender->RequestArgs);
        if (
            in_array($Switch, array('on', 'off'))
            && $Session->ValidateTransientKey($TransientKey)
            && $Session->CheckPermission('Garden.Settings.Manage')
        ) {
            SaveToConfig('Garden.PrivateCommunity', $Switch == 'on' ? FALSE : TRUE);
        }
        Redirect('dashboard/role');
    }

    /**
     * No setup.
     */
    public function Setup() {
    }
}
