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
 *  @author    Bogdan Barbu <barbu.bogdan.beniamin@google.com>
 *  @copyright since 2021 Bogdan Barbu
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use BB\Clockwork\Installer\Installer;

if (!defined('_PS_VERSION_')) {
    exit();
}

define('CLOCKWORK_DIR', dirname(__FILE__));

/** add composer autoload */
$vendor_autoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

class Clockwork extends Module
{
    // public $adminTabs = [
    //     [
    //         'name' => [
    //             'en' => 'Clockwork',
    //             'fr' => 'Clockwork',
    //         ],
    //         'class_name' => 'AdminClockwork',
    //         'parent_class_name' => 'CONFIGURE',
    //         'visible' => true,
    //         'icon' => 'monochrome_photos',
    //     ],
    //     [
    //         'name' => [
    //             'en' => 'Clockwork Settings',
    //             'fr' => 'Paramètres d\'Clockwork',
    //         ],
    //         'class_name' => 'AdminClockworkSettings',
    //         'parent_class_name' => 'AdminClockwork',
    //         'visible' => true,
    //     ],
    // ];

    public $hooks = [
        'actionFrontControllerSetMedia',
        'actionAjaxDieBefore',
    ];

    public function __construct()
    {
        $this->name = 'clockwork';
        $this->tab = 'others';
        $this->version = '1.0.12';
        $this->author = 'Bogdan Barbu';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;
        $this->secure_key = Tools::encrypt($this->name);

        parent::__construct();

        $this->displayName = $this->l('ClockWork Integration');
        $this->description = $this->l('Analyse the performance of your site using Clockwork.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('BB_CLOCKWORK_ENABLED')) {
            $this->warning = $this->l('Clockwork is not enabled.');
        }
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // install hooks and tabs
        $installer = new Installer($this);
        if (!$installer->install()) {
            return false;
        }

        // setup webp
        if (!Configuration::hasKey('BB_CLOCKWORK_ENABLED')) {
            if (!$this->setDefaultSettings()) {
                return false;
            }
        }

        return true;
    }

    public function uninstall()
    {

        // uninstall hooks and tabs
        if (!(new Installer($this))->uninstall()) {
            return false;
        }

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * set the default settings
     */
    public function setDefaultSettings()
    {
        Configuration::updateGlobalValue('BB_CLOCKWORK_ENABLED', 0);
        return true;
    }

    /** get the content for the admin configuration page */
    public function getContent()
    {
        $output = null;

        Tools::redirectAdmin(
            Context::getContext()->link->getAdminLink(
                'AdminClockworkSettings',
                true
            )
        );

        // display the configuration form
        return $output;
    }

    /**
     * returns whether WebP is active
     */
    public function clockworkActive(): bool
    {
        return (bool) Configuration::get('BB_CLOCKWORK_ENABLED');
    }

    public function hookActionAjaxDieBefore($params)
    {
        if (!_PS_MODE_DEV_) {
            return;
        }
        $controller = $params['controller'];
        if (!$controller instanceof FrontController) {
            return;
        }

        Profiler::getInstance()->processData();
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        if (!_PS_MODE_DEV_) return;

        $this->context->controller->registerJavascript(
            'clockwork-toolbar',
            'https://cdn.jsdelivr.net/gh/underground-works/clockwork-browser@1/dist/toolbar.js',
            [
                'position' => 'bottom',
                'priority' => 100,
                'server' => 'remote',
            ]
        );
    }
}
