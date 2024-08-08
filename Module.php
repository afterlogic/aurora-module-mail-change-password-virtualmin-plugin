<?php

/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordVirtualminPlugin;

use Aurora\Modules\Mail\Models\MailAccount;

/**
 * Allows users to change passwords on their email accounts in Virtualmin.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    public function init()
    {
        $this->subscribeEvent('Mail::Account::ToResponseArray', array($this, 'onMailAccountToResponseArray'));
        $this->subscribeEvent('ChangeAccountPassword', array($this, 'onChangeAccountPassword'));
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Adds to account response array information about if allowed to change the password for this account.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onMailAccountToResponseArray($aArguments, &$mResult)
    {
        $oAccount = $aArguments['Account'];

        if ($oAccount && $this->checkCanChangePassword($oAccount)) {
            if (!isset($mResult['Extend']) || !is_array($mResult['Extend'])) {
                $mResult['Extend'] = [];
            }
            $mResult['Extend']['AllowChangePasswordOnMailServer'] = true;
        }
    }

    /**
     * Tries to change password for account if allowed.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onChangeAccountPassword($aArguments, &$mResult)
    {
        $bPasswordChanged = false;
        $bBreakSubscriptions = false;

        $oAccount = $aArguments['Account'] instanceof MailAccount ? $aArguments['Account'] : false;
        if ($oAccount && $this->checkCanChangePassword($oAccount) && $oAccount->getPassword() === $aArguments['CurrentPassword']) {
            $bPasswordChanged = $this->changePassword($oAccount, $aArguments['NewPassword']);
            $bBreakSubscriptions = true; // break if mail server plugin tries to change password in this account.
        }

        if (is_array($mResult)) {
            $mResult['AccountPasswordChanged'] = $mResult['AccountPasswordChanged'] || $bPasswordChanged;
        }

        return $bBreakSubscriptions;
    }

    /**
     * Checks if allowed to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @return bool
     */
    protected function checkCanChangePassword($oAccount)
    {
        $bFound = in_array('*', $this->oModuleSettings->SupportedServers);

        if (!$bFound) {
            $oServer = $oAccount->getServer();

            if ($oServer && in_array($oServer->IncomingServer, $this->oModuleSettings->SupportedServers)) {
                $bFound = true;
            }
        }

        return $bFound;
    }

    /**
     * Tries to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param string $sPassword
     * @return boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    protected function changePassword($oAccount, $sPassword)
    {
        $sEmail = $oAccount->Email;
        $sPassCurr = $oAccount->getPassword();
        [$sUsername, $sDomain] = explode("@", $sEmail);

        $sVirtualminURL = rtrim($this->oModuleSettings->VirtualminURL, "/");
        $sVirtualminAdminUser = $this->oModuleSettings->VirtualminAdminUser;
        $sVirtualminAdminPass = $this->oModuleSettings->VirtualminAdminPass;

        if ($sVirtualminAdminPass && !\Aurora\System\Utils::IsEncryptedValue($sVirtualminAdminPass)) {
            $this->setConfig('VirtualminAdminPass', \Aurora\System\Utils::EncryptValue($sVirtualminAdminPass));
            $this->saveModuleConfig();
        } else {
            $sVirtualminAdminPass = \Aurora\System\Utils::DecryptValue($sVirtualminAdminPass);
        }

        if (0 === strlen($sPassword) || $sPassCurr === $sPassword) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordRejected);
        }

        $sURL = $sVirtualminURL . '/virtual-server/remote.cgi?program=modify-user&domain=' . urlencode($sDomain) . '&user=' . urlencode($sUsername) . '&pass=' . urlencode($sPassword) . '&json=1';
        $sURL = str_replace('//', '/', $sURL);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sURL);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, $sVirtualminAdminUser . ":" . $sVirtualminAdminPass);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $mResult = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($mResult === null) {
            throw new \Aurora\System\Exceptions\ApiException(0, null, "Virtualmin API failure");
        }

        if (isset($mResult["status"]) && ($mResult["status"] != "success")) {
            $sOutput = (isset($mResult["output"])) ? (": " . trim($mResult["output"])) : "";
            throw new \Aurora\System\Exceptions\ApiException(0, null, "Virtualmin API error" . $sOutput);
        }

        return true;
    }
}
