<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ApplicationServer\App;

use Magento\Framework\App\AreaList;
use Magento\Framework\App\ExceptionHandlerInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;

class Application  extends \Magento\Framework\App\Http
{
    public function __construct(
        ObjectManagerInterface                 $objectManager,
        Manager                                $eventManager,
        AreaList                               $areaList,
        \Magento\ApplicationServer\App\Request $request,
        ResponseHttp                           $response,
        ConfigLoaderInterface                  $configLoader,
        State                                  $state,
        Registry                               $registry,
        ExceptionHandlerInterface              $exceptionHandler = null
    ) {
        parent::__construct(
            $objectManager,
            $eventManager,
            $areaList,
            $request,
            $response,
            $configLoader,
            $state,
            $registry,
            $exceptionHandler
        );
    }

    /**
     * Run application
     *
     * @return ResponseInterface
     * @throws LocalizedException|\InvalidArgumentException
     */
    public function launch()
    {
        /** @var \Magento\Framework\App\FrontControllerInterface $frontController */
        $frontController = $this->_objectManager->create(\Magento\Framework\App\FrontControllerInterface::class);
        $result = $frontController->dispatch($this->_request);
        // TODO: Temporary solution until all controllers return ResultInterface (MAGETWO-28359)
        if ($result instanceof ResultInterface) {
            $this->registry->register('use_page_cache_plugin', true, true);
            $result->renderResult($this->_response);
        } elseif ($result instanceof HttpInterface) {
            $this->_response = $result;
        } else {
            throw new \InvalidArgumentException('Invalid return type');
        }
        if ($this->_request->isHead() && $this->_response->getHttpResponseCode() == 200) {
           // $this->handleHeadRequest();
        }
        // This event gives possibility to launch something before sending output (allow cookie setting)
        $eventParams = ['request' => $this->_request, 'response' => $this->_response];
        $this->_eventManager->dispatch('controller_front_send_response_before', $eventParams);
        return $this->_response;
    }

}
