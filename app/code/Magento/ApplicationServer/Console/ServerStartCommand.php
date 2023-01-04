<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ApplicationServer\Console;

use Magento\ApplicationServer\App\Request;
use Magento\ApplicationServer\App\Application;
use Magento\ApplicationServer\ObjectManager\AppObjectManager;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Http;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Swoole\Exception;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function str_starts_with;
use const SWOOLE_LOG_WARNING;

/**
 * Class StoreListCommand
 *
 * Command for listing the configured stores
 */
class ServerStartCommand extends Command
{
    private const DEFAULT_PORT = 9501;
    private const OPTION_PORT = 'port';
    private const OPTION_AREA = 'area';


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('server:run')
            ->setDescription('Run application server')
            ->setDefinition($this->getOptionsList());
    }

    /**
     * Get list of options
     * @return array
     */
    private function getOptionsList() : array
    {
        return [
            new InputOption(
                self::OPTION_PORT,
                'p',
                InputOption::VALUE_OPTIONAL,
                'port to serv on',
                self::DEFAULT_PORT
            ),
            new InputOption(
                self::OPTION_AREA,
                'a',
                InputOption::VALUE_OPTIONAL,
                'port to serv on',
                'graphql'
            ),
        ];
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $debug = function ($val) use ($output) {
            $output->writeln(time() . ': '. (is_string($val) ? $val : stripslashes(json_encode($val, JSON_PRETTY_PRINT))));
        };

        $server = new Server("0.0.0.0", $input->getOption(self::OPTION_PORT));

        $server->set([
            // Process
            'daemonize' => 0,
            'open_cpu_affinity' => true,
            // Server
            'reactor_num' => 1,
            'worker_num' => 1, // todo: set to number of CPU
            'log_level' => SWOOLE_LOG_WARNING,
            'enable_coroutine' => false,


            'admin_server' => false,
        ]);

        $bootstrap = Bootstrap::create(BP, $_SERVER);
        $globalObjectManager = $bootstrap->getObjectManager();

        $areaCode = $input->getOption(self::OPTION_AREA);

        $globalObjectManager->configure(
            $globalObjectManager->get(ConfigLoaderInterface::class)->load($areaCode)
        );
        $globalObjectManager->get(State::class)->setAreaCode($areaCode);

        $server->on("start", function (Server $server) use ($areaCode, $debug) {
            $debug(str_repeat('=', 40));
            $debug("Start server in $areaCode");
            $debug("    kill -USR1  " . $server->master_pid);

            // listen to  ctrl + c
            Process::signal(2, function () use ($server) {
                $server->shutdown();
            });
        });


        $server->on(
            "request",
            function (\Swoole\Http\Request $request, Response $swooleResponse)
                use ($bootstrap, $globalObjectManager, $debug)
            {
                $appRequest = new Request($request);
                $objectManager = new AppObjectManager(
                    $globalObjectManager,
                    [
                        \Magento\Framework\App\Request\Http::class => $appRequest,
                    ]
                );

                try {
                    $app = $objectManager->create(
                        Application::class,
                        [
                            'request' => $appRequest,
                        ]
                    );
                    $response = $app->launch($appRequest);

                    $this->sendResponse($response, $swooleResponse);
                } catch (Throwable $t) {
                    $debug($t);
                    $swooleResponse->status(500, 'Exception: ' . $t);
                    $swooleResponse->end();
                } finally {
                    ObjectManager::setInstance($globalObjectManager);
                }

            }
        );

        try {
            $server->start();
        } catch (Exception $e) {
            $debug('Application is stopped');
        }

        return Command::SUCCESS;
    }


    /**
     * @param HttpResponse $response
     * @param Response $swooleResponse
     * @return void
     */
    private function sendResponse(HttpResponse $response, Response $swooleResponse): void
    {
        if ($response->getHttpResponseCode() != 200) {
            $swooleResponse->status($response->getHttpResponseCode(0));
        }

        foreach ($response->getHeaders() as $header) {
            $swooleResponse->header($header->getFieldName(), $header->getFieldValue());
        }

        $swooleResponse->end($response->getContent());
    }
}
