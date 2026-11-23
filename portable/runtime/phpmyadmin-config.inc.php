<?php
/*
 * phpMyAdmin settings for the portable Student Registration System.
 * Copied to runtime\phpmyadmin\config.inc.php by build-portable.ps1.
 * phpMyAdmin is only reachable from this computer (127.0.0.1).
 */
$cfg['blowfish_secret'] = 'srms-portable-local-only-0123456789abcdef'; // required by phpMyAdmin, 32+ chars

$i = 1;
$cfg['Servers'][$i]['auth_type']       = 'config';   // opens without a login screen
$cfg['Servers'][$i]['host']            = '127.0.0.1';
$cfg['Servers'][$i]['port']            = getenv('SRMS_DB_PORT') ?: '3306';
$cfg['Servers'][$i]['user']            = 'root';
$cfg['Servers'][$i]['password']        = '';
$cfg['Servers'][$i]['AllowNoPassword'] = true;
$cfg['Servers'][$i]['only_db']         = ['srms'];   // show only the project's database

$cfg['TempDir']                         = dirname(__DIR__) . '/tmp/pma';
$cfg['VersionCheck']                    = false;      // works offline
$cfg['SendErrorReports']                = 'never';
$cfg['PmaNoRelation_DisableWarning']    = true;
$cfg['ShowPhpInfo']                     = false;
$cfg['DefaultLang']                     = 'en';
$cfg['NavigationTreeEnableGrouping']    = false;
