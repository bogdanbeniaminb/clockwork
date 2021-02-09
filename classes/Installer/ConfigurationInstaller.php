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

use Db;
use DbQuery;
use Tools;
use Configuration;

/**
 * Install the module hooks
 *
 * developed by Barbu Bogdan-Beniamin
 * v. 0.1
 */
class ConfigurationInstaller
{
    protected $module = null;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * remove configuration items from the DB
     *
     * @return boolean
     */
    public function removeConfiguration()
    {
        $query = new DbQuery();
        $query
            ->select('name')
            ->from('configuration')
            ->where('name LIKE \'BB_CLOCKWORK_%\'');
        $results = Db::getInstance()->executeS($query);
        if (empty($results)) {
            return true;
        }

        $configurationKeys = array_column($results, 'name');
        foreach ($configurationKeys as $configurationKey) {
            Configuration::deleteByName($configurationKey);
        }

        return true;
    }
}
