<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ApplicationServer\App;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\InputException;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Framework\Stdlib\Cookie\CookieMetadata;
use Magento\Framework\Stdlib\Cookie\CookieScopeInterface;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\Cookie\SensitiveCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;
use function count;

/**
 * CookieManager is facade on top of request and response to helps manage the setting, retrieving and deleting of cookies.
 *
 * To aid in security, the cookie manager will make it possible for the application to indicate if the cookie contains
 * sensitive data so that extra protection can be added to the contents of the cookie as well as how the browser
 * stores the cookie.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class CookieManager implements CookieManagerInterface
{
    /**#@+
     * Constants for Cookie manager.
     * RFC 2109 - Page 15
     * http://www.ietf.org/rfc/rfc6265.txt
     */
    const MAX_NUM_COOKIES = 50;
    const MAX_COOKIE_SIZE = 4096;
    const EXPIRE_NOW_TIME = 1;
    const EXPIRE_AT_END_OF_SESSION_TIME = 0;
    /**#@-*/

    /**#@+
     * Constant for metadata array key
     */
    const KEY_EXPIRE_TIME = 'expiry';
    /**#@-*/

    /**
     * @param CookieScopeInterface $scope
     * @param Response $response
     * @param LoggerInterface $logger
     */
    public function __construct(
        private Http $request,
        private Response $response,
        private CookieScopeInterface $scope,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Set a value in a private cookie with the given $name $value pairing.
     *
     * Sensitive cookies cannot be accessed by JS. HttpOnly will always be set to true for these cookies.
     *
     * @param string $name
     * @param string $value
     * @param SensitiveCookieMetadata $metadata
     * @return void
     * @throws FailureToSendException Cookie couldn't be sent to the browser.  If this exception isn't thrown,
     * there is still no guarantee that the browser received and accepted the cookie.
     * @throws CookieSizeLimitReachedException Thrown when the cookie is too big to store any additional data.
     * @throws InputException If the cookie name is empty or contains invalid characters.
     */
    public function setSensitiveCookie($name, $value, SensitiveCookieMetadata $metadata = null)
    {
        $metadataArray = $this->scope->getSensitiveCookieMetadata($metadata)->__toArray();
        $this->setCookie((string)$name, (string)$value, $metadataArray);
    }

    /**
     * Set a value in a public cookie with the given $name $value pairing.
     *
     * Public cookies can be accessed by JS. HttpOnly will be set to false by default for these cookies,
     * but can be changed to true.
     *
     * @param string $name
     * @param string $value
     * @param PublicCookieMetadata $metadata
     * @return void
     * @throws FailureToSendException If cookie couldn't be sent to the browser.
     * @throws CookieSizeLimitReachedException Thrown when the cookie is too big to store any additional data.
     * @throws InputException If the cookie name is empty or contains invalid characters.
     */
    public function setPublicCookie($name, $value, PublicCookieMetadata $metadata = null)
    {
        $metadataArray = $this->scope->getPublicCookieMetadata($metadata)->__toArray();
        $this->setCookie((string)$name, (string)$value, $metadataArray);
    }

    /**
     * Set a value in a cookie with the given $name $value pairing.
     *
     * @param string $name
     * @param string $value
     * @param array $metadataArray
     * @return void
     * @throws FailureToSendException If cookie couldn't be sent to the browser.
     * @throws CookieSizeLimitReachedException Thrown when the cookie is too big to store any additional data.
     * @throws InputException If the cookie name is empty or contains invalid characters.
     */
    protected function setCookie($name, $value, array $metadataArray)
    {
        $expire = $this->computeExpirationTime($metadataArray);

        $this->checkAbilityToSendCookie($name, $value);

        $setCookieHeader = new \Laminas\Http\Header\SetCookie(
            $name,
            $value,
            $expire,
            $this->extractValue(CookieMetadata::KEY_PATH, $metadataArray, ''),
            $this->extractValue(CookieMetadata::KEY_DOMAIN, $metadataArray, ''),
            $this->extractValue(CookieMetadata::KEY_SECURE, $metadataArray, null),
            $this->extractValue(CookieMetadata::KEY_HTTP_ONLY, $metadataArray, false),
            null,
            null,
            $this->extractValue(CookieMetadata::KEY_SAME_SITE, $metadataArray, 'Lax')
        );

        $this->response->addHeader($setCookieHeader);

    }

    /**
     * Retrieve the size of a cookie.
     *
     * The size of a cookie is determined by the length of 'name=value' portion of the cookie.
     *
     * @param string $name
     * @param string $value
     * @return int
     */
    private function sizeOfCookie($name, $value)
    {
        // The constant '1' is the length of the equal sign in 'name=value'.
        return strlen($name) + 1 + strlen($value);
    }

    /**
     * Determines ability to send cookies, based on the number of existing cookies and cookie size
     *
     * @param string $name
     * @param string|null $value
     * @return void if it is possible to send the cookie
     * @throws CookieSizeLimitReachedException Thrown when the cookie is too big to store any additional data.
     * @throws InputException If the cookie name is empty or contains invalid characters.
     */
    private function checkAbilityToSendCookie($name, $value)
    {
        if ($name == '' || preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new InputException(
                new Phrase(
                    'Cookie name cannot be empty and cannot contain these characters: =,; \\t\\r\\n\\013\\014'
                )
            );
        }

        $numCookies = count($this->response->getCookie());

        $sizeOfCookie = $this->sizeOfCookie($name, $value);

        if ($numCookies > static::MAX_NUM_COOKIES) {
            $this->logger->warning(
                'Unable to send the cookie. Maximum number of cookies would be exceeded.',
                [
                    'cookies' => $numCookies,
                    'user-agent' => $this->httpHeader->getHttpUserAgent()
                ]
            );
        }

        if ($sizeOfCookie > static::MAX_COOKIE_SIZE) {
            throw new CookieSizeLimitReachedException(
                new Phrase(
                    'Unable to send the cookie. Size of \'%name\' is %size bytes.',
                    [
                        'name' => $name,
                        'size' => $sizeOfCookie,
                    ]
                )
            );
        }
    }

    /**
     * Determines the expiration time of a cookie.
     *
     * @param array $metadataArray
     * @return int in seconds since the Unix epoch.
     */
    private function computeExpirationTime(array $metadataArray)
    {
        if (isset($metadataArray[CookieManager::KEY_EXPIRE_TIME])
            && $metadataArray[CookieManager::KEY_EXPIRE_TIME] < time()
        ) {
            $expireTime = $metadataArray[CookieManager::KEY_EXPIRE_TIME];
        } else {
            if (isset($metadataArray[CookieMetadata::KEY_DURATION])) {
                $expireTime = $metadataArray[CookieMetadata::KEY_DURATION] + time();
            } else {
                $expireTime = CookieManager::EXPIRE_AT_END_OF_SESSION_TIME;
            }
        }

        return $expireTime;
    }

    /**
     * Determines the value to be used as a $parameter.
     *
     * If $metadataArray[$parameter] is not set, returns the $defaultValue.
     *
     * @param string $parameter
     * @param array $metadataArray
     * @param string|boolean|int|null $defaultValue
     * @return string|boolean|int|null
     */
    private function extractValue($parameter, array $metadataArray, $defaultValue)
    {
        if (array_key_exists($parameter, $metadataArray)) {
            return $metadataArray[$parameter];
        } else {
            return $defaultValue;
        }
    }

    /**
     * Retrieve a value from a cookie.
     *
     * @param string $name
     * @param string|null $default The default value to return if no value could be found for the given $name.
     * @return string|null
     */
    public function getCookie($name, $default = null)
    {
        $cookies = $this->request->getHeader('Cookie');
        if ($cookies)  {
            if (isset($cookies[$name])) {
                return (string) $cookies[$name];
            } else {
                return $default;
            }
        }
        return $default;
    }

    /**
     * Deletes a cookie with the given name.
     *
     * @param string $name
     * @param CookieMetadata $metadata
     * @return void
     * @throws FailureToSendException If cookie couldn't be sent to the browser.
     *     If this exception isn't thrown, there is still no guarantee that the browser
     *     received and accepted the request to delete this cookie.
     * @throws InputException If the cookie name is empty or contains invalid characters.
     */
    public function deleteCookie($name, CookieMetadata $metadata = null)
    {
        $metadataArray = $this->scope->getCookieMetadata($metadata)->__toArray();

        // explicitly set an expiration time in the metadataArray.
        $metadataArray[CookieManager::KEY_EXPIRE_TIME] = CookieManager::EXPIRE_NOW_TIME;

        $this->checkAbilityToSendCookie($name, '');

        // cookie value set to empty string to delete from the remote client
        $this->setCookie($name, '', $metadataArray);

    }
}
