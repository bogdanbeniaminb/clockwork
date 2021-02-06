<?php

use BB\Clockwork\Profiler;

abstract class Controller extends ControllerCore
{
    protected $profiler = null;

    public function __construct()
    {
        if (_PS_MODE_DEV_) {
            if (!class_exists(Profiler::class)) {
                include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
            }
            if (class_exists(Profiler::class)) {
                $this->profiler = Profiler::getInstance();
                $this->profiler->stamp('config');
                $this->profiler->setController($this);
            }
        }

        parent::__construct();

        if (_PS_MODE_DEV_ && $this->profiler) {
            $this->profiler->stamp('__construct');
        }
    }

    public function run()
    {
        // only execute while in debug mode
        if (!_PS_MODE_DEV_) {
            return parent::run();
        }

        $this->init();
        if ($this->profiler) {
            $this->profiler->stamp('init');
        }

        if ($this->checkAccess()) {
            if ($this->profiler) {
                $this->profiler->stamp('checkAccess');
            }

            if (!$this->content_only && ($this->display_header || !empty($this->className))) {
                $this->setMedia();
                if ($this->profiler) {
                    $this->profiler->stamp('setMedia');
                }
            }

            $this->postProcess();
            if ($this->profiler) {
                $this->profiler->stamp('postProcess');
            }

            if (!$this->content_only && ($this->display_header || !empty($this->className))) {
                $this->initHeader();
                if ($this->profiler) {
                    $this->profiler->stamp('initHeader');
                }
            }

            $this->initContent();
            if ($this->profiler) {
                $this->profiler->stamp('initContent');
            }

            if (!$this->content_only && ($this->display_footer || !empty($this->className))) {
                $this->initFooter();
                if ($this->profiler) {
                    $this->profiler->stamp('initFooter');
                }
            }

            if ($this->ajax) {
                $action = Tools::toCamelCase(Tools::getValue('action'), true);

                if (!empty($action) && method_exists($this, 'displayAjax' . $action)) {
                    $this->{'displayAjax' . $action}();
                } elseif (method_exists($this, 'displayAjax')) {
                    $this->displayAjax();
                }

                return;
            }
        } else {
            $this->initCursedPage();
        }

        echo $this->displayProfiling();
    }

    /**
     * Display profiling
     * If it's a migrated page, we change the outPutHtml content, otherwise
     * we display the profiling at the end of the page.
     *
     * @return string
     */
    public function displayProfiling(): string
    {
        $content = '';
        if (!empty($this->redirect_after)) {
            $this->context->smarty->assign(
                [
                    'redirectAfter' => $this->redirect_after,
                ]
            );
        } else {
            // Call original display method
            ob_start();
            $this->display();
            $displayOutput = ob_get_clean();
            if (empty($displayOutput) && isset($this->outPutHtml)) {
                $displayOutput = $this->outPutHtml;
            }

            $content .= $displayOutput;
            if ($this->profiler) {
                $this->profiler->stamp('display');
            }
        }

        // Process all profiling data
        if ($this->profiler) {
            $this->profiler->processData();

            $this->context->smarty->assign(
                $this->profiler->getSmartyVariables()
            );
        }


        if (strpos($content, '{$content}') === false) {
            return $content;
        }

        $this->outPutHtml = $content;
        return '';
    }
}
