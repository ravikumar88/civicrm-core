<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * This class handles downloads of remotely-provided extensions
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class CRM_Utils_HttpClient {

  const STATUS_OK = 'ok';
  const STATUS_WRITE_ERROR = 'write-error';
  const STATUS_DL_ERROR = 'dl-error';

  /**
   * Download the remote zipfile.
   *
   * @param string $remoteFile URL of a .zip file
   * @param string $localFile path at which to store the .zip file
   * @return STATUS_OK|STATUS_WRITE_ERROR|STATUS_DL_ERROR
   */
  public static function fetch($remoteFile, $localFile) {
    require_once 'CA/Config/Curl.php';
    $caConfig = CA_Config_Curl::probe(array(
      'verify_peer' => (bool) CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME, 'verifySSL', NULL, TRUE)
    ));

    // Download extension zip file ...
    if (!function_exists('curl_init')) {
      CRM_Core_Error::fatal('Cannot install this extension - curl is not installed!');
    }
    if (preg_match('/^https:/', $remoteFile) && !$caConfig->isEnableSSL()) {
      CRM_Core_Error::fatal('Cannot install this extension - does not support SSL');
    }

    //setting the curl parameters.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remoteFile);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    if (preg_match('/^https:/', $remoteFile)) {
      curl_setopt_array($ch, $caConfig->toCurlOptions());
    }

    //follow redirects
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

    $fp = @fopen($localFile, "w");
    if (!$fp) {
      CRM_Core_Session::setStatus(ts('Unable to write to %1.<br />Is the location writable?', array(1 => $localFile)), ts('Write Error'), 'error');
      return self::STATUS_WRITE_ERROR;
    }
    curl_setopt($ch, CURLOPT_FILE, $fp);

    curl_exec($ch);
    if (curl_errno($ch)) {
      CRM_Core_Session::setStatus(ts('Unable to download extension from %1. Error Message: %2',
        array(1 => $remoteFile, 2 => curl_error($ch))), ts('Download Error'), 'error');
      return self::STATUS_DL_ERROR;
    }
    else {
      curl_close($ch);
    }

    fclose($fp);

    return self::STATUS_OK;
  }
}