<?php

use Clockwork\Support\Vanilla\Clockwork;
use Clockwork\Web\Web;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

@require_once __DIR__ . '/../../clockwork.php';

class ClockworkWebModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $clockwork = Clockwork::init([
            'features' => [
                'performance' => [
                    'client_metrics' => true,
                ],
            ],
            'register_helpers' => true,
            'storage_files_path' => CLOCKWORK_DIR . '/storage/clockwork',
            'api' => __PS_BASE_URI__ . 'modules/clockwork/actions/endpoint.php?request=',
            'toolbar' => true,
            'web' => [
                'enable' => __PS_BASE_URI__ . 'module/clockwork/web',
                'path' => CLOCKWORK_DIR . '/views/web/public',
                'uri' =>  __PS_BASE_URI__ . 'modules/clockwork/views/web/public',
            ],
        ]);

        $clockwork->returnWeb();
        $this->profiler->disable();
        exit();

        // $index = (new Web)->asset('index.html');
        // $path = $index['path'];
        // $html = file_get_contents($path);
        // $html = str_replace('</title>', '</title><base href="' . _PS_BASE_URL_SSL_ . __PS_BASE_URI__ .  'module/clockwork/vendor/web?asset=">', $html);
        // file_put_contents($path . '-temp', $html);
        // $this->getWebAsset('index.html-temp')->send();
    }

    public function getWebAsset($path)
    {
        $this->ensureClockworkIsEnabled();

        $asset = (new Web)->asset($path);

        if (!$asset) throw new NotFoundHttpException();

        return new BinaryFileResponse($asset['path'], 200, ['Content-Type' => $asset['mime']]);
    }

    public function ensureClockworkIsEnabled()
    {
        if (!Configuration::get('BB_CLOCKWORK_ENABLED')) {
            // die;
        }
    }
}
