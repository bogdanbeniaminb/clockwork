<?php

class SmartyDevTemplate extends SmartyDevTemplateCore
{
    public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null, $display = false, $merge_tpl_vars = true, $no_output_filter = false)
    {
        $profiler = null;
        if (_PS_MODE_DEV_) {
            if (!class_exists(BB\Clockwork\Profiler::class)) {
                @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
            }
            if (class_exists(BB\Clockwork\Profiler::class)) {
                $profiler = BB\Clockwork\Profiler::getInstance();
            }
        }

        $start = microtime(true);

        $result = parent::fetch($template, $cache_id, $compile_id, $parent, $display, $merge_tpl_vars, $no_output_filter);

        $end = microtime(true);

        if ($profiler) {
            if (null !== $template) {
                $tpl = $template->template_resource;
            } else {
                $tpl = $this->template_resource;
            }

            $profiler->addView($tpl, [
                'template' => $tpl,
                'cache_id' => $cache_id,
                'compile_id' => $compile_id,
                'parent' => $parent,
                'display' => $display,
                'merge_tpl_vars' => $merge_tpl_vars,
                'no_output_filter' => $no_output_filter,
            ], [
                'time' => $start,
                'duration' => ($end - $start) * 1000,
            ]);
        }

        return $result;
    }

    public function _subTemplateRender(
        $template,
        $cache_id,
        $compile_id,
        $caching,
        $cache_lifetime,
        $data,
        $scope,
        $forceTplCache,
        $uid = null,
        $content_func = null
    ) {

        $profiler = null;
        if (_PS_MODE_DEV_) {
            if (!class_exists(BB\Clockwork\Profiler::class)) {
                @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
            }
            if (class_exists(BB\Clockwork\Profiler::class)) {
                $profiler = BB\Clockwork\Profiler::getInstance();
            }
            $start = microtime(true);
        }


        $result = parent::_subTemplateRender(
            $template,
            $cache_id,
            $compile_id,
            $caching,
            $cache_lifetime,
            $data,
            $scope,
            $forceTplCache,
            $uid,
            $content_func
        );


        if ($profiler) {
            $end = microtime(true);

            if (null !== $template) {
                $tpl = json_encode($template);
            } else {
                $tpl = 'unknown';
            }

            $profiler->addView($tpl, [
                'template' => $tpl,
                'cache_id' => $cache_id,
                'compile_id' => $compile_id,
            ], [
                'time' => $start,
                'duration' => ($end - $start) * 1000,
            ]);
        }

        return $result;
    }
}
