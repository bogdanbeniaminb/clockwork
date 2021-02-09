<?php

use BB\Clockwork\Profiler;

class SmartyResourceParent extends SmartyResourceParentCore
{
    protected function fetch($name, &$source, &$mtime)
    {
        foreach ($this->paths as $path) {
            if (Tools::file_exists_cache($file = $path . $name)) {
                if (_PS_MODE_DEV_) {
                    $source = implode('', [
                        '<!-- begin ' . $file . ' -->',
                        file_get_contents($file),
                        '<!-- end ' . $file . ' -->',
                    ]);

                    if (!class_exists(Profiler::class)) {
                        @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
                    }
                    if (class_exists(Profiler::class)) {
                        $profiler = Profiler::getInstance();
                        $profiler->addView($file, [
                            'template' => $file,
                            'name' => $name,
                        ], [
                            'time' => microtime(true),
                            // 'duration' => ($end - $start) * 1000,
                        ]);
                    }
                } else {
                    $source = file_get_contents($file);
                }
                $mtime = filemtime($file);

                return;
            }
        }
    }
}
