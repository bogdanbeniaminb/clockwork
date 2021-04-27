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

if (!defined('_PS_VERSION_')) {
    exit();
}

/**
 * Helper for the module install process
 *
 * developed by Barbu Bogdan-Beniamin
 * v. 0.1
 */
class Installer
{
    protected $module = null;

    /**
     * @var HooksInstaller
     */
    protected $hooksInstaller;

    /**
     * @var TabsInstaller
     */
    protected $tabsInstaller;

    /**
     * @var ConfigurationInstaller
     */
    protected $configurationInstaller;

    /**
     * @param \Module $module
     */
    public function __construct($module)
    {
        $this->module = $module;
        $this->hooksInstaller = new HooksInstaller($module);
        $this->tabsInstaller = new TabsInstaller($module);
        $this->configurationInstaller = new ConfigurationInstaller($module);
    }

    /**
     * install the hooks and tabs
     *
     * @return boolean
     */
    public function install()
    {
        $hooks_result = $this->hooksInstaller->registerHooks();
        $tabs_result = $this->tabsInstaller->installTabs();
        return $hooks_result && $tabs_result;
    }

    /**
     * uninstall the hooks and tabs
     *
     * @return boolean
     */
    public function uninstall($with_configuration = true)
    {
        $hooks_result = $this->hooksInstaller->unregisterHooks();
        $tabs_result = $this->tabsInstaller->uninstallTabs();
        $config_result =
            !$with_configuration ||
            $this->configurationInstaller->removeConfiguration();
        return $hooks_result && $tabs_result && $config_result;
    }
}
