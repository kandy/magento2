<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ApplicationServer\App;

use Exception;
use InvalidArgumentException;
use Magento\Framework\App;
use Magento\Framework\App\ExceptionHandlerInterface;
use Magento\Framework\App\FrontControllerInterface as FrontController;
use Magento\Framework\App\HttpRequestInterface;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;

class Application implements AppInterface
{
    private ObjectManagerInterface $objectManager;
    private Manager $eventManager;
    private Registry $registry;
    private ExceptionHandlerInterface $exceptionHandler;

    public function __construct(
       ObjectManagerInterface  $objectManager,
       Manager $eventManager,
       Registry $registry,
       ExceptionHandlerInterface $exceptionHandler
    ) {
        $this->objectManager = $objectManager;
        $this->eventManager = $eventManager;
        $this->registry = $registry;
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * Run application
     *
     * @return ResponseInterface
     * @throws LocalizedException|InvalidArgumentException
     */
    public function launch(?HttpRequestInterface $request = null)
    {
        $request ??= $this->objectManager->get(HttpRequestInterface::class);
        /** @var FrontController $frontController */
        $frontController = $this->objectManager->create(FrontController::class);
        $response = $frontController->dispatch(
            $request ?? $this->objectManager->get(HttpRequestInterface::class)
        );
        $response = $this->handreResponse($response);
        $response = $this->handleHead($request, $response);
        $response = $this->dispatchBeforeSendEvent($request, $response);

        return $response;
    }

    public function catchException(App\Bootstrap $bootstrap, Exception $exception)
    {
        return $this->exceptionHandler->handle($bootstrap, $exception, $this->_response, $this->_request);
    }

    /**
     * @param ResponseInterface $result
     * @return \Magento\Framework\App\Response\HttpInterface
     */
    private function handreResponse($result): \Magento\Framework\App\Response\HttpInterface
    {
        // TODO: Temporary solution until all controllers return ResultInterface (MAGETWO-28359);
        if ($result instanceof ResultInterface) {
            $this->registry->register('use_page_cache_plugin', true, true);
            $response = $this->objectManager->create(\Magento\Framework\App\Response\HttpInterface::class);
            $result->renderResult($response);
        } elseif ($result instanceof HttpInterface) {
            $response = $result;
        } else {
            throw new InvalidArgumentException('Invalid return type');
        }

        return $response;
    }

    /**
     * @param  $request
     * @param HttpInterface|null $response
     * @return void
     */
    private function handleHead(HttpRequestInterface $request, HttpInterface $response): HttpInterface
    {
        if ($request->isHead() && $response->getHttpResponseCode() == 200) {
            $contentLength = mb_strlen($response->getContent(), '8bit');
            $response->clearBody();
            $response->setHeader('Content-Length', $contentLength);
        }
        return $response;
    }

    /**
     * @param mixed $request
     * @param HttpInterface|null $response
     * @return array
     */
    private function dispatchBeforeSendEvent(mixed $request, HttpInterface $response): HttpInterface
    {
        // This event gives possibility to launch something before sending output (allow cookie setting)
        $eventParams = ['request' => $request, 'response' => $response];
        $this->eventManager->dispatch('controller_front_send_response_before', $eventParams);
        return $response;
    }
}
