<?php

class SmartyResourceParent extends SmartyResourceParentCore
{
    protected function fetch($name, &$source, &$mtime)
    {
        foreach ($this->paths as $path) {
            if (Tools::file_exists_cache($file = $path . $name)) {
                if (_PS_MODE_DEV_) {
                    $start = microtime(true);

                    $source = implode('', [
                        '<!-- begin ' . $file . ' -->',
                        file_get_contents($file),
                        '<!-- end ' . $file . ' -->',
                    ]);

                    if (!class_exists(BB\Clockwork\Profiler::class)) {
                        @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
                    }
                    if (class_exists(BB\Clockwork\Profiler::class)) {
                        $end = microtime(true);
                        $profiler = BB\Clockwork\Profiler::getInstance();
                        $profiler->addView('Fetch ' . $name, [
                            'template' => $name,
                            'name' => $name,
                        ], [
                            'time' => $start,
                            'duration' => ($end - $start) * 1000,
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
