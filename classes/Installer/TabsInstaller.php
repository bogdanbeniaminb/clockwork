<?php

/**
 * since 2021 Bogdan Barbu
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@codingheads.com so we can send you a copy immediately.
 *
 *  @author    Bogdan Barbu <barbu.bogdan.beniamin@gmail.com>
 *  @copyright since 2021 Bogdan Barbu
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace BB\Clockwork\Installer;

use Tab;
use Db;
use DbQuery;
use Language;

if (!defined('_PS_VERSION_')) {
    exit();
}

/**
 * Install the module tabs
 *
 * developed by Barbu Bogdan-Beniamin
 * v. 0.1
 */
class TabsInstaller
{
    protected $module = null;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * returns the hooks for this module
     *
     * @return array
     */
    public function getTabs()
    {
        return property_exists($this->module, 'adminTabs')
            ? $this->module->adminTabs
            : [];
    }

    /**
     * Add Tabs
     *
     * @return bool
     */
    public function installTabs()
    {
        $tabs = $this->getTabs();

        if (empty($tabs)) {
            return true;
        }

        $result = true;
        foreach ($tabs as $tab_info) {
            // skip if the tab already exists
            if (Tab::getIdFromClassName($tab_info['class_name'])) {
                continue;
            }

            // are we in PS 1.6?
            $ps_16 = version_compare(_PS_VERSION_, '1.7', '<');

            // replace ShopParameters with AdminParameters in PS 1.6
            $parentClassName = $tab_info['parent_class_name'];
            if ($parentClassName == 'ShopParameters' && $ps_16) {
                $parentClassName = 'AdminPreferences';
            }

            if ($ps_16 && ($tab_info['class_name'] == 'AdminClockwork')) {
                continue;
            }

            // skip if we are using a class that is not available in PS 1.6
            $availableOn17 = [
                'DEFAULT',
                'SELL',
                'IMPROVE',
                'CONFIGURE',
                'AdminClockwork',
            ];
            if ($ps_16 && in_array($parentClassName, $availableOn17)) {
                $parentClassName = 'AdminPreferences';
            }

            // install the tab
            $tab = new Tab();
            $parentId = (int) Tab::getIdFromClassName($parentClassName);
            if (!empty($parentId)) {
                $tab->id_parent = $parentId;
            }
            $tab->class_name = $tab_info['class_name'];
            $tab->module = $this->module->name;

            foreach (Language::getLanguages(false) as $language) {
                if (empty($tab_info['name'][$language['iso_code']])) {
                    $tab->name[$language['id_lang']] = $tab_info['name']['en'];
                } else {
                    $tab->name[$language['id_lang']] =
                        $tab_info['name'][$language['iso_code']];
                }
            }

            $tab->active = true;
            if (isset($tab_info['visible'])) {
                $tab->active = $tab_info['visible'];
            }

            if (isset($tab_info['icon']) && property_exists('Tab', 'icon')) {
                $tab->icon = $tab_info['icon'];
            }
            $result = $result && (bool) $tab->add();
        }

        return $result;
    }

    /**
     * remove tabs from the backend
     *
     * @return bool
     */
    public function uninstallTabs()
    {
        // search if we have installed tabs and remove the existing tabs
        $query = new DbQuery();
        $query
            ->select('*')
            ->from('tab')
            ->where('module = \'' . pSQL($this->module->name) . '\'');
        $tabs = Db::getInstance()->executeS($query);

        if (empty($tabs)) {
            return true;
        }

        // remove existing tabs
        $result = true;
        foreach ($tabs as $tab_info) {
            $tab = new Tab((int) $tab_info['id_tab']);
            $result = $result && (bool) $tab->delete();
        }
        return $result;
    }
}
