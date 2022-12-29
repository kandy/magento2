<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ApplicationServer\App;

use Laminas\Stdlib\Parameters;


class Request extends \Magento\Framework\App\Request\Http
{

    public function __construct(\Swoole\Http\Request $request)
    {
            $this->setEnv(new Parameters($_ENV));
            $httpParams = [];
            foreach ($request->header as  $name => $val) {
                $httpParams['http_' . strtolower($name)]  = $val;
            }
            $this->setContent($request->getContent());
            $this->setServer(new Parameters($request->server + $httpParams));
            $this->setMethod($request->server['request_method']);
            $this->setUri($request->server['request_uri']);

            if (!empty($request->get)) {
                $this->setQuery(new Parameters($request->get));
            }
            if (!empty($request->post)) {
                $this->setPost(new Parameters($request->post));
            }
            if (!empty($request->cookie)) {
                $this->setCookies($request->cookie);
            }
            $this->getHeaders()->addHeaders($request->header);

//        if (str_starts_with((string) $request->headers->get('CONTENT_TYPE'), 'application/x-www-form-urlencoded') &&
//            in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), ['PUT', 'PATCH', 'DELETE'])) {
//            parse_str($request->getContent(), $data);
//
//            $request->request = new ParameterBag($data);
//        }

        //@TODO
//            if ($_FILES) {
//                // convert PHP $_FILES superglobal
//                $files = $this->mapPhpFiles();
//                $this->setFiles(new Parameters($files));
//            }


    }


    public function getHttpHost($trimPort = true)
    {
        $httpHost = $this->getServer('HTTP_HOST');
        if (empty($httpHost)) {
            return false;
        }
        if ($trimPort) {
            $host = explode(':', $httpHost);
            return $host[0];
        }
        return $httpHost;
    }

    public function getOriginalPathInfo()
    {
        return $this->getServer('REQUEST_URI') ?? '/';
    }

    public function setRouteName($route)
    {
        $this->route = $route;
        return $this;
    }

    public function getServer($name = null, $default = null)
    {
        return parent::getServer($name === null ? null : strtolower($name), $default);
    }


    /**
     * Retrieve the module name
     *
     * @return string
     */
    public function getModuleName()
    {
        return (string)$this->module;
    }

    /**
     * Set the module name to use
     *
     * @param string $value
     * @return $this
     */
    public function setModuleName($value)
    {
        $this->module = $value;
        return $this;
    }

    /**
     * Retrieve the controller name
     *
     * @return string
     */
    public function getControllerName()
    {
        return (string)$this->controller;
    }

    /**
     * Set the controller name to use
     *
     * @param string $value
     * @return $this
     */
    public function setControllerName($value)
    {
        $this->controller = $value;
        return $this;
    }

    /**
     * Retrieve the action name
     *
     * @return string
     */
    public function getActionName()
    {
        return (string)$this->action;
    }

    /**
     * Set the action name
     *
     * @param string $value
     * @return $this
     */
    public function setActionName($value)
    {
        $this->action = $value;
        return $this;
    }

    public function isSecure()
    {
        return false;
    }

    public function isSafeMethod()
    {
       return true;
    }


    /**
     * Retrieve cookie value
     *
     * @param string|null $name
     * @param string|null $default
     * @return string|null
     */
    public function getCookie($name = null, $default = null)
    {
        return null;
    }

    public function isAjax()
    {
        $requestedWith = $this->getHeaders()->get('X_REQUESTED_WITH');
        $isXmlHttpRequest =  false !== $requestedWith && $requestedWith->getFieldValue() === 'XMLHttpRequest';

        return $isXmlHttpRequest
            || $this->getParam('ajax')
            || $this->getParam('isAjax');
    }

}
