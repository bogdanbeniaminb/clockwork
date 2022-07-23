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
 * Install the module hooks
 *
 * developed by Barbu Bogdan-Beniamin
 * v. 0.1
 */
class HooksInstaller
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
    public function getHooks()
    {
        return property_exists($this->module, 'hooks')
            ? $this->module->hooks
            : [];
    }

    /**
     * register the module hooks
     *
     * @return boolean
     */
    public function registerHooks()
    {
        if ($hooks = $this->getHooks()) {
            foreach ($hooks as $hook) {
                $result = $this->module->registerHook($hook);
                if (!$result) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * unregister the module hooks
     *
     * @return boolean
     */
    public function unregisterHooks()
    {
        if ($hooks = $this->getHooks()) {
            foreach ($hooks as $hook) {
                $this->module->unregisterHook($hook);
            }
        }
        return true;
    }
}
