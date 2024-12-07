<?php


abstract class FrontController extends FrontControllerCore
{
    public $clockworkProfiler = null;

    public function __construct()
    {
        if (_PS_MODE_DEV_) {
            if (!class_exists(BB\Clockwork\Profiler::class)) {
                Module::getInstanceByName('clockwork');
            }
            if (class_exists(BB\Clockwork\Profiler::class)) {
                $this->clockworkProfiler = BB\Clockwork\Profiler::getInstance();
                $this->clockworkProfiler->stamp('config');
                $this->clockworkProfiler->setController($this);
            }
        }

        parent::__construct();

        if (_PS_MODE_DEV_ && $this->clockworkProfiler) {
            $this->clockworkProfiler->stamp('__construct');
        }
    }

    public function run()
    {
        // only execute while in debug mode
        if (!_PS_MODE_DEV_) {
            return parent::run();
        }

        $this->init();
        if ($this->clockworkProfiler) {
            $this->clockworkProfiler->stamp('init');
        }

        if ($this->checkAccess()) {
            if ($this->clockworkProfiler) {
                $this->clockworkProfiler->stamp('checkAccess');
            }

            if (!$this->content_only && ($this->display_header || !empty($this->className))) {
                $this->setMedia();
                if ($this->clockworkProfiler) {
                    $this->clockworkProfiler->stamp('setMedia');
                }
            }

            $this->postProcess();
            if ($this->clockworkProfiler) {
                $this->clockworkProfiler->stamp('postProcess');
            }

            if (!$this->content_only && ($this->display_header || !empty($this->className))) {
                $this->initHeader();
                if ($this->clockworkProfiler) {
                    $this->clockworkProfiler->stamp('initHeader');
                }
            }

            $this->initContent();
            if ($this->clockworkProfiler) {
                $this->clockworkProfiler->stamp('initContent');
            }

            if (!$this->content_only && ($this->display_footer || !empty($this->className))) {
                $this->initFooter();
                if ($this->clockworkProfiler) {
                    $this->clockworkProfiler->stamp('initFooter');
                }
            }

            if ($this->ajax) {
                $action = Tools::toCamelCase(Tools::getValue('action'), true);

                if (!empty($action) && method_exists($this, 'displayAjax' . $action)) {
                    $this->{'displayAjax' . $action}();
                } elseif (method_exists($this, 'displayAjax')) {
                    $this->displayAjax();
                }
                if ($this->clockworkProfiler) {
                    $this->clockworkProfiler->processData();
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
            if ($this->clockworkProfiler) {
                $this->clockworkProfiler->stamp('display');
            }
        }

        // Process all profiling data
        if ($this->clockworkProfiler) {
            $this->clockworkProfiler->processData();

            $this->context->smarty->assign(
                $this->clockworkProfiler->getSmartyVariables()
            );
        }


        if (strpos($content, '{$content}') === false) {
            return $content;
        }

        $this->outPutHtml = $content;
        return '';
    }
}
