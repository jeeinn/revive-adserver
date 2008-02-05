<?php

/*
+---------------------------------------------------------------------------+
| OpenX v${RELEASE_MAJOR_MINOR}                                           |
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
$Id:$
*/

/**
 * @package    OpenXDll
 * @author     Ivan Klishch <iklishch@lohika.com>
 *
 */

// Require the following classes:
require_once MAX_PATH . '/lib/OA/Dll.php';
require_once MAX_PATH . '/lib/OA/Dll/BannerInfo.php';
require_once MAX_PATH . '/lib/OA/Dal/Statistics/Banner.php';


/**
 * The OA_Dll_Banner class extends the OA_Dll class.
 *
 */

class OA_Dll_Banner extends OA_Dll
{
    /**
     * This method sets BannerInfo from a data array.
     *
     * @access private
     *
     * @param OA_Dll_BannerInfo &$oBanner
     * @param array $bannerData
     *
     * @return boolean
     */
    function _setBannerDataFromArray(&$oBanner, $bannerData)
    {
        $bannerData['htmlTemplate'] = $bannerData['htmltemplate'];
        $bannerData['imageURL']     = $bannerData['imageurl'];
        $bannerData['fileName']     = $bannerData['filename'];
        $bannerData['storageType']  = $bannerData['storagetype'];
        $bannerData['bannerName']   = $bannerData['description'];
        $bannerData['campaignId']   = $bannerData['campaignid'];
        $bannerData['bannerId']     = $bannerData['bannerid'];

        $oBanner->readDataFromArray($bannerData);
        return  true;
    }

    /**
     * This method performs data validation for a banner, for example to check
     * that an email address is an email address. Where necessary, the method connects
     * to the OA_Dal to obtain information for other business validations.
     *
     * @access private
     *
     * @param OA_Dll_BannerInfo &$oBanner  Banner object.
     *
     * @return boolean  Returns false if fields are not valid and true if valid.
     *
     */
    function _validate(&$oBanner)
    {
        if (isset($oBanner->bannerId)) {
            // When modifying a banner, check correct field types are used and the bannerID exists.
            if (!$this->checkStructureNotRequiredIntegerField($oBanner, 'campaignId') ||
                !$this->checkStructureRequiredIntegerField($oBanner, 'bannerId') ||
                !$this->checkIdExistence('banners', $oBanner->bannerId)) {
                return false;
            }
        } else {
            // When adding a banner, check that the required field 'campaignId' is correct.
            if (!$this->checkStructureRequiredIntegerField($oBanner, 'campaignId')) {
                return false;
            }
        }

        if (isset($oBanner->campaignId) &&
            !$this->checkIdExistence('campaigns', $oBanner->campaignId)) {
            return false;
        }

        $storageTypes = array('sql', 'web', 'url', 'html', 'network', 'txt');

        if (isset($oBanner->storageType) and !in_array($oBanner->storageType, $storageTypes)) {
            $this->raiseError('Field \'storageType\' must be one of the enum: \'sql\', \'web\', \'url\', \'html\', \'network\', \'txt\'');
            return false;
        }

        if (!$this->checkStructureNotRequiredStringField($oBanner, 'bannerName', 255) ||
            !$this->checkStructureNotRequiredStringField($oBanner, 'fileName', 255) ||
            !$this->checkStructureNotRequiredStringField($oBanner, 'imageURL', 255) ||
            !$this->checkStructureNotRequiredStringField($oBanner, 'htmlTemplate') ||
            !$this->checkStructureNotRequiredIntegerField($oBanner, 'width') ||
            !$this->checkStructureNotRequiredIntegerField($oBanner, 'height') ||
            !$this->checkStructureNotRequiredIntegerField($oBanner, 'weight') ||
            !$this->checkStructureNotRequiredStringField($oBanner, 'url') ||
            !$this->checkStructureNotRequiredBooleanField($oBanner, 'active') ||
            !$this->checkStructureNotRequiredStringField($oBanner, 'adserver')
            ) {
            return false;
        }

        return true;
    }

    /**
     * This method performs data validation for statistics methods(bannerId, date).
     *
     * @access private
     *
     * @param integer  $bannerId
     * @param date     $oStartDate
     * @param date     $oEndDate
     *
     * @return boolean
     *
     */
    function _validateForStatistics($bannerId, $oStartDate, $oEndDate)
    {
        if (!$this->checkIdExistence('banners', $bannerId) ||
            !$this->checkDateOrder($oStartDate, $oEndDate)) {
            return false;
        }

        return true;
    }

    /**
     * This function calls a method in the OA_Dll class which checks permissions.
     *
     * @access public
     *
     * @param integer $advertiserId  Banner ID
     *
     * @return boolean  False if access is denied and true if allowed.
     */
    function checkStatisticsPermissions($bannerId)
    {
       if (!$this->checkPermissions(
            array(OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER, OA_ACCOUNT_ADVERTISER),
            'banners', $bannerId)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * This method modifies an existing banner. Undefined fields do not change
     * and defined fields with a NULL value also remain unchanged.
     *
     * @access public
     *
     * @param OA_Dll_BannerInfo &$oBanner <br />
     *          <b>For adding</b><br />
     *          <b>Required properties:</b> campaignId<br />
     *          <b>Optional properties:</b> bannerName, storageType, fileName, imageURL, htmlTemplate, width, height, weight, url<br />
     *
     *          <b>For modify</b><br />
     *          <b>Required properties:</b> bannerId<br />
     *          <b>Optional properties:</b> campaignId, bannerName, storageType, fileName, imageURL, htmlTemplate, width, height, weight, url<br />
     *
     * @return boolean  True if the operation was successful
     *
     */
    function modify(&$oBanner)
    {

        if (!isset($oBanner->bannerId)) {
            // Add
            $oBanner->setDefaultForAdd();
            if (!$this->checkPermissions($this->aAllowAdvertiserAndAbovePerm,
                 'campaigns', $oBanner->campaignId, OA_PERM_BANNER_EDIT)) {

                return false;
            }
        } else {
            // Edit
            if (!$this->checkPermissions($this->aAllowAdvertiserAndAbovePerm,
                 'banners', $oBanner->bannerId, OA_PERM_BANNER_EDIT)) {

                return false;
            }
        }

        $bannerData =  (array) $oBanner;

        // Name
        $bannerData['bannerid']     = $oBanner->bannerId;
        $bannerData['campaignid']   = $oBanner->campaignId;
        $bannerData['description']  = $oBanner->bannerName;
        $bannerData['storagetype']  = $oBanner->storageType;
        $bannerData['filename']     = $oBanner->fileName;
        $bannerData['imageurl']     = $oBanner->imageURL;
        $bannerData['htmltemplate'] = $oBanner->htmlTemplate;
        $bannerData['active']       = ($oBanner->active) ? 't' : 'f';

        // Different content types have different requirements...
        switch($bannerData['storagetype']) {
            case 'html':
                $bannerData['contenttype'] = $bannerData['storagetype'];
            break;
        }
        if ($this->_validate($oBanner)) {
            $doBanner = OA_Dal::factoryDO('banners');
            if (!isset($bannerData['bannerId'])) {
                $doBanner->setFrom($bannerData);
                $oBanner->bannerId = $doBanner->insert();
            } else {
                $doBanner->get($bannerData['bannerId']);
                $doBanner->setFrom($bannerData);
                $doBanner->update();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * This method deletes an existing banner.
     *
     * @access public
     *
     * @param integer $bannerId  The ID of the banner to delete.
     *
     * @return boolean  True if the operation was successful
     *
     */
    function delete($bannerId)
    {
        if (!$this->checkPermissions(
            array(OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER),
            'banners', $bannerId)) {
            return false;
        }

       if (isset($bannerId)) {
            $doBanner = OA_Dal::factoryDO('banners');
            $doBanner->bannerid = $bannerId;
            $result = $doBanner->delete();
        } else {
            $result = false;
        }

        if ($result) {
            return true;
        } else {
            $this->raiseError('Unknown bannerId Error');
            return false;
        }
    }

    /**
     * This method returns BannerInfo for a specified banner.
     *
     * @access public
     *
     * @param int $bannerId
     * @param OA_Dll_BannerInfo &$oBanner
     *
     * @return boolean
     */
    function getBanner($bannerId, &$oBanner)
    {
        if ($this->checkIdExistence('banners', $bannerId)) {
            if (!$this->checkPermissions(null, 'banners', $bannerId)) {
                return false;
            }
            $doBanner = OA_Dal::factoryDO('banners');
            $doBanner->get($bannerId);
            $bannerData = $doBanner->toArray();

            $oBanner = new OA_Dll_BannerInfo();

            $this->_setBannerDataFromArray($oBanner, $bannerData);
            return true;

        } else {

            $this->raiseError('Unknown bannerId Error');
            return false;
        }
    }

    /**
     * This method returns a list of banners for a specified campaign.
     *
     * @access public
     *
     * @param int $campaignId
     * @param array &$aBannerList
     *
     * @return boolean
     */
    function getBannerListByCampaignId($campaignId, &$aBannerList)
    {
        $aBannerList = array();

        if (!$this->checkIdExistence('campaigns', $campaignId)) {
                return false;
        }

        if (!$this->checkPermissions(null, 'campaigns', $campaignId)) {
            return false;
        }

        $doBanner = OA_Dal::factoryDO('banners');
        $doBanner->campaignid = $campaignId;
        $doBanner->find();

        while ($doBanner->fetch()) {
            $bannerData = $doBanner->toArray();

            $oBanner = new OA_Dll_BannerInfo();
            $this->_setBannerDataFromArray($oBanner, $bannerData);

            $aBannerList[] = $oBanner;
        }
        return true;
    }

    /**
     * This method returns daily statistics for a banner for a specified period.
     *
     * @access public
     *
     * @param integer $bannerId The ID of the banner to view statistics for
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array &$rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>day date</b> The day
     *   <li><b>requests integer</b> The number of requests for the day
     *   <li><b>impressions integer</b> The number of impressions for the day
     *   <li><b>clicks integer</b> The number of clicks for the day
     *   <li><b>revenue decimal</b> The revenue earned for the day
     *   </ul>
     *
     * @return boolean  True if the operation was successful and false if not.
     *
     */
    function getBannerDailyStatistics($bannerId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($bannerId)) {
            return false;
        }

        if ($this->_validateForStatistics($bannerId, $oStartDate, $oEndDate)) {
            $dalBanner = new OA_Dal_Statistics_Banner();
            $rsStatisticsData = $dalBanner->getBannerDailyStatistics($bannerId, $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method returns publisher statistics for a banner for a specified period.
     *
     * @access public
     *
     * @param integer $bannerId The ID of the banner to view statistics for
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array &$rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>publisherID integer</b> The ID of the publisher
     *   <li><b>publisherName string (255)</b> The name of the publisher
     *   <li><b>requests integer</b> The number of requests for the day
     *   <li><b>impressions integer</b> The number of impressions for the day
     *   <li><b>clicks integer</b> The number of clicks for the day
     *   <li><b>revenue decimal</b> The revenue earned for the day
     *   </ul>
     *
     * @return boolean  True if the operation was successful and false if not.
     *
     */
    function getBannerPublisherStatistics($bannerId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($bannerId)) {
            return false;
        }

        if ($this->_validateForStatistics($bannerId, $oStartDate, $oEndDate)) {
            $dalBanner = new OA_Dal_Statistics_Banner();
            $rsStatisticsData = $dalBanner->getBannerPublisherStatistics($bannerId, $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method returns zone statistics for a banner for a specified period.
     *
     * @access public
     *
     * @param integer $bannerId The ID of the banner to view statistics for
     * @param date $oStartDate The date from which to get statistics (inclusive)
     * @param date $oEndDate The date to which to get statistics (inclusive)
     * @param array &$rsStatisticsData The data returned by the function
     *   <ul>
     *   <li><b>publisherID integer</b> The ID of the publisher
     *   <li><b>publisherName string (255)</b> The name of the publisher
     *   <li><b>zoneID integer</b> The ID of the zone
     *   <li><b>zoneName string (255)</b> The name of the zone
     *   <li><b>requests integer</b> The number of requests for the day
     *   <li><b>impressions integer</b> The number of impressions for the day
     *   <li><b>clicks integer</b> The number of clicks for the day
     *   <li><b>revenue decimal</b> The revenue earned for the day
     *   </ul>
     *
     * @return boolean  True if the operation was successful and false if not.
     *
     */
    function getBannerZoneStatistics($bannerId, $oStartDate, $oEndDate, &$rsStatisticsData)
    {
        if (!$this->checkStatisticsPermissions($bannerId)) {
            return false;
        }

        if ($this->_validateForStatistics($bannerId, $oStartDate, $oEndDate)) {
            $dalBanner = new OA_Dal_Statistics_Banner();
            $rsStatisticsData = $dalBanner->getBannerZoneStatistics($bannerId, $oStartDate, $oEndDate);

            return true;
        } else {
            return false;
        }
    }


}

?>
