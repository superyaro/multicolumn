<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 snowflake productions GmbH
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Backend\ClickMenu\ClickMenu;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class tx_multicolumn_alt_clickmenu
{
    /**
     * clickMenu object
     *
     * @var        clickMenu
     */
    protected $pObj;

    /**
     * Adding tx_multicolumn_parentid on new context menu
     *
     * @param ClickMenu $backRef The calling object. Value by reference.
     * @param array $menuItems array with the currently collected menu items to show
     * @param string $table table name of clicked item
     * @param int $uid uID of clicked item
     *
     * @return array Modified $menuItems array
     */
    public function main(clickMenu &$backRef, $menuItems, $table, $uid)
    {
        if ($table == 'tt_content') {
            $this->pObj = $backRef;
            $this->rec = $this->pObj->rec;
            $isMulticolumnContainer = ($this->rec['CType'] == 'multicolumn') ? true : false;

            // has menuitems the new button? add multicolumnparent id to request
            if ($this->rec['tx_multicolumn_parentid'] && $menuItems['new']) {
                //add multicolumn_parent_id to new url
                $this->addMultiColumnParentIdToNewItem($menuItems['new'], $this->rec['tx_multicolumn_parentid']);
            }

            // is element a multicolumn container ? add column pasting
            if ($isMulticolumnContainer && $menuItems['pasteafter']) {
                $multicolumnMenuItems = $this->getMulticolumnPasteIntoItems($uid);
                $this->addMulticolumnMenuItemsAfterPasteafter($multicolumnMenuItems, $menuItems);
            }
        }

        return $menuItems;
    }

    /**
     * Adding multicolumn menu items after pasteafter
     *
     * @param array $multicolumnMenuItems Array with multicolumn menu items
     * @param array $menuItems Array with orginal menu items from alt_clickmenu
     */
    protected function addMulticolumnMenuItemsAfterPasteafter(array $multicolumnMenuItems, array &$menuItems)
    {
        $sortedItems = [];
        foreach ($menuItems as $menuKey => $item) {
            if ($menuKey == 'pasteafter') {
                $sortedItems[$menuKey] = $item;
                $sortedItems = array_merge($sortedItems, $multicolumnMenuItems);
            } else {
                $sortedItems[$menuKey] = $item;
            }
        }

        $menuItems = $sortedItems;
    }

    /**
     * Builds multicolumn menu items
     *
     * @param int $multicolumnUid multicolumn content element uid
     *
     * @return array
     */
    protected function getMulticolumnPasteIntoItems($multicolumnUid)
    {
        $multicolumnMenuItems = [];
        $multicolumnMenuItems['multicolumnspacer-1'] = 'spacer';

        $multicolumnSelectItems = [];
        $LL = tx_multicolumn_div::includeBeLocalLang();
        $pasteIntoLink = $this->getPasteIntoLink($multicolumnUid);

        // get number of columns
        $columns = tx_multicolumn_db::getNumberOfColumnsFromContainer($multicolumnUid);

        $columnIndex = 0;
        $columnTitle = $GLOBALS['LANG']->getLLL('multicolumColumn', $LL) . ' ' . $GLOBALS['LANG']->getLLL('cms_layout.columnTitle', $LL);

        while ($columnIndex < $columns) {
            $item = $pasteIntoLink;
            // set correct title
            $item[1] .= ' ' . $GLOBALS['LANG']->getLLL('multicolumColumn.clickmenu', $LL) . ' ' . ($columnIndex + 1);
            // add colPos
            $item[3] = str_replace(
                trim(GeneralUtility::quoteJSvalue('tx_multicolumn[action]=pasteInto'), '\''),
                trim(GeneralUtility::quoteJSvalue('tx_multicolumn[action]=pasteInto&colPos=' . (tx_multicolumn_div::colPosStart + $columnIndex)), '\''),
                $item[3]
            );

            $multicolumnMenuItems['multicolumn-pasteinto-' . $columnIndex] = $item;

            $columnIndex++;
        }

        $multicolumnMenuItems['multicolumnspacer-2'] = 'spacer';

        return $multicolumnMenuItems;
    }

    /**
     * Builds the modified clickMenu->DB_paste link (adding specific colPos and multicolum_parentid)
     *
     * @param int $multicolumnUid multicolumn content element uid
     *
     * @return array Item array, element in $menuItems
     */
    protected function getPasteIntoLink($multicolumnUid)
    {
        $selItem = $this->pObj->clipObj->getSelectedRecord();
        $elInfo = [
            \TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs($selItem['_RECORD_TITLE'], $GLOBALS['BE_USER']->uc['titleLen']),
            \TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs(\TYPO3\CMS\Backend\Utility\BackendUtility::getRecordTitle('tt_content', $this->rec), $BE_USER->uc['titleLen']),
            $this->pObj->clipObj->currentMode(),
        ];

        $item = $this->pObj->DB_paste('tt_content', -$multicolumnUid, 'into', $elInfo);
        $pasteUrl = $this->pObj->clipObj->pasteUrl('tt_content', -$multicolumnUid, 0);
        $item[3] = str_replace(
            \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($pasteUrl . '&redirect='),
            \TYPO3\CMS\Core\Utility\GeneralUtility::quoteJSvalue($pasteUrl . '&tx_multicolumn[action]=pasteInto&tx_multicolumn_parentid=' . (int)$multicolumnUid . '&redirect='),
            $item[3]
        );

        return $item;
    }

    /**
     * Add &defVals[tt_content][tx_multicolumn_parentid]= to new item
     *
     * @param array $newItem Array of new item in context menu
     * @param int $multicolumnParentId parent id of multicolumn item
     */
    protected function addMultiColumnParentIdToNewItem(array &$newItem, $multicolumnParentId)
    {
        $newItem[3] = str_replace('new', 'new&defVals[tt_content][tx_multicolumn_parentid]=' . intval($multicolumnParentId), $newItem[3]);
    }
}
