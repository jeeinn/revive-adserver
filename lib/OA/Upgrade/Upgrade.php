<?php

/*
+---------------------------------------------------------------------------+
| OpenX v${RELEASE_MAJOR_MINOR}                                                              |
| ======${RELEASE_MAJOR_MINOR_DOUBLE_UNDERLINE}                                                                 |
|                                                                           |
| Copyright (c) 2003-2008 m3 Media Services Ltd                             |
| For contact details, see: http://www.openx.org/                           |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

define('OA_STATUS_NOT_INSTALLED',          -1);
define('OA_STATUS_CURRENT_VERSION',         0);
define('OA_STATUS_PAN_NOT_INSTALLED',      -1);
define('OA_STATUS_PAN_CONFIG_DETECTED',     1);
define('OA_STATUS_PAN_DBCONNECT_FAILED',    2);
define('OA_STATUS_PAN_VERSION_FAILED',      3);
define('OA_STATUS_PAN_DBINTEG_FAILED',      5);
define('OA_STATUS_PAN_CONFINTEG_FAILED',    6);
define('OA_STATUS_M01_NOT_INSTALLED',      -1);
define('OA_STATUS_M01_CONFIG_DETECTED',     1);
define('OA_STATUS_M01_DBCONNECT_FAILED',    2);
define('OA_STATUS_M01_VERSION_FAILED',      3);
define('OA_STATUS_M01_DBINTEG_FAILED',      5);
define('OA_STATUS_M01_CONFINTEG_FAILED',    6);
define('OA_STATUS_MAX_NOT_INSTALLED',      -1);
define('OA_STATUS_MAX_CONFIG_DETECTED',     1);
define('OA_STATUS_MAX_DBCONNECT_FAILED',    2);
define('OA_STATUS_MAX_VERSION_FAILED',      3);
define('OA_STATUS_MAX_DBINTEG_FAILED',      5);
define('OA_STATUS_MAX_CONFINTEG_FAILED',    6);
define('OA_STATUS_OAD_NOT_INSTALLED',      -1);
define('OA_STATUS_OAD_CONFIG_DETECTED',     1);
define('OA_STATUS_OAD_DBCONNECT_FAILED',    2);
define('OA_STATUS_OAD_VERSION_FAILED',      3);
define('OA_STATUS_OAD_DBINTEG_FAILED',      5);
define('OA_STATUS_OAD_CONFINTEG_FAILED',    6);
define('OA_STATUS_CAN_UPGRADE',            10);


require_once 'MDB2.php';
require_once 'MDB2/Schema.php';

require_once MAX_PATH.'/lib/OA.php';
require_once MAX_PATH.'/lib/OA/DB.php';
require_once MAX_PATH.'/lib/OA/Dal/ApplicationVariables.php';
require_once(MAX_PATH.'/lib/OA/Upgrade/UpgradeLogger.php');
require_once(MAX_PATH.'/lib/OA/Upgrade/DB_Upgrade.php');
require_once(MAX_PATH.'/lib/OA/Upgrade/UpgradeAuditor.php');
require_once(MAX_PATH.'/lib/OA/Upgrade/DB_UpgradeAuditor.php');
require_once(MAX_PATH.'/lib/OA/Upgrade/UpgradePackageParser.php');
require_once(MAX_PATH.'/lib/OA/Upgrade/VersionController.php');
require_once MAX_PATH.'/lib/OA/Upgrade/EnvironmentManager.php';
require_once MAX_PATH.'/lib/OA/Upgrade/phpAdsNew.php';
require_once(MAX_PATH.'/lib/OA/Upgrade/Configuration.php');
require_once MAX_PATH.'/lib/OA/Upgrade/DB_Integrity.php';

require_once MAX_PATH . '/lib/OA/Permission.php';
require_once MAX_PATH . '/lib/OA/Preferences.php';


/**
 * @package    OpenXUpgrade Class
 *
 * @author     Monique Szpak <monique.szpak@openx.org>
 */
class OA_Upgrade
{
    var $upgradePath = '';

    var $message = '';

    /**
     * @var OA_UpgradeLogger
     */
    var $oLogger;

    var $oParser;
    var $oDBUpgrader;
    var $oVersioner;
    var $oAuditor;
    var $oSystemMgr;
    var $oDbh;
    var $oPAN;
    var $oConfiguration;
    var $oIntegrity;

    var $aPackageList = array();
    var $aPackage     = array();
    var $aDBPackages  = array();
    var $aDsn         = array();

    var $versionInitialApplication;
    var $versionInitialSchema = array();
    var $versionInitialAppOpenads;

    var $package_file = '';
    var $recoveryFile;
    var $nobackupsFile;
    var $postTaskFile = '';

    var $can_drop_database = false;

    var $existing_installation_status = OA_STATUS_NOT_INSTALLED;
    var $upgrading_from_milestone_version = false;
    var $aToDoList = array();

    function OA_Upgrade()
    {
        $this->upgradePath  = MAX_PATH.'/etc/changes/';
        $this->recoveryFile = MAX_PATH.'/var/RECOVER';
        $this->nobackupsFile = MAX_PATH.'/var/NOBACKUPS';
        $this->postTaskFile = MAX_PATH.'/var/TASKS.php';

        $this->oLogger      = new OA_UpgradeLogger();
        $this->oParser      = new OA_UpgradePackageParser();
        $this->oDBUpgrader  = new OA_DB_Upgrade($this->oLogger);
        $this->oAuditor     = new OA_UpgradeAuditor();
        $this->oVersioner   = new OA_Version_Controller();
        $this->oPAN         = new OA_phpAdsNew();
        $this->oSystemMgr   = new OA_Environment_Manager();
        $this->oConfiguration = new OA_Upgrade_Config();
        $this->oTable       = new OA_DB_Table();
        $this->oIntegrity   = new OA_DB_Integrity();

        if ($this->seekFantasyUpgradeFile())
        {
            $this->upgradePath  = MAX_PATH.'/etc/changesfantasy/';
        }
        $this->oDBUpgrader->path_changes = $this->upgradePath;

        $this->aDsn['database'] = array();
        $this->aDsn['table']    = array();
        $this->aDsn['database']['type']     = 'mysql';
        $this->aDsn['database']['host']     = 'localhost';
        $this->aDsn['database']['port']     = '3306';
        $this->aDsn['database']['username'] = '';
        $this->aDsn['database']['passowrd'] = '';
        $this->aDsn['database']['name']     = '';
        $this->aDsn['table']['type']        = 'InnoDB';
        $this->aDsn['table']['prefix']      = 'oa_';
    }

    /**
     * initialise a database connection
     * hook up the various components with a db object
     *
     * @param array $dsn
     * @return boolean
     */
    function initDatabaseConnection($dsn=null)
    {
        if (is_null($this->oDbh))
        {
            //$this->oDbh = OA_DB::singleton($dsn);
            $this->oDbh = OA_DB::singleton(OA_DB::getDsn());
        }
        if (PEAR::isError($this->oDbh))
        {
            $this->oLogger->log($this->oDbh->getMessage());
            $this->oDbh = null;
            return false;
        }
        if (!$this->oDbh)
        {
            $this->oLogger->log('Unable to connect to database');
            $this->oDbh = null;
            return false;
        }
        $this->oTable->oDbh = $this->oDbh;
        $this->oDBUpgrader->initMDB2Schema();
        $this->oVersioner->init($this->oDbh);
        $this->oAuditor->init($this->oDbh, $this->oLogger);
        $this->oDBUpgrader->oAuditor =& $this->oAuditor->oDBAuditor;
        $this->oDBUpgrader->doBackups = $this->_doBackups();
        $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
        $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
        return true;
    }

    /**
     * add any needed database parameter to the config array
     *
     * @param array $aConfig
     *
     * @return array
     */
    function initDatabaseParameters($aConfig)
    {
        // Check if we need to ensure to enable MySQL 4 compatibility
        if (strcasecmp($aConfig['database']['type'], 'mysql') === 0) {
            $result = $this->oDbh->exec("SET SESSION sql_mode='MYSQL40'");
            $aConfig['database']['mysql4_compatibility'] = !PEAR::isError($result);
        }

        return $aConfig;
    }

    /**
     * see the recovery file and ye may findeth
     *
     * @return boolean
     */
    function isRecoveryRequired()
    {
        return (is_array($this->seekRecoveryFile()) ? true : false);
    }

    /**
     * the recovery trigger file contains a record for each upgrade package
     * that was executed during the previous ugprade
     * this method reads that file and cycles through the upgrade audit ids
     * to retrieve, compile and execute the steps taken in reverse order
     * restoring tables that were changed and dropping tables that were added
     *
     * steps are audited and logged as per an upgrade
     *
     * @return boolean
     */
    function recoverUpgrade()
    {
        $aRecover = $this->seekRecoveryFile();
        if (is_array($aRecover))
        {
            if (!empty($aRecover))
            {
                // hmm, use canUpgrade() instead?
                $this->detectPAN();
                $this->detectMAX01();
                $this->detectMAX();
                if (!$this->initDatabaseConnection())
                {
                    return false;
                }
                $this->oDBUpgrader->prefix   = $GLOBALS['_MAX']['CONF']['table']['prefix'];
                $n = count($aRecover);
                for ($i = $n-1;$i>-1;$i--)
                {
                    $aRec = $aRecover[$i];

                    $this->oLogger->logOnly('attempting to roll back upgrade action id '.$aRec['auditId']);
                    $this->oLogger->logOnly('retrieving upgrade actions');

                    $aResult = $this->oAuditor->queryAuditByUpgradeId($aRec['auditId']);

                    if ($aResult[0]['upgrade_name'] != $aRec['package'])
                    {
                        $this->oLogger->logError('cannot recover using this recovery file: package name mismatch');
                        return false;
                    }

                    $this->package_file = $aRec['package'];
                    $this->oLogger->setLogFile($aResult[0]['logfile'].'.rollback');
                    $this->oDBUpgrader->logFile = $this->oLogger->logFile;
                    $this->oConfiguration->clearConfigBackupName();

                    $this->oLogger->logOnly('retrieved upgrade actions ok');

                    $this->oAuditor->setKeyParams(array('upgrade_name'=>$this->package_file,
                                                        'version_to'=>$aResult[0]['version_from'],
                                                        'version_from'=>$aResult[0]['version_to'],
                                                        'logfile'=>basename($this->oLogger->logFile)
                                                       )
                                                 );
                    $this->oAuditor->setUpgradeActionId();

                    $this->oLogger->log('Preparing to rollback package '.$this->package_file);
                    if (!$this->oDBUpgrader->prepRollbackByAuditId($aRec['auditId'], $versionInitialSchema, $schemaName))
                    {
                        $this->oAuditor->logAuditAction(array('description'=>'ROLLBACK FAILED',
                                                              'action'=>UPGRADE_ACTION_ROLLBACK_FAILED,
                                                              'confbackup'=>''
                                                             )
                                                       );
                        return false;
                    }
                    $this->oLogger->log('Starting to rollback package '.$this->package_file);
                    if (!$this->oDBUpgrader->rollback())
                    {
                        $this->oAuditor->logAuditAction(array('description'=>'ROLLBACK FAILED',
                                                              'action'=>UPGRADE_ACTION_ROLLBACK_FAILED,
                                                              'confbackup'=>''
                                                             )
                                                       );
                        return false;
                    }

                    if (!file_exists(MAX_PATH.'/var/UPGRADE'))
                    {
                        if (! $this->_createEmptyVarFile('UPGRADE'))
                        {
                            $this->oLogger->log('failed to replace the UPGRADE trigger file');
                        }
                    }
                    if ($this->upgrading_from_milestone_version)
                    {
                        if ( ! $this->_removeInstalledFlagFile())
                        {
                            $this->oLogger->log('failed to remove the INSTALLED flag file');
                        }
                    }
                    if (! $this->_restoreConfigBackup($aResult[0]['confbackup'], $aRec['auditId']))
                    {
                        //return false;
                        // do we really want to halt rollback because of a conf file?
                    }
                    if ($this->oVersioner->tableAppVarsExists($this->oDBUpgrader->_listTables()))
                    {
                        $product = 'oa';
                        if ($aResult[0]['version_from'] == '2.3.31-alpha-pr3')
                        {
                            $product = 'max';
                            $this->oVersioner->removeOpenadsVersion();
                            $this->oVersioner->putApplicationVersion('v0.3.31-alpha', $product);
                        }
                        else if ($aResult[0]['version_from'] == '2.1.29-rc')
                        {
                            $product = 'max';
                            $this->oVersioner->removeOpenadsVersion();
                            $this->oVersioner->putApplicationVersion('v0.1.29-rc', $product);
                        }
                        else
                        {
                            $this->oVersioner->putApplicationVersion($aResult[0]['version_from'], $product);
                        }
                        $this->oVersioner->putSchemaVersion($schemaName, $versionInitialSchema);
                    }
                    $this->oLogger->log('Finished rolling back package '.$this->package_file);
                    $this->oLogger->log('Information regarding the problems encountered during the upgrade can be found in');
                    $this->oLogger->log($aResult[0]['logfile']);
                    $this->oLogger->log('Information regarding steps taken during rollback can be found in');
                    $this->oLogger->log($this->oLogger->logFile);
                    $this->oLogger->log('Database and configuration files have been rolled back to version '.$aResult[0]['version_from']);
                    $this->oAuditor->logAuditAction(array('description'=>'ROLLBACK COMPLETE',
                                                          'action'=>UPGRADE_ACTION_ROLLBACK_SUCCEEDED,
                                                          'confbackup'=>''
                                                         )
                                                   );
                }
            }
            else
            {
                $this->oLogger->log('No valid recovery information found in var/RECOVER');
                $this->oLogger->log('It is not possible to rollback the previous upgrade');
                return false;
            }
            $this->oLogger->log('Recovery complete');
        }
        else
        {
            $this->oLogger->log('No valid recovery information found in var/RECOVER');
            return false;
        }
        $this->_pickupRecoveryFile();
        return true;
    }

    /**
     * delete the existing conf file
     * copy the backup conf file to it's old name
     * delete the backup conf file and audit
     *
     * @param string $confBackup
     * @param integer $auditId
     */
    function _restoreConfigBackup($confBackup, $auditId)
    {
        if ($confBackup)
        {
            $host = getHostName();
            $confFile = $host.'.conf.php';
            if (file_exists(MAX_PATH.'/var/'.$confFile))
            {
                if (! @unlink(MAX_PATH.'/var/'.$confFile))
                {
                    $this->oLogger->logError('failed to remove current configuration file');
                    return false;
                }
            }
            if (!file_exists(MAX_PATH.'/var/'.$confBackup))
            {
                $this->oLogger->logError('failed to find backup configuration file');
                return false;
            }
            $confOldName = substr($confBackup, strpos($confBackup,'old.')+4);
            if (substr($confOldName, -8, 4) == '.ini') {
                $confOldName = str_replace('.php','',$confOldName);
            }
            if (! copy(MAX_PATH.'/var/'.$confBackup,MAX_PATH.'/var/'.$confOldName))
            {
                return false;
            }
            $this->oLogger->log('restored config file '.$confOldName);
            if (! @unlink(MAX_PATH.'/var/'.$confBackup))
            {
                $this->oLogger->log('failed to remove backup configuration file');
                return false;
            }
            $this->oLogger->log('removed backup config file '.$confBackup);
            $this->oAuditor->updateAuditBackupConfDroppedById($auditId, 'dropped during recovery');
        }
        return true;
    }

    /**
     * return an array of system environment info
     *
     * @return array
     */
    function checkEnvironment()
    {
        return $this->oSystemMgr->checkSystem();
    }

    function getProductApplicationVersion()
    {
        $appPrefix = $this->oDbh->dbsyntax == 'pgsql' ? 'for PostgreSQL ' : '';
        switch ($this->versionInitialApplication)
        {
            case '' :
                return 'unknown version';
            case '0.100' :
                return '2.1.29-rc';
            case '200.313' :
            case '200.314' :
                return $appPrefix.'2.0.11-pr1';
            case 'v0.3.31-alpha' :
                return '2.3.31-alpha';
            default :
                return $this->versionInitialApplication;
        }
    }

    /**
     * look for existing installations (phpAdsNew, MMM, Openads)
     * retrieve details and check for errors
     *
     * @return boolean
     */
    function canUpgrade()
    {
        $strDetected       = ' configuration file detected';
        $strCanUpgrade     = 'This version can be upgraded';
        $strNoConnect      = 'Could not connect to the database';
        $strConnected      = 'Connected to the database ok';
        $strNoUpgrade      = 'This version cannot be upgraded';
        $strTableError     = 'Error accessing Database Tables';

        $this->oLogger->logClear();
        $this->oLogger->logOnly('looking for PAN');
        $this->detectPAN();
        $strProductName = MAX_PRODUCT_NAME.' '.$this->getProductApplicationVersion();
        switch ($this->existing_installation_status)
        {
            case OA_STATUS_PAN_NOT_INSTALLED:
                $this->oLogger->logOnly('PAN not detected');
                break;
            case OA_STATUS_PAN_CONFIG_DETECTED:
                $this->oLogger->logError($strProductName.$strDetected);
                break;
            case OA_STATUS_PAN_DBCONNECT_FAILED:
                $this->oLogger->logError($strProductName.$strDetected);
                $this->oLogger->logError($strNoConnect.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                break;
            case OA_STATUS_PAN_VERSION_FAILED:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strConnected.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                $this->oLogger->logError($strNoUpgrade);
                break;
            case OA_STATUS_PAN_DBINTEG_FAILED:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strConnected.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                $this->oLogger->logError($strNoUpgrade);
                return false;
            case OA_STATUS_PAN_CONFINTEG_FAILED:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strConnected.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                $this->oLogger->logError($strNoUpgrade);
                return false;
            case OA_STATUS_CAN_UPGRADE:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strCanUpgrade);
                return true;
        }

        $this->oLogger->logOnly('looking for MMM0.1');
        $this->detectMAX01();
        $strProductName = MAX_PRODUCT_NAME.' '.$this->getProductApplicationVersion();
        switch ($this->existing_installation_status)
        {
            case OA_STATUS_M01_NOT_INSTALLED:
                $this->oLogger->logOnly('MMM v0.1 not detected');
                break;
            case OA_STATUS_M01_CONFIG_DETECTED:
                if (!$this->oLogger->errorExists)
                {
                    $this->oLogger->logError($strProductName.$strDetected);
                }
                break;
            case OA_STATUS_M01_DBCONNECT_FAILED:
                if (!$this->oLogger->errorExists)
                {
                    $this->oLogger->logError($strProductName.$strDetected);
                    $this->oLogger->logError($strNoConnect.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                }
                break;
            case OA_STATUS_M01_DBINTEG_FAILED:
                return false;
            case OA_STATUS_M01_CONFINTEG_FAILED:
                return false;
            case OA_STATUS_M01_VERSION_FAILED:
                if (!$this->oLogger->errorExists)
                {
                    $this->oLogger->log($strProductName.' detected');
                    $this->oLogger->logError($strConnected.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                    $this->oLogger->logError($strNoUpgrade);
                }
                break;
            case OA_STATUS_CAN_UPGRADE:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strCanUpgrade);
                return true;
        }

        $this->oLogger->logOnly('looking for MAX0.3');
        $this->detectMAX();
        $strProductName = MAX_PRODUCT_NAME.' '.$this->getProductApplicationVersion();
        switch ($this->existing_installation_status)
        {
            case OA_STATUS_MAX_NOT_INSTALLED:
                $this->oLogger->logOnly('MMM v0.3 not detected');
                break;
            case OA_STATUS_MAX_CONFIG_DETECTED:
                $this->oLogger->logError($strProductName.$strDetected);
                break;
            case OA_STATUS_MAX_DBCONNECT_FAILED:
                $this->oLogger->logError($strProductName.$strDetected);
                $this->oLogger->logError($strNoConnect.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                break;
            case OA_STATUS_MAX_DBINTEG_FAILED:
                return false;
            case OA_STATUS_MAX_CONFINTEG_FAILED:
                return false;
            case OA_STATUS_MAX_VERSION_FAILED:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->logError($strConnected.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                $this->oLogger->logError($strNoUpgrade);
                break;
            case OA_STATUS_CAN_UPGRADE:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strCanUpgrade);
                return true;
        }

        $this->oLogger->logOnly('looking for Openads');
        $this->detectOpenads();
        $strProductName = MAX_PRODUCT_NAME.' '.$this->getProductApplicationVersion();
        switch ($this->existing_installation_status)
        {
            case OA_STATUS_OAD_NOT_INSTALLED:
                if (!$this->oLogger->errorExists)
                {
                    $this->oLogger->log('No previous version of @package    OpenXdetected');
                    return true;
                }
                break;
            case OA_STATUS_OAD_CONFIG_DETECTED:
                $this->oLogger->logError('Openads'.$strDetected);
                break;
            case OA_STATUS_OAD_DBCONNECT_FAILED:
                $this->oLogger->logError('Openads'.$strDetected);
                $this->oLogger->logError($strNoConnect.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                return false;
            case OA_STATUS_OAD_DBINTEG_FAILED:
                return false;
            case OA_STATUS_OAD_CONFINTEG_FAILED:
                return false;
            case OA_STATUS_OAD_VERSION_FAILED:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->logError($strConnected.' : '.$GLOBALS['_MAX']['CONF']['database']['name']);
                $this->oLogger->logError($strNoUpgrade);
                return false;
            case OA_STATUS_CURRENT_VERSION:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log('This version is up to date.');
                return false;
            case OA_STATUS_CAN_UPGRADE:
                $this->oLogger->log($strProductName.' detected');
                $this->oLogger->log($strCanUpgrade);
                return true;
        }
        return false;
    }

    /**
     * check existance of upgrade package file
     *
     * @return boolean
     */
    function checkUpgradePackage()
    {
        if ($this->package_file)
        {
            if (!file_exists($this->upgradePath.$this->package_file))
            {
                $this->oLogger->logError('Upgrade package file '.$this->package_file.' NOT found');
                return false;
            }
            return true;
        }
        else if ($this->existing_installation_status == OA_STATUS_NOT_INSTALLED)
        {
            return true;
        }
        $this->oLogger->logError('No upgrade package file specified');
        return false;
    }

    /**
     * search for an existing phpAdsNew installation
     *
     * @param boolean $skipIntegrityCheck
     * @return boolean
     */
    function detectPAN($skipIntegrityCheck = false)
    {
        $this->oPAN->init();
        if ($this->oPAN->detected)
        {
            $GLOBALS['_MAX']['CONF']['database'] = $this->oPAN->aDsn['database'];
            //$GLOBALS['_MAX']['CONF']['table']    = $this->oPAN->aDsn['table'];
            $this->existing_installation_status = OA_STATUS_PAN_CONFIG_DETECTED;
            if (PEAR::isError($this->oPAN->oDbh))
            {
                $this->existing_installation_status = OA_STATUS_PAN_DBCONNECT_FAILED;
                return false;
            }
            $this->oDbh =&  $this->oPAN->oDbh;
            if (!$this->initDatabaseConnection())
            {
                $this->existing_installation_status = OA_STATUS_PAN_DBCONNECT_FAILED;
                return false;
            }
            $this->versionInitialApplication = $this->oPAN->getPANversion();
            if (!$this->versionInitialApplication)
            {
                $this->existing_installation_status = OA_STATUS_PAN_VERSION_FAILED;
                return false;
            }
            $valid = ( (version_compare($this->versionInitialApplication,'200.313')==0)
                      ||
                       (version_compare($this->versionInitialApplication,'200.314')==0)
                     );
            if ($valid)
            {
//                if (!$this->initDatabaseConnection())
//                {
//                    $this->existing_installation_status = OA_STATUS_PAN_DBCONNECT_FAILED;
//                    return false;
//                }
                if ($this->oDbh->dbsyntax == 'pgsql') {
                    // @package    OpenX2.0 for PostgreSQL
                    $this->versionInitialSchema['tables_core'] = '049';
                } else {
                    // @package    OpenX2.0
                    $this->versionInitialSchema['tables_core'] = '099';
                }
                if (!$skipIntegrityCheck && !$this->_checkDBIntegrity($this->versionInitialSchema['tables_core']))
                {
                    $this->existing_installation_status = OA_STATUS_PAN_DBINTEG_FAILED;
                    return false;
                }
                if (!$skipIntegrityCheck && !$this->oPAN->checkPANConfigIntegrity($this)) {
                    $this->existing_installation_status = OA_STATUS_PAN_CONFINTEG_FAILED;
                    return false;
                }
                $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                $this->aPackageList[0] = 'openads_upgrade_2.0.11_to_2.3.32_beta.xml';
                $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                $this->upgrading_from_milestone_version = true;
                return true;
            }
            // if its not a max 0.1 installation
            if (!version_compare($this->versionInitialApplication,'200.000')<0)
            {
                $this->existing_installation_status = OA_STATUS_PAN_VERSION_FAILED;
                return false;
            }
        }
        $this->existing_installation_status = OA_STATUS_PAN_NOT_INSTALLED;
        return false;
    }

    /**
     * search for an existing MMM 0.1 installation
     * very similar to a PAN installation with config.inc.php and config table
     * schema is half way between PAN and MAX
     *
     * @param boolean $skipIntegrityCheck
     * @return boolean
     */
    function detectMAX01($skipIntegrityCheck = false)
    {
        $this->oPAN->init();
        if ($this->oPAN->detected)
        {
            $GLOBALS['_MAX']['CONF']['database'] = $this->oPAN->aDsn['database'];
            $GLOBALS['_MAX']['CONF']['table']    = $this->oPAN->aDsn['table'];
            $this->existing_installation_status = OA_STATUS_M01_CONFIG_DETECTED;
            if (PEAR::isError($this->oPAN->oDbh))
            {
                $this->existing_installation_status = OA_STATUS_M01_DBCONNECT_FAILED;
                return false;
            }
            $this->oDbh =&  $this->oPAN->oDbh;
            if (!$this->initDatabaseConnection())
            {
                $this->existing_installation_status = OA_STATUS_M01_DBCONNECT_FAILED;
                return false;
            }
            $this->versionInitialApplication = $this->oPAN->getPANversion();
            if (!$this->versionInitialApplication)
            {
                $this->existing_installation_status = OA_STATUS_M01_VERSION_FAILED;
                return false;
            }

            $valid = (version_compare($this->versionInitialApplication,'0.100')==0);
            if ($valid)
            {
                $this->versionInitialSchema['tables_core'] = '300';
                if (!$this->initDatabaseConnection())
                {
                    $this->existing_installation_status = OA_STATUS_M01_DBCONNECT_FAILED;
                    return false;
                }
                if (!$skipIntegrityCheck && !$this->_checkDBIntegrity($this->versionInitialSchema['tables_core']))
                {
                    $this->existing_installation_status = OA_STATUS_M01_DBINTEG_FAILED;
                    return false;
                }
                $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                $this->aPackageList[0] = 'openads_upgrade_2.1.29_to_2.3.32_beta.xml';
                $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                $this->upgrading_from_milestone_version = true;
                return true;
            }
            $this->existing_installation_status = OA_STATUS_M01_VERSION_FAILED;
            return false;
        }
        $this->existing_installation_status = OA_STATUS_PAN_NOT_INSTALLED;
        return false;
    }

    /**
     * search for an existing Max Media Manager installation
     *
     * @param boolean $skipIntegrityCheck
     * @return boolean
     */
    function detectMAX($skipIntegrityCheck = false)
    {
        if ($GLOBALS['_MAX']['CONF']['max']['installed'])
        {
            $this->existing_installation_status = OA_STATUS_MAX_CONFIG_DETECTED;
            if (!$this->initDatabaseConnection())
            {
                $this->existing_installation_status = OA_STATUS_MAX_DBCONNECT_FAILED;
                return false;
            }
            $this->versionInitialApplication = $this->oVersioner->getApplicationVersion('max');
            if (!$this->versionInitialApplication)
            {
                $this->existing_installation_status = OA_STATUS_MAX_VERSION_FAILED;
                return false;
            }
            $valid = (version_compare($this->versionInitialApplication,'v0.3.31-alpha')==0);
            if ($valid)
            {
                $this->versionInitialSchema['tables_core'] = '500';
                if (!$skipIntegrityCheck && !$this->_checkDBIntegrity($this->versionInitialSchema['tables_core']))
                {
                    $this->existing_installation_status = OA_STATUS_MAX_DBINTEG_FAILED;
                    return false;
                }
                $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                $this->aPackageList[0]  = 'openads_upgrade_2.3.31_to_2.3.32_beta.xml';
                $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                $this->upgrading_from_milestone_version = true;
                return true;
            }
            $this->existing_installation_status = OA_STATUS_MAX_VERSION_FAILED;
            return false;
        }
        $this->existing_installation_status = OA_STATUS_MAX_NOT_INSTALLED;
        return false;
    }

    /**
     * compare the schema of the connected database
     * with that of a given schema
     *
     * @param string $version
     * @return boolean
     */
    function _checkDBIntegrity($version)
    {
        $path_schema = $this->oDBUpgrader->path_schema;
        $file_schema = $this->oDBUpgrader->file_schema;
        $path_changes = $this->oDBUpgrader->path_changes;
        $file_changes = $this->oDBUpgrader->file_changes;

        $this->oIntegrity->oUpgrader = $this;
        $result =$this->oIntegrity->checkIntegrityQuick($version);

        $this->oDBUpgrader->path_schema     = $path_schema;
        $this->oDBUpgrader->file_schema     = $file_schema;
        $this->oDBUpgrader->path_changes    = $path_changes;
        $this->oDBUpgrader->file_changes    = $file_changes;

        if (!$result)
        {
            $this->oLogger->logError('database integrity check could not complete due to problems');
            return false;
        }
        $this->oLogger->logClear();
        if (count($this->oIntegrity->aTasksConstructiveAll)>0)
        {
            $this->oLogger->logError('database integrity check detected problems with the database');
            foreach ($this->oIntegrity->aTasksConstructiveAll AS $elem => $aTasks)
            {
                foreach ($aTasks AS $task => $aItems)
                {
                    $this->oLogger->logError(count($aItems).' '.$elem.' to '.$task);
                }
            }
            return false;
        }
        return true;
    }


    /**
     * search for an existing @package    OpenXinstallation
     *
     * @param boolean $skipIntegrityCheck
     * @return boolean
     */
    function detectOpenads($skipIntegrityCheck = false)
    {
        if ($GLOBALS['_MAX']['CONF']['openads']['installed'] || file_exists(MAX_PATH.'/var/INSTALLED'))
        {
            $this->existing_installation_status = OA_STATUS_CONFIG_FOUND;
            if (!$this->initDatabaseConnection())
            {
                $this->existing_installation_status = OA_STATUS_MAX_DBCONNECT_FAILED;
                return false;
            }
            $this->versionInitialApplication = $this->oVersioner->getApplicationVersion();
            if (!$this->versionInitialApplication)
            {
                $this->existing_installation_status = OA_STATUS_OAD_VERSION_FAILED;
                return false;
            }
            // hark the special case of 2.3.34-beta - the borked schema
            // treat this as a milestone upgrade for repair purposes
            //if (version_compare($this->versionInitialApplication,'2.3.34-beta')==0)
            // actually, better check for any version < .38 in case of upgrades from .34 prior to the repair pkg
            if (version_compare($this->versionInitialApplication,'2.3.38-beta','<')==-1)
            {
                $this->versionInitialSchema['tables_core'] = $this->oVersioner->getSchemaVersion('tables_core');
                if ($this->versionInitialSchema['tables_core']=='129')
                {
                    $this->versionInitialSchema['tables_core'] = '12934';
                    if (!$skipIntegrityCheck && !$this->_checkDBIntegrity($this->versionInitialSchema['tables_core']))
                    {
                        $this->existing_installation_status = OA_STATUS_MAX_DBINTEG_FAILED;
                        return false;
                    }
                    $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                    $this->aPackageList[0]  = 'openads_upgrade_2.3.34_to_2.3.38_beta.xml';
                    $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                    $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                    $this->upgrading_from_milestone_version = true;
                    return true;
                }
            }
            $current = (version_compare($this->versionInitialApplication,OA_VERSION)==0);
            $valid   = (version_compare($this->versionInitialApplication,OA_VERSION)<0);
            if ($valid)
            {
                $this->aPackageList = $this->getUpgradePackageList($this->versionInitialApplication, $this->_readUpgradePackagesArray());
                if (!$skipIntegrityCheck && count($this->aPackageList)>0)
                {
                    $this->versionInitialSchema['tables_core'] = $this->oVersioner->getSchemaVersion('tables_core');
                    if (!$this->_checkDBIntegrity($this->versionInitialSchema['tables_core']))
                    {
                        $this->existing_installation_status = OA_STATUS_OAD_DBINTEG_FAILED;
                        return false;
                    }
                }
                $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                $this->upgrading_from_milestone_version = false;
                return true;
            }
            else if ($current)
            {
                if ($this->seekFantasyUpgradeFile())
                {
                    $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                    $this->aPackageList[0]  = 'openads_fantasy_upgrade_999.999.999.xml';
                    $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                    $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                    $this->oLogger->log('Fantasy Upgrade Requested');
                    return true;
                }
                $this->existing_installation_status = OA_STATUS_CURRENT_VERSION;
                $this->aPackageList = array();
                return false;
            }
            else if ($this->oConfiguration->checkForConfigAdditions())
            {
                $this->existing_installation_status = OA_STATUS_CAN_UPGRADE;
                $this->aDsn['database'] = $GLOBALS['_MAX']['CONF']['database'];
                $this->aDsn['table']    = $GLOBALS['_MAX']['CONF']['table'];
                $this->upgrading_from_milestone_version = false;
                return true;
            }
            $this->existing_installation_status = OA_STATUS_OAD_VERSION_FAILED;
            return false;
        }
        $this->existing_installation_status = OA_STATUS_OAD_NOT_INSTALLED;
        return false;
    }

    /**
     * execute the installation steps
     *
     * @return boolean
     */
    function install($aConfig)
    {
        $this->oLogger->setLogFile('install.log');
        $this->oLogger->deleteLogFile();

        // Always use lower case prefixes for new installs
        $aConfig['table']['prefix'] = strtolower($aConfig['table']['prefix']);

        $this->aDsn['database'] = $aConfig['database'];
        $this->aDsn['table']    = $aConfig['table'];

        $this->oLogger->log('Installation started '.OA::getNow());
        $this->oLogger->log('Attempting to connect to database '.$this->aDsn['database']['name'].' with user '.$this->aDsn['database']['username']);

        if (!$this->_createDatabase())
        {
            $this->oLogger->logError('Installation failed to create the database '.$this->aDsn['database']['name']);
            return false;
        }
        $this->oLogger->log('Connected to database '.$this->oDbh->connected_database_name);

        if (!$this->checkExistingTables())
        {
            if (!$this->oLogger->errorExists)
            {
                $this->oLogger->logError();
            }
            return false;
        }

        if (!$this->checkPermissionToCreateTable())
        {
            $this->oLogger->logError('Insufficient database permissions or incorrect database settings to install');
            return false;
        }

        if (!$this->initDatabaseConnection())
        {
            $this->oLogger->logError('Installation failed to connect to the database '.$this->aDsn['database']['name']);
            $this->_dropDatabase();
            return false;
        }

        $aConfig = $this->initDatabaseParameters($aConfig);

        if (!$this->createCoreTables())
        {
            $this->oLogger->logError('Installation failed to create the core tables');
            $this->_dropDatabase();
            return false;
        }
        $this->oLogger->log('Installation created the core tables');

        $this->oAuditor->setKeyParams(array('upgrade_name'=>'install_'.OA_VERSION,
                                            'version_to'=>OA_VERSION,
                                            'version_from'=>0,
                                            'logfile'=>basename($this->oLogger->logFile)
                                            )
                                     );

        if (!$this->oVersioner->putSchemaVersion('tables_core', $this->oTable->aDefinition['version']))
        {
            $this->_auditInstallationFailure('Installation failed to update the schema version to '.$oTable->aDefinition['version']);
            $this->_dropDatabase();
            return false;
        }
        $this->oLogger->log('Installation updated the schema version to '.$this->oTable->aDefinition['version']);

        if (!$this->oVersioner->putApplicationVersion(OA_VERSION))
        {
            $this->_auditInstallationFailure('Installation failed to update the application version to '.OA_VERSION);
            $this->_dropDatabase();
            return false;
        }
        $this->oLogger->log('Installation updated the application version to '.OA_VERSION);

        $this->oConfiguration->getInitialConfig();
        if (!$this->saveConfigDB($aConfig))
        {
            $this->_auditInstallationFailure('Installation failed to write database details to the configuration file '.$this->oConfiguration->configFile);
            if (file_exists($this->oConfiguration->configPath.$this->oConfiguration->configFile))
            {
                @unlink($this->oConfiguration->configPath.$this->oConfiguration->configFile);
                $this->oLogger->log('Installation deleted the configuration file '.$this->oConfiguration->configFile);
            }
            $this->_dropDatabase();
            return false;
        }

        $this->oAuditor->logAuditAction(array('description'=>'UPGRADE COMPLETE',
                                                'action'=>UPGRADE_ACTION_UPGRADE_SUCCEEDED,
                                               )
                                         );
        if ($this->upgrading_from_milestone_version)
        {
            if ( ! $this->removeUpgradeTriggerFile())
            {
                $this->oLogger->log('failed to remove the UPGRADE trigger file');
            }
        }
        return true;
    }

    function _auditInstallationFailure($msg)
    {
        $this->oLogger->logError($msg);
        $this->oAuditor->logAuditAction(array('description'=>'UPGRADE FAILED',
                                                'action'=>UPGRADE_ACTION_UPGRADE_FAILED,
                                                )
                                         );
    }

    /**
     * remove the currently connected database
     *
     * @param boolean $log
     */
    function _dropDatabase($log = true)
    {
        if ($this->can_drop_database)
        {
            if (OA_DB::dropDatabase($this->aDsn['database']['name']))
            {
                if ($log)
                {
                    $this->oLogger->log('Installation dropped the database '.$this->aDsn['database']['name']);
                }
                return true;
            }
            $this->oLogger->logError('Installation failed to drop the database '.$this->aDsn['database']['name']);
            return false;
        }
        else
        {
            $this->oTable->dropAllTables();
            if ($log)
            {
                $this->oLogger->log('Installation dropped the core tables from database '.$this->aDsn['database']['name']);
            }
            return true;
        }
    }

    /**
     * create the empty database
     *
     * @return boolean
     */
    function _createDatabase()
    {
        $GLOBALS['_MAX']['CONF']['database']          = $this->aDsn['database'];
        $GLOBALS['_MAX']['CONF']['table']['prefix']   = $this->aDsn['table']['prefix'];
        $GLOBALS['_MAX']['CONF']['table']['type']     = $this->aDsn['table']['type'];
        // Try connecting to the database
        $this->oDbh =& OA_DB::singleton(OA_DB::getDsn($this->aDsn));
        if (PEAR::isError($this->oDbh))
        {
            $GLOBALS['_OA']['CONNECTIONS']  = array();
            $GLOBALS['_MDB2_databases']     = array();

            $result = OA_DB::createDatabase($this->aDsn['database']['name']);
            if (PEAR::isError($result))
            {
                $this->oLogger->logError($result->getMessage());
                return false;
            }
            $this->oDbh = OA_DB::changeDatabase($this->aDsn['database']['name']);
            if (PEAR::isError($this->oDbh))
            {
                $this->oLogger->logError($this->oDbh->getMessage());
                $this->oDbh = null;
                return false;
            }
            $this->oLogger->log('Database created '.$this->aDsn['database']['name']);
            $this->can_drop_database = true;
        }

        $result = OA_DB::createFunctions();
        if (PEAR::isError($result)) {
            $this->oLogger->logError($result->getMessage());
            return false;
        }

        return true;
    }

    /**
     * create the tables_core schema in the database
     *
     * @return boolean
     */
    function createCoreTables()
    {
        if ($this->oTable->init(MAX_PATH.'/etc/tables_core.xml'))
        {
            $this->oLogger->logOnly('schema definition from cache '. ($this->oTable->cached_definition ? 'TRUE':'FALSE'));
            $this->oTable->dropAllTables();
            return $this->oTable->createAllTables();
        }
        return false;
    }

    function setOpenadsInstalledOn()
    {
        $this->oConfiguration->setOpenadsInstalledOn();
    }

    /**
     * retrieve the configuration settings
     *
     * @return array
     */
    function getConfig()
    {
        if (!$GLOBALS['_MAX']['CONF']['max']['installed'])
        {
            $this->oConfiguration->getInitialConfig();
        }
        return $this->oConfiguration->aConfig;
    }

    /**
     * save database configuration settings
     *
     * @param array $aConfig
     * @return boolean
     */
    function saveConfigDB($aConfig)
    {
        $this->oConfiguration->setupConfigDatabase($aConfig['database']);
        $this->oConfiguration->setupConfigTable($aConfig['table']);
        return $this->oConfiguration->writeConfig();
    }

    /**
     * save configuration settings
     *
     * @param array $aConfig
     * @return boolean
     */
    function saveConfig($aConfig)
    {
        $this->oConfiguration->setupConfigWebPath($aConfig['webpath']);

        // Don't reparse the config file to prevent constants being parsed.
        $this->oConfiguration->writeConfig(false);
        $aConfig['database'] = $GLOBALS['_MAX']['CONF']['database'];
        $aConfig['table'] = $GLOBALS['_MAX']['CONF']['table'];
        $this->oConfiguration->setupConfigDatabase($aConfig['database']);
        $this->oConfiguration->setupConfigTable($aConfig['table']);
        $this->oConfiguration->setupConfigStore($aConfig['store']);
        $this->oConfiguration->setupConfigPriority('');
        return $this->oConfiguration->writeConfig();
    }

    /**
     * prepare to execute the upgrade steps
     * assumes that you have run canUpgrade first (to detect install and determine versionInitialApplication)
     * execute milestones followed by incremental packages
     * this method is called recursively for incremental packages
     * audit each package execution
     *
     *
     * @return boolean
     */
    function upgrade($input_file='', $timing='constructive')
    {
        // initialise database connection if necessary
        if (is_null($this->oDbh))
        {
            $this->initDatabaseConnection();
        }
        if (!$this->checkPermissionToCreateTable())
        {
            $this->oLogger->logError('Insufficient database permissions or incorrect database settings');
            return false;
        }
        // first deal with each of the packages in the list
        // that was compiled during detection
        if (count($this->aPackageList)>0)
        {
            foreach ($this->aPackageList AS $k => $this->package_file)
            {
                if (!$this->upgradeExecute($this->package_file))
                {
                    $halt = true;
                    break;
                }
            }
        }
        if ($halt)
        {
            return false;
        }
        // when upgrading from a milestone version such as pan or max
        // run through this upgrade again
        // else finish by doing a *version stamp* upgrade
        if ($this->upgrading_from_milestone_version)
        {
            // if openads installed was not on
            // set installed on so openads can be detected
            $GLOBALS['_MAX']['CONF']['openads']['installed'] = 1;
            if ($this->detectOpenads())
            {
                if (!$this->upgrade())
                {
                    $GLOBALS['_MAX']['CONF']['openads']['installed'] = 0;
                    $this->_removeInstalledFlagFile();
                    return false;
                }
            }
        }
        else
        {
            $version = OA_VERSION;
            if ($this->seekFantasyUpgradeFile())
            {
                $version = '999.999.999';
                $this->createFantasyRecoveryFile();
            }
            $this->package_file = 'openads_version_stamp_'.$version;
            $this->oLogger->setLogFile($this->_getUpgradeLogFileName($timing));
            $this->oDBUpgrader->logFile = $this->oLogger->logFile;
            $this->oAuditor->setUpgradeActionId();
            $this->oAuditor->setKeyParams(array('upgrade_name'=>$this->package_file,
                                                'version_to'=>$version,
                                                'version_from'=>$this->getProductApplicationVersion(),
                                                'logfile'=>basename($this->oLogger->logFile)
                                                )
                                         );
            $this->oAuditor->logAuditAction(array('description'=>'FAILED',
                                                  'action'=>UPGRADE_ACTION_UPGRADE_FAILED,
                                                 )
                                           );
            if (!$this->_upgradeConfig())
            {
                $this->oLogger->logError('Failed to upgrade configuration file');
                return false;
            }
            if ($this->versionInitialApplication != $version)
            {
                if (!$this->oVersioner->putApplicationVersion($version))
                {
                    $this->$this->oLogger->logError('Failed to update application version to '.$version);
                    $this->message = 'Failed to update application version to '.$version;
                    return false;
                }
                $this->versionInitialApplication = $this->oVersioner->getApplicationVersion();
                $this->oLogger->log('Application version updated to '. $version);
            }
            $this->oAuditor->updateAuditAction(array('description'=>'UPGRADE COMPLETE',
                                                     'action'=>UPGRADE_ACTION_UPGRADE_SUCCEEDED,
                                                     'confbackup'=>$this->oConfiguration->getConfigBackupName()
                                                    )
                                              );
            $this->_writeRecoveryFile();
            $this->_pickupNoBackupsFile();
        }
        $this->_pickupRecoveryFile();
        $this->_writePostUpgradeTasksFile();
        return true;
    }

    /**
     * save database settings and merge new settings from dist config
     *
     * @return boolean
     */
    function _upgradeConfig()
    {
        $aConfig['database'] = $GLOBALS['_MAX']['CONF']['database'];
        $aConfig['table']    = $GLOBALS['_MAX']['CONF']['table'];
        $aConfig             = $this->initDatabaseParameters($aConfig);
        $this->saveConfigDB($aConfig);
        // Backs up the existing config file and merges any changes from dist.conf.php.
        if (!$this->oConfiguration->mergeConfig())
        {
            return false;
        }
        if (!$this->oConfiguration->writeConfig())
        {
            return false;
        }
        return true;
    }

    /**
     * execute an upgrade package and audit
     *
     *
     * @return boolean
     */
    function upgradeExecute($input_file='')
    {
        $this->oLogger->setLogFile($this->_getUpgradeLogFileName());
        $this->oDBUpgrader->logFile = $this->oLogger->logFile;
        $this->oConfiguration->clearConfigBackupName();

        if ($input_file)
        {
            $input_file = $this->upgradePath.$input_file;
        }
        if (!$this->_parseUpgradePackageFile($input_file))
        {
            return false;
        }
        $this->oAuditor->setUpgradeActionId();  // links the upgrade_action record with database_action records
        $this->oAuditor->setKeyParams(array('upgrade_name'=>$this->package_file,
                                            'version_to'=>$this->aPackage['versionTo'],
                                            'version_from'=>$this->aPackage['versionFrom'],
                                            'logfile'=>basename($this->oLogger->logFile)
                                            )
                                     );
        // do this here in case there is a fatal error
        // in one of the upgrade methods
        // this ensures that there is recovery info available after
        $this->oAuditor->logAuditAction(array('description'=>'FAILED',
                                              'action'=>UPGRADE_ACTION_UPGRADE_FAILED,
                                             )
                                       );
        $this->_writeRecoveryFile();
        if (!$this->runScript($this->aPackage['prescript']))
        {
            $this->oLogger->logError('Failure in upgrade prescript '.$this->aPackage['prescript']);
            return false;
        }
        if (!$this->upgradeSchemas())
        {
            $this->oLogger->logError('Failure while upgrading schemas');
            return false;
        }
        if (!$this->runScript($this->aPackage['postscript']))
        {
            $this->oLogger->logError('Failure in upgrade postscript '.$this->aPackage['postscript']);
            return false;
        }
        if (!$this->oVersioner->putApplicationVersion($this->aPackage['versionTo']))
        {
            $this->oLogger->logError('Failed to update application version to '.$this->aPackage['versionTo']);
            $this->message = 'Failed to update application version to '.$this->aPackage['versionTo'];
            return false;
        }
        $this->versionInitialApplication = $this->aPackage['versionTo'];
        $this->oAuditor->updateAuditAction(array('description'=>'UPGRADE COMPLETE',
                                                 'action'=>UPGRADE_ACTION_UPGRADE_SUCCEEDED,
                                                 'confbackup'=>$this->oConfiguration->getConfigBackupName()
                                                )
                                          );
        return true;
    }

    function addPostUpgradeTask($task)
    {
        $this->aToDoList[] = $task;
    }

    /**
     * Create the admin user and account, plus a default manager
     *
     * @param array $aAdmin
     * @return boolean
     */
    function putAdmin($aAdmin)
    {
        // Create Admin account
        $doAccount = OA_Dal::factoryDO('accounts');
        $doAccount->account_name = 'Administrator account';
        $doAccount->account_type = OA_ACCOUNT_ADMIN;
        $adminAccountId = $doAccount->insert();

        if (!$adminAccountId) {
            $this->oLogger->logError('error creating the admin account');
            return false;
        }

        // Create Manager entity
        $doAgency = OA_Dal::factoryDO('agency');
        $doAgency->name   = 'Default manager';
        $doAgency->email  = $doUser->email_address;
        $doAgency->active = 1;
        $agencyId = $doAgency->insert();

        if (!$agencyId) {
            $this->oLogger->logError('error creating the manager entity');
            return false;
        }

        $doAgency = OA_Dal::factoryDO('agency');
        if (!$doAgency->get($agencyId)) {
            $this->oLogger->logError('error retrieving the manager account ID');
            return false;
        }

        $agencyAccountId = $doAgency->account_id;

        // Create Admin user
        $doUser = OA_Dal::factoryDO('users');
        $doUser->contact_name = 'Administrator';
        $doUser->email_address = $aAdmin['email'];
        $doUser->username = $aAdmin['name'];
        $doUser->password = md5($aAdmin['pword']);
        $doUser->default_account_id = $agencyAccountId;
        $userId = $doUser->insert();

        if (!$userId) {
            $this->oLogger->logError('error creating the admin user');
            return false;
        }

        $result = OA_Permission::setAccountAccess($adminAccountId, $userId);
        if (!$result) {
            $this->oLogger->logError("error creating access to admin account, account id: $adminAccountId, user ID: $userId");
            return false;
        }
        $result = OA_Permission::setAccountAccess($agencyAccountId, $userId);
        if (!$result) {
            $this->oLogger->logError("error creating access to default agency account, account id: $agencyAccountId, user ID: $userId");
            return false;
        }

        // Insert preferences and return
        return $this->putDefaultPreferences($adminAccountId);
    }

    function putPreferences($aPrefs)
    {
        $adminAccountId = OA_Dal_ApplicationVariables::get('admin_account_id');

        if (!$adminAccountId) {
            $this->oLogger->logError('error getting the admin account ID');
            return false;
        }

        $oPreferences = new OA_Preferences();

        $aPrefs = array(
            'timezone' => $aPrefs['timezone'],
            'language' => $aPrefs['language'],
        );

        foreach ($aPrefs as $prefName => $value) {
            $doPreferences = OA_Dal::factoryDO('preferences');
            $doPreferences->preference_name = $prefName;
            $doPreferences->find();
            if ($doPreferences->fetch()) {
                $doAPA = OA_Dal::factoryDO('account_preference_assoc');
                $doAPA->account_id    = $adminAccountId;
                $doAPA->preference_id = $doPreferences->preference_id;
                $doAPA->value         = $value;
                $result = $doAPA->update();

                if (!$result) {
                    $this->oLogger->logError("error adding preference default for $prefName: '".$aPref['default']."'");
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * A method to inser initialise the preferences table and insert the default prefs
     *
     * @param int $adminAccountId
     * @return bool
     */
    function putDefaultPreferences($adminAccountId)
    {
        // Preferences handling
        $oPreferences = new OA_Preferences();
        $aPrefs = $oPreferences->getPreferenceDefaults();

        // Insert default prefs
        foreach ($aPrefs as $prefName => $aPref) {
            $doPreferences = OA_Dal::factoryDO('preferences');
            $doPreferences->preference_name = $prefName;
            $doPreferences->account_type = empty($aPref['account_type']) ? '' : $aPref['account_type'];
            $preferenceId = $doPreferences->insert();

            if (!$preferenceId) {
                $this->oLogger->logError("error adding preference entry: $prefName");
                return false;
            }

            $doAPA = OA_Dal::factoryDO('account_preference_assoc');
            $doAPA->account_id    = $adminAccountId;
            $doAPA->preference_id = $preferenceId;
            $doAPA->value         = $aPref['default'];
            $result = $doAPA->insert();

            if (!$result) {
                $this->oLogger->logError("error adding preference default for $prefName: '".$aPref['default']."'");
                return false;
            }
        }

        return true;
    }

    /**
     * Update checkForUpdates value into Settings
     *
     * @param boolean $syncEnabled
     * @return boolean
     */
    function putSyncSettings($syncEnabled)
    {
        require_once MAX_PATH . '/lib/OA/Admin/Settings.php';
        require_once MAX_PATH . '/lib/OA/Sync.php';

        $oSettings = new OA_Admin_Settings();
        $oSettings->settingChange('sync', 'checkForUpdates', $syncEnabled);

        // Reset Sync cache
        OA_Dal_ApplicationVariables::delete('sync_cache');
        OA_Dal_ApplicationVariables::delete('sync_timestamp');
        OA_Dal_ApplicationVariables::delete('sync_last_seen');

        if (!$oSettings->writeConfigChange()) {
            $this->oLogger->logError('Error saving Sync settings to the config file');
            return false;
        }

        // Generate a new Platform Hash if empty
        $platformHash = OA_Dal_ApplicationVariables::get('platform_hash');
        if (empty($platformHash) && !OA_Dal_ApplicationVariables::set('platform_hash', sha1(uniqid('', true))))
        {
            $this->oLogger->logError('Error inserting Platform Hash into database');
            return false;
        }

        $oSync = new OA_Sync();
        OA::disableErrorHandling();
        $oSync->checkForUpdates();
        OA::enableErrorHandling();

        return true;
    }

    /**
     * calls the dummy data class insert() method
     * which uses the DataGenerator to insert some data
     *
     * @return boolean
     */
    function insertDummyData()
    {
        require_once MAX_PATH.'/lib/OA/Upgrade/DummyData.php';
        $oDummy = new OA_Dummy_Data();
        $oDummy->insert();
        return true;
    }

    /**
     * this can be used to run custom scripts
     * for planned enhancement: pre/post upgrade
     *
     * @param string $file
     * @param string $classprefix
     * @return boolean
     */
    function runScript($file)
    {
        if (!$file)
        {
            return true;
        }
        else if (file_exists($this->upgradePath.$file))
        {
            $this->oLogger->log('loading script '.$file);
            if (!@include($this->upgradePath.$file)) {
                $this->oLogger->logError('cannot include script '.$file);
                return false;
            }
            if (empty($className)) {
                $this->oLogger->logError('missing $className variable in '.$file);
                return false;
            }

            if (class_exists($className))
            {
                $this->oLogger->log('instantiating class '.$className);
                $oScript = new $className;
                $method = 'execute';
                if (is_callable(array($oScript, $method)))
                {
                    $this->oLogger->log('method is callable '.$method);
                    $aParams = array($this);
                    if (!call_user_func(array($oScript, $method), $aParams))
                    {
                        $this->oLogger->logError('script returned false '.$className);
                        return false;
                    }
                    return true;
                }
                $this->oLogger->logError('method not found '.$method);
                return false;
            }
            $this->oLogger->logError('class not found '.$className);
            return false;
        }
        $this->oLogger->logError('script not found '.$file);
        return false;
    }

    /**
     * test if the database username has necessary permissions
     *
     * @return boolean
     */
    function checkPermissionToCreateTable()
    {
        $prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];

        // If prefix is not lowercase, check for DB case sensitivity
        if ($prefix != strtolower($prefix)) {
            $result = $this->oDbh->isDBCaseSensitive();
            if (PEAR::isError($result))
            {
                $this->oLogger->logError('Unable to retrieve database case sensitivity info');
                return false;
            }
            if (!$result)
            {
                $this->oLogger->logError('@package    OpenXrequires database case sensitivity to work with uppercase prefixes');
                return false;
            }
        }
        $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();
        if (PEAR::isError($aExistingTables))
        {
            $this->oLogger->logError('Unable to SHOW TABLES - check your database permissions');
            return false;
        }
        $tblTmp = $prefix.'tmp_dbpriviligecheck';
        $tblTmpQuoted = $this->oDbh->quoteIdentifier($tblTmp,true);
        if (in_array($tblTmp, $aExistingTables))
        {
            $result = $this->oDbh->exec("DROP TABLE {$tblTmpQuoted}");
        }
        if (PEAR::isError($result))
        {
            $this->oLogger->logError('Test privileges table already exists and you don\'t have permissions to remove it');
            return false;
        }

        $result = $this->oDbh->exec("CREATE TABLE {$tblTmpQuoted} (tmp int)");
        if (PEAR::isError($result))
        {
            $this->oLogger->logError('Failed to CREATE TABLE - check your database permissions');
            return false;
        }
        $result   = $this->oDbh->manager->listTableFields($tblTmp);
        PEAR::popErrorHandling();
        if (PEAR::isError($result))
        {
            $this->oDbh->exec("DROP TABLE {$tblTmpQuoted}");
            $this->oLogger->logError('Failed to SHOW FIELDS - check your database permissions');
            return false;
        }
        $result = $this->oDbh->exec("ALTER TABLE {$tblTmpQuoted} ADD test_desc TEXT");
        if (PEAR::isError($result))
        {
            $this->oDbh->exec("DROP TABLE {$tblTmpQuoted}");
            $this->oLogger->logError('Failed to ALTER TABLE - check your database permissions');
            return false;
        }
        $result = $this->oDbh->manager->createIndex($tblTmp, $tblTmp.'_idx', array('fields' => array('tmp' => array( 'sorting' => 'ascending' ))));
        if (PEAR::isError($result))
        {
            $this->oDbh->exec("DROP TABLE {$tblTmpQuoted}");
            $this->oLogger->logError('Failed to CREATE INDEX - check your database permissions');
            return false;
        }
        $result = $this->oDbh->manager->dropIndex($tblTmp, $tblTmp.'_idx');
        if (PEAR::isError($result))
        {
            $this->oDbh->exec("DROP TABLE {$tblTmpQuoted}");
            $this->oLogger->logError('Failed to DROP INDEX - check your database permissions');
            return false;
        }
        $result = $this->oDbh->exec("DROP TABLE {$tblTmpQuoted}");
        if (PEAR::isError($result))
        {
            $this->oLogger->logError('Failed to DROP TABLE - check your database permissions');
            return false;
        }
        $tblTmp = $prefix.'tmp_tmp_dbpriviligecheck';
        $result = $this->oDbh->exec("CREATE TEMPORARY TABLE {$tblTmp} (tmp int)");
        if (PEAR::isError($result))
        {
            $this->oLogger->logError('Failed to CREATE TEMPORARY TABLE - check your database permissions');
            return false;
        }
        $result = $this->oDbh->exec("DROP TABLE {$tblTmp}");
        if (PEAR::isError($result))
        {
            $this->oLogger->logError('Failed to DROP TEMPORARY TABLE - check your database permissions');
            return false;
        }
        $this->oLogger->log('Database settings and permissions are OK');
        return true;
    }

    /**
     * check if openads tables already exist in the specified database
     *
     * @return boolean
     */
    function checkExistingTables()
    {
        $result = true;

        $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();

        $oldTableMessagePrefix  = 'Your database contains an old @package    OpenXconfiguration table: ';
        $oldTableMessagePostfix = 'If you are trying to upgrade this database, please copy your existing configuration file into the var folder of this install. If you wish to proceed with a fresh installation, please either choose a new Table Prefix or a new Database.';
        if (in_array($this->aDsn['table']['prefix'].'config', $aExistingTables))
        {
            $this->oLogger->logError($oldTableMessagePrefix . $this->aDsn['table']['prefix'] . 'config. ' . $oldTableMessagePostfix);
            return false;
        }
        if (in_array($this->aDsn['table']['prefix'].'preference', $aExistingTables))
        {
            $this->oLogger->logError($oldTableMessagePrefix . $this->aDsn['table']['prefix'].'preference. ' . $oldTableMessagePostfix);
            return false;
        }
        if (in_array($this->aDsn['table']['prefix'].'preferences', $aExistingTables))
        {
            $this->oLogger->logError($oldTableMessagePrefix . $this->aDsn['table']['prefix'].'preferences. ' . $oldTableMessagePostfix);
            return false;
        }
        $tablePrefixError = false;
        foreach ($aExistingTables AS $k => $tablename)
        {
            if (substr($tablename, 0, strlen($this->aDsn['table']['prefix'])) == $this->aDsn['table']['prefix'])
            {
               $result = false;
               $this->oLogger->log('Table with the prefix '.$this->aDsn['table']['prefix'].' found: '.$tablename);
               if ($tablePrefixError == false)
               {
                   $this->oLogger->logError('The database you have chosen already contains tables with the prefix '.$this->aDsn['table']['prefix']);
                   $this->oLogger->logError('Please either remove these tables or choose a new prefix');
                   $tablePrefixError = true;
               }
            }
        }
        return $result;
    }

    /**
     * execute each of the db upgrade packages
     *
     * @return boolean
     */
    function upgradeSchemas()
    {
        foreach ($this->aDBPackages as $k=>$aPkg)
        {
            if (!array_key_exists($aPkg['schema'],$this->versionInitialSchema))
            {
                $this->versionInitialSchema[$aPkg['schema']] = $this->oVersioner->getSchemaVersion($aPkg['schema']);
            }
            $ok = false;
            if ($this->oDBUpgrader->init('constructive', $aPkg['schema'], $aPkg['version'], false))
            {
                if ($this->_runUpgradeSchemaPreScript($aPkg['prescript']))
                {
                    if ($this->oDBUpgrader->upgrade($this->versionInitialSchema[$aPkg['schema']]))
                    {
                        if ($this->_runUpgradeSchemaPostscript($aPkg['postscript']))
                        {
                            $ok = true;
                        }
                    }
                }
            }
            // for now we execute destructive immediately after constructive
            if ($ok)
            {
                $ok = false; // start over - should return true throughout even if nothing to do
                // last param 'true' will reset the object without having to re-parse the schema
                if ($this->oDBUpgrader->init('destructive', $aPkg['schema'], $aPkg['version'], true))
                {
                    if ($this->_runUpgradeSchemaPreScript($aPkg['prescript']))
                    {
                        if ($this->oDBUpgrader->upgrade($this->versionInitialSchema[$aPkg['schema']]))
                        {
                            if ($this->_runUpgradeSchemaPostscript($aPkg['postscript']))
                            {
                                $ok = true;
                            }
                        }
                    }
                }
            }
            if ($ok)
            {
              $version = ( $aPkg['stamp'] ?  $aPkg['stamp'] : $aPkg['version']);
              $this->oVersioner->putSchemaVersion($aPkg['schema'],$version);
            }
            else
            {
                return false;
            }
        }
        return true;
    }

    /**
     * call the db_upgrader's prepare and run script functions
     * for pre / post upgrade schema packages
     *
     * @param string $file
     * @return boolean
     */
    function _runUpgradeSchemaPreScript($file)
    {
        if ($file)
        {
            if (!$this->oDBUpgrader->prepPreScript($this->upgradePath.$file))
            {
                $this->oLogger->logError('schema prepping prescript: '.$this->upgradePath.$file);
                return false;
            }
            if(!$this->oDBUpgrader->runPreScript(array($this)))
            {
                $this->oLogger->logError('schema prepping prescript: '.$this->upgradePath.$file);
                return false;
            }
        }
        return true;
    }

    /**
     * call the db_upgrader's prepare and run script functions
     * for pre / post upgrade schema packages
     *
     * @param string $file
     * @return boolean
     */
    function _runUpgradeSchemaPostScript($file)
    {
        if ($file)
        {
            if (!$this->oDBUpgrader->prepPostScript($this->upgradePath.$file))
            {
                $this->oLogger->logError('schema prepping postscript: '.$this->upgradePath.$file);
                return false;
            }
            if(!$this->oDBUpgrader->runPostScript(array($this)))
            {
                $this->oLogger->logError('schema prepping postscript: '.$this->upgradePath.$file);
                return false;
            }
        }
        return true;
    }

    /**
     * for each schema, replace the upgraded tables with the backup tables
     *
     * @return boolean
     */
/*    function rollbackSchemas()
    {
        foreach ($this->versionInitialSchema AS $schema => $version)
        {
            if ($this->oVersioner->getSchemaVersion($schema) != $version)
            {
                krsort($this->aDBPackages);
                foreach ($this->aDBPackages as $k=>$aPkg)
                {
                    $this->oAuditor->oDBAuditor->logAuditAction(array('info1'=>'UPGRADE FAILED',
                                                               'info2'=>'ROLLING BACK',
                                                               'action'=>DB_UPGRADE_ACTION_UPGRADE_FAILED,
                                                              )
                                                        );
                    if (!$this->oDBUpgrader->init('destructive', $aPkg['schema'], $aPkg['version']))
                    {
                        return false;
                    }
                    if (!$this->oDBUpgrader->prepRollback())
                    {
                        return false;
                    }
                    if (!$this->oDBUpgrader->rollback())
                    {
                        $this->oLogger->logError('ROLLBACK FAILED: '.$aPkg['schema'].'_'.$aPkg['version']);
                        return false;
                    }
                    if (!$this->oDBUpgrader->init('constructive', $aPkg['schema'], $aPkg['version'], true))
                    {
                        return false;
                    }
                    if (!$this->oDBUpgrader->prepRollback())
                    {
                        return false;
                    }
                    if (!$this->oDBUpgrader->rollback())
                    {
                        $this->oLogger->logError('ROLLBACK FAILED: '.$aPkg['schema'].'_'.$aPkg['version']);
                        return false;
                    }
                    $this->oLogger->logError('ROLLBACK SUCCEEDED: '.$aPkg['schema'].'_'.$aPkg['version']);
                    $this->oVersioner->putSchemaVersion($aPkg['schema'], $aPkg['version']);
                }
                $this->oVersioner->putSchemaVersion($schema, $version);
            }
        }
        return true;
    }*/

    /**
     * use the xml parser to parse the upgrade package
     *
     * @param string $input_file
     * @return array
     */
    function _parseUpgradePackageFile($input_file)
    {
        $this->aPackage = array();
        $this->aDBPackages = array();


        $this->oParser->aPackage       = array('db_pkgs' => array());
        $this->oParser->DBPkg_version  = '';
        $this->oParser->DBPkg_stamp    = '';
        $this->oParser->DBPkg_schema   = '';
        $this->oParser->DBPkg_prescript = '';
        $this->oParser->DBPkg_postscript = '';
        $this->oParser->aDBPkgs        = array('files'=>array());
        $this->oParser->aSchemas       = array();
        $this->oParser->aFiles         = array();

        $this->oParser->elements   = array();
        $this->oParser->element    = '';
        $this->oParser->count      = 0;
        $this->oParser->error      ='';

        if ($input_file!='')
        {
            $result = $this->oParser->setInputFile($input_file);
            if (PEAR::isError($result)) {
                return $result;
            }

            $result = $this->oParser->parse();
            if (PEAR::isError($result))
            {
                $this->oLogger->logError('problem parsing the package file: '.$result->getMessage());
                return false;
            }
            if (PEAR::isError($this->oParser->error))
            {
                $this->oLogger->logError('problem parsing the package file: '.$this->oParser->error);
                return false;
            }
            $this->aPackage     = $this->oParser->aPackage;
            $this->aDBPackages  = $this->aPackage['db_pkgs'];
            $this->aPackage['versionFrom'] = ($this->aPackage['versionFrom'] ? $this->aPackage['versionFrom'] : $this->versionInitialApplication);
        }
        else
        {
            // an actual package for this version does not exist so fake it
            $this->aPackage['versionTo']   = OA_VERSION;
            $this->aPackage['versionFrom'] = $this->versionInitialApplication;
            $this->aPackage['prescript']   = '';
            $this->aPackage['postscript']  = '';
            $this->aDBPackages             = array();
        }
        return true;
    }

    /**
     * retrieve the message errary
     *
     * @return boolean
     */
    function getMessages()
    {
        return $this->oLogger->aMessages;
    }

    /**
     * not used anymore i think
     * retrieve the error array
     *
     * @return boolean
     */
    function getErrors()
    {
        return $this->oLogger->aErrors;
    }

    /**
     * write the version, schema and timestamp to a small temp file in the var folder
     * this will be written when an upgrade starts and deleted when it ends
     * if this file is present outside of the upgrade process it indicates that
     * the upgrade was interrupted
     *
     * @return boolean
     */
    function _writeRecoveryFile()
    {
        $log     = fopen($this->recoveryFile, 'a');
        $date    = date('Y-m-d h:i:s');
        $auditId = $this->oAuditor->getUpgradeActionId();
        fwrite($log, "{$auditId}/{$this->package_file}/{$date};\r\n");
        fclose($log);
        return file_exists($this->recoveryFile);
    }

    /**
     * remove the recovery file
     *
     * @return boolean
     */
    function _pickupRecoveryFile()
    {
        if (file_exists($this->recoveryFile))
        {
            @unlink($this->recoveryFile);
        }
        return (!file_exists($this->recoveryFile));
    }

    /**
     * retrieves the contents of the recovery file into an array
     *
     * @return array | false
     */
     function seekRecoveryFile()
    {
        if (file_exists($this->recoveryFile))
        {
            $aContent = explode(';', file_get_contents($this->recoveryFile));
            foreach ($aContent as $k => $v)
            {
                if (trim($v))
                {
                    $aLine = explode('/', trim($v));
                    if (is_array($aLine) && (count($aLine)==3) && (is_numeric($aLine[0])))
                    {
                        $aResult[] = array(
                                            'auditId'   =>$aLine[0],
                                            'package'   =>$aLine[1],
                                            'updated'   =>$aLine[2],
                                            );
                    }
                    else
                    {
                        return array();
                    }
                }
            }
            return $aResult;
        }
        return false;
    }

    /**
     * write a list of files to be included after the upgrade
     *
     * @return boolean
     */
    function _writePostUpgradeTasksFile()
    {
        if (!empty($this->aToDoList))
        {
            $f = fopen($this->postTaskFile, 'w');
            $this->aToDoList = array_unique($this->aToDoList);
            foreach ($this->aToDoList AS $k => $v)
            {
                fwrite($f, "{$v};\r\n");
            }
            fclose($f);
            return file_exists($this->postTaskFile);
        }
        return true;
    }

    /**
     * retrieves a list of files to be included at the very end of the upgrade
     * include each file
     *
     * @return array | false
     */
     function executePostUpgradeTasks()
    {
        if (file_exists($this->postTaskFile))
        {
            $aContent = array_unique(explode(';', trim(file_get_contents($this->postTaskFile))));
            foreach ($aContent as $k => $v)
            {
                if (trim($v))
                {
                    $file = $this->upgradePath."tasks/openads_upgrade_task_".trim($v).".php";
                    if (file_exists($file))
                    {
                        $this->oLogger->logOnly('attempting to include file '.$file);
                        include $file;
                        $this->oLogger->logOnly('executed file '.$file);
                    }
                    else
                    {
                        $this->oLogger->logOnly('file not found '.$file);
                    }
                    $aResult[$k]['task']    = trim($v);
                    $aResult[$k]['file']    = $file;
                    $aResult[$k]['result']  = $upgradeTaskResult;
                    $aResult[$k]['message'] = $upgradeTaskMessage;
                    $aResult[$k]['error']   = $upgradeTaskError;
                }
            }
            $this->_pickupPostUpgradeTasksFile();
            return $aResult;
        }
        return true;
    }

    /**
     * remove the nobackups file
     *
     * @return boolean
     */
    function _pickupPostUpgradeTasksFile()
    {
        @unlink($this->postTaskFile);
        return (!file_exists($this->postTaskFile));
    }

    /**
     * looks for the UPGRADE.FANTASY file
     *
     * @return array | false
     */
     function seekFantasyUpgradeFile()
    {
        return file_exists(MAX_PATH.'/var/UPGRADE.FANTASY');
    }

    /**
     * copy a recovery file to a RECOVERY.FANTASY file
     *
     */
    function createFantasyRecoveryFile()
    {
        if ($this->seekFantasyUpgradeFile())
        {
            if (file_exists($this->recoveryFile))
            {
                @copy($this->recoveryFile, $this->recoveryFile.'.FANTASY');
            }
        }
    }

    /**
     * identifies if upgrade without backups is requested
     *
     * @return boolean
     */
    function _doBackups()
    {
        return (!file_exists($this->nobackupsFile));
    }

    /**
     * remove the nobackups file
     *
     * @return boolean
     */
    function _pickupNoBackupsFile()
    {
        @unlink($this->nobackupsFile);
        return (!file_exists($this->nobackupsFile));
    }

    /**
     * build a string for naming a logfile
     * should identify it's purpose
     *
     * @param string $timing -- not used currently
     * @return string
     */
    function _getUpgradeLogFileName($timing='constructive')
    {
        if ($this->package_file=='')
        {
            $package = 'openads_upgrade_'.OA_VERSION;
        }
        else
        {
            $package = str_replace('.xml', '', $this->package_file);
        }
        return $package.'_'.OA::getNow('Y_m_d_h_i_s').'.log';
    }

    /**
     * get the name of the logfile currently assigned to the logger
     *
     * @return string
     */
    function getLogFileName()
    {
        return $this->oLogger->logFile;
    }

    /**
     * remove the upgrade file
     *
     * @return boolean
     */
    function removeUpgradeTriggerFile()
    {
        if (file_exists(MAX_PATH.'/var/UPGRADE'))
        {
            return @unlink(MAX_PATH.'/var/UPGRADE');
        }
        return true;
    }

    /**
     * remove the upgrade file
     *
     * @return boolean
     */
    function _removeInstalledFlagFile()
    {
        if (file_exists(MAX_PATH.'/var/INSTALLED'))
        {
            return @unlink(MAX_PATH.'/var/INSTALLED');
        }
        return true;
    }

    function _createEmptyVarFile($filename)
    {
        $fp = fopen(MAX_PATH.'/var/'.$filename, 'a');
        fwrite($fp, "");
        fclose($fp);
        return (file_exists(MAX_PATH.'/var/'.$filename));
    }

    /**
     * retrieve the contents of the upgrade package file into an array
     * this file contains a list of all valid openads 2.3 upgrade packages
     *
     * @param string $file
     * @return array
     */
    function _readUpgradePackagesArray($file='')
    {
        if (!$file)
        {
            $file = $this->upgradePath.'openads_upgrade_array.txt';
        }
        if (!file_exists($file))
        {
            return false;
        }
        return unserialize(file_get_contents($file));
    }

    /**
     * walk an array of version information to build a list of required upgrades
     * they must be in the RIGHT order!!!
     * hence the weird sorting of keys etc..
     */
    function getUpgradePackageList($verPrev, $aVersions=null)
    {
        $verPrev = OA::stripVersion($verPrev);
        $aFiles = array();
        if (is_array($aVersions))
        {
            ksort($aVersions, SORT_NUMERIC);
            foreach ($aVersions as $release => $aMajor)
            {
                ksort($aMajor, SORT_NUMERIC);
                foreach ($aMajor as $major => $aMinor)
                {
                    ksort($aMinor, SORT_NUMERIC);
                    foreach ($aMinor as $minor => $aStatus)
                    {
                        if (array_key_exists('-beta-rc', $aStatus))
                        {
                            $aKeys = array_keys($aStatus['-beta-rc']);
                            sort($aKeys, SORT_NUMERIC);
                            foreach ($aKeys AS $k => $v)
                            {
                                $version = $release.'.'.$major.'.'.$minor.'-beta-rc'.$v;
                                if (version_compare($verPrev, $version)<0)
                                {
                                    $aFiles[] = $aStatus['-beta-rc'][$v]['file'];
                                }
                            }
                        }
                        if (array_key_exists('-beta', $aStatus))
                        {
                            $aBeta = $aStatus['-beta'];
                            foreach ($aBeta as $key => $file)
                            {
                                $version = $release.'.'.$major.'.'.$minor.'-beta';
                                if (version_compare($verPrev, $version)<0)
                                {
                                    $aFiles[] = $file;
                                }
                            }
                        }
                        if (array_key_exists('-rc', $aStatus))
                        {
                            $aKeys = array_keys($aStatus['-rc']);
                            sort($aKeys, SORT_NUMERIC);
                            foreach ($aKeys AS $k => $v)
                            {
                                $version = $release.'.'.$major.'.'.$minor.'-rc'.$v;
                                if (version_compare($verPrev, $version)<0)
                                {
                                    $aFiles[] = $aStatus['-rc'][$v]['file'];
                                }
                            }
                        }
                        if (array_key_exists('file', $aStatus))
                        {
                            $version = $release.'.'.$major.'.'.$minor;
                            if (version_compare($verPrev, $version)<0)
                            {
                                $aFiles[] = $aStatus['file'];
                            }
                        }
                    }
                }
            }
        }
        return $aFiles;
    }

    /**
     * compile a list of changesets available in /etc/changes
     * could be used for a changeset manager
     * THIS IS NOT USED BY THE UPGRADER
     *
     * @return array
     */
    /*
    function _getChangesetList($schema)
    {
        $aFiles = array();
        $dh = opendir(MAX_PATH.'/etc/changes');
        if ($dh) {
            while (false !== ($file = readdir($dh)))
            {
                $aMatches = array();
                if (preg_match("/schema_{$schema}_([\d])+\.xml/", $file, $aMatches))
                {
                    $version = $aMatches[1];
                    $fileSchema = basename($file);
                    $aFiles[$version] = array();
                    $fileChanges = str_replace('schema', 'changes', $fileSchema);
                    $fileMigrate = str_replace('schema', 'migration', $fileSchema);
                    $fileMigrate = str_replace('xml', 'php', $fileMigrate);
                    if (!file_exists(MAX_CHG.$fileChanges))
                    {
                        $fileChanges = 'not found';
                    }
                    $aFiles[$version]['changeset'] = $fileChanges;
                    if (!file_exists(MAX_CHG.$fileMigrate))
                    {
                        $fileMigrate = 'not found';
                    }
                    $aFiles[$version]['migration'] = $fileMigrate;
                    $aFiles[$version]['schema'] = $fileSchema;
                }
            }
        }
        closedir($dh);
        return $aFiles;
    }
    */

    /**
     * Open each changeset and determine the version and timings
     * THIS IS NOT USED BY THE UPGRADER
     *
     * @return boolean
     */
/*    function _compileChangesetInfo()
    {
        $this->aChangesetList = $this->_getChangesetList();
        foreach ($this->aChangesetList as $version=>$aFiles)
        {
            $file       = MAX_PATH.'/etc/changes/'.$aFiles['changeset'];
            $aChanges   = $this->oDBUpgrader->oSchema->parseChangesetDefinitionFile($file);
            if (!$this->_isPearError($aChanges, "failed to parse changeset ({$file})"))
            {
                $this->_log('changeset found in file: '.$file);
                $this->_log('name: '.$aChanges['name']);
                $this->_log('version: '.$aChanges['version']);
                $this->_log('comments: '.$aChanges['comments']);
                $this->_log(($aChanges['constructive'] ? 'constructive changes found' : 'constructive changes not found'));
                $this->_log(($aChanges['destructive'] ? 'destructive changes found' : 'destructive changes not found'));
            }
            else
            {
                return false;
            }
        }
        return true;
    } */

    /**
     * THIS IS NOT USED BY THE UPGRADER
     *
     * @return boolean
     */
/*    function _checkChangesetAudit($schema)
    {
        $aResult = $this->oAuditor->oDBAuditor->queryAudit(null, null, $schema, DB_UPGRADE_ACTION_UPGRADE_SUCCEEDED);
        if ($aResult)
        {
            foreach ($aResult as $k=>$v)
            {
                $this->oLogger->log($v['schema_name'].' upgraded to version '.$v['version'].' on '.$v['updated']);
            }
        }
        return true;
    }*/}

    /**
     * retrieve a list of available upgrade packages
     * THIS IS NOT USED BY THE UPGRADER
     *
     * @return array
     */
/*    function _getPackageList()
    {
        $aFiles = array();
        $dh = opendir($this->upgradePath);
        if ($dh) {
            while (false !== ($file = readdir($dh)))
            {
                $aMatches = array();
                if (preg_match('/openads_upgrade_[\w\W\d]+_to_([\w\W\d])+\.xml/', $file, $aMatches))
                {
                    $aFiles[] = $file;
                }
            }
        }
        closedir($dh);
        return $aFiles;
    }*/
?>
