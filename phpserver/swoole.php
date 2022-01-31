<?php
use Magento\Framework\App\Bootstrap;

$debug = function ($val) {
    echo  time() , ': ' ,  (is_string($val) ? $val : stripslashes(json_encode($val, JSON_PRETTY_PRINT))) , PHP_EOL;
};
require __DIR__ . '/../app/bootstrap.php';

$server = new Swoole\HTTP\Server("0.0.0.0", 9501);


$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

//var_dump(array_keys($objectManager->_sharedInstances));
$areaCode = 'frontend';

$objectManager->configure(
    $objectManager->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class)->load($areaCode)
);

$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode($areaCode);
$keepList = array_keys($objectManager->_sharedInstances);
//$debug($keepList);

$server->on("start", function (\Swoole\Http\Server $server) use ($areaCode, $debug) {

    $debug( str_repeat('=', 40));
    $debug( "Start server in $areaCode");
    $debug("kill -USR1  " . $server->master_pid);
    //kill -USR2 MASTER_PID
});


$server->on(
    "request",
    function (\Swoole\Http\Request $request, \Swoole\Http\Response $response)
        use ($bootstrap, $objectManager, $keepList, $debug) {

        $uri = $request->server['request_uri'];
        $debug("Request Start: {$request->server['request_uri']}");
        //$debug($request);

        if (strpos($uri, '/static/') !== 0) {
            try {
                $magentoR = new \Magento\Framework\App\Http\Swoole\Request($request);
                $objectManager->_sharedInstances[\Magento\Framework\App\Request\Http::class] = $magentoR;

                /** @var \Magento\Framework\App\Http $app */
                $app = $bootstrap->createApplication(
                    \Magento\Framework\App\Http\SwooleApplication::class,
                    [
                        'request' => $magentoR,
                    ]
                );

                $r = $app->launch();
            } catch (\Throwable $t) {
                $debug($t);
                $response->end('Error:' . $t );
                return;
            }
            if ($r->getHttpResponseCode() == 500) {
                $debug($r);
            }

            foreach ($r->getHeaders() as $header) {
                $response->header($header->getFieldName(), $header->getFieldValue());
            }

            $s = strlen($r->getContent());
            $debug("Request size: {$s}");
            $response->end($r->getContent() );


            // var_dump($objectManager->_sharedInstances);
            $keys = array_diff(array_keys($objectManager->_sharedInstances), $keepList);
//            $debug($keepList);
            foreach ( $keys as $key) {
                unset($objectManager->_sharedInstances[$key]);
            }

            $debug("Request End: {$request->server['request_uri']}");

        } else {
            // $response->
        }


    }
);

$server->start();
