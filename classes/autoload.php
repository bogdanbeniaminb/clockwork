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

if (!defined('_PS_VERSION_')) {
    exit();
}

require_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');

/** autoload classes as needed - either using our namespace, or without namespace*/
spl_autoload_register(function ($class) {
    if (strpos($class, 'BB\\Clockwork') === 0) {
        // remove the namespace
        $file = dirname(__FILE__) . '/';
        $file .= str_replace(['BB\\Clockwork\\', '\\'], ['', '/'], $class);
        $file .= '.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
});
