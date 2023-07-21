<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordVirtualminPlugin;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property array $SupportedServers
 * @property string $VirtualminURL
 * @property string $VirtualminAdminUser
 * @property string $VirtualminAdminPass
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "SupportedServers" => new SettingsProperty(
                [
                    "*"
                ],
                "array",
                null,
                "If IMAP Server value of the mailserver is in this list, password change is enabled for it. * enables it for all the servers.",
            ),
            "VirtualminURL" => new SettingsProperty(
                "https://192.168.0.1:10000",
                "string",
                null,
                "Defines main URL of Virtualmin installation",
            ),
            "VirtualminAdminUser" => new SettingsProperty(
                "root",
                "string",
                null,
                "Admin username of Virtualmin installation",
            ),
            "VirtualminAdminPass" => new SettingsProperty(
                "",
                "string",
                null,
                "Admin password of Virtualmin installation",
            ),
        ];
    }
}
