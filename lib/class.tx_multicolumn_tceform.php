<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 snowflake productions GmbH
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
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
class tx_multicolumn_tceform
{
    /**
     * Current items
     *
     * @var        array
     */
    protected $items = [];

    /**
     * How many items exists?
     *
     * @var        int
     */
    protected $itemsCount = 0;

    /**
     * TCA config of colPos
     *
     * @var        array
     */
    protected $config = [];

    /**
     * Current row
     *
     * @var        array
     */
    protected $row = [];

    /**
     * Locallang array
     *
     * @var        array
     */
    protected $LL = [];

    /**
     * Decide what to to do. Action is defined in TCA $itemsProc['config']['multicolumnProc']
     *
     * @param    array $itemsProc
     * @param \TYPO3\CMS\Backend\Form\FormDataProvider\TcaSelectItems $pObj
     * @param    int $pid : Target pid of page
     */
    public function init($itemsProc, \TYPO3\CMS\Backend\Form\FormDataProvider\TcaSelectItems $pObj)
    {
        // call proFunc
        if (!empty($itemsProc['config']['itemsProcFunctions'])) {
            foreach ($itemsProc['config']['itemsProcFunctions'] as $procFunc) {
                if (!empty($procFunc)) {
                    \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($procFunc, $itemsProc, $pObj);
                }
            }
        }

        $this->items = &$itemsProc['items'];
        $this->itemsCount = count($this->items);
        $this->config = $itemsProc['config'];
        $this->row = $itemsProc['row'];
        $this->LL = tx_multicolumn_div::includeBeLocalLang($this->config['multicolumnLL']);

        call_user_func([
            'tx_multicolumn_tceform',
            $this->config['multicolumnProc'],
        ]);
    }

    /**
     * Builds a list of all multicolumn container of current pid to use in itemsProc[items]
     */
    protected function buildMulticolumnList()
    {
        if ($containers = tx_multicolumn_db::getContainersFromPid($this->row['pid'], $this->row['sys_language_uid'][0])) {
            if ($this->items) {
                $itemsUidList = $this->getItemsUidList();
            }

            $multicolumnContainerItem = 1;
            foreach ($containers as $container) {
                // do not list current container
                if ($this->row['uid'] == $container['uid']) {
                    continue;
                }

                if (!\TYPO3\CMS\Core\Utility\GeneralUtility::inList($itemsUidList, $container['uid'])) {
                    $title = $container['header'] ? $container['header'] : $GLOBALS['LANG']->getLLL('pi1_title', $this->LL) . ' ' . $multicolumnContainerItem . ' (uid: ' . $container['uid'] . ')';
                    $this->items[] = [
                        0 => $title,
                        1 => $container['uid'],
                        2 => null,
                    ];
                    $multicolumnContainerItem++;
                }
            }
        }
    }

    /**
     * Get all uids of $itemsProc['items']
     */
    protected function getItemsUidList()
    {
        $itemsUidList = null;
        $comma = null;

        foreach ($this->items as $item) {
            if ($item[1]) {
                $itemsUidList = $comma . $item[1];
                $comma = ',';
            }
        }

        return $itemsUidList;
    }

    /**
     * Add dynamic colPos to content element if its inside a multicolumn container
     */
    protected function buildDynamicCols()
    {
        if (!$this->row['tx_multicolumn_parentid']) {
            return;
        }

        $numberOfColumns = tx_multicolumn_db::getNumberOfColumnsFromContainer($this->row['tx_multicolumn_parentid']);

        $columnIndex = 0;
        $columnTitle = $GLOBALS['LANG']->getLLL('multicolumColumn', $this->LL) . ' ' . $GLOBALS['LANG']->getLLL('cms_layout.columnTitle', $this->LL);

        while ($columnIndex < $numberOfColumns) {
            $this->items[] = [
                0 => $columnTitle . ' ' . ($columnIndex + 1),
                1 => tx_multicolumn_div::colPosStart + $columnIndex,
                2 => null,
            ];

            $columnIndex++;
        }
    }
}
