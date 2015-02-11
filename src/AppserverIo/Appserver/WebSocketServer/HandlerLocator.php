<?php

/**
 * AppserverIo\Appserver\WebSocketServer\HandlerLocator
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
namespace AppserverIo\Appserver\WebSocketServer;

use AppserverIo\Appserver\WebSocketProtocol\RequestInterface;
use AppserverIo\Appserver\WebSocketProtocol\HandlerContextInterface;

/**
 * The handler resource locator implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class HandlerLocator implements ResourceLocatorInterface
{

    /**
     * Tries to locate the handler that handles the request and returns the instance if one can be found.
     *
     * @param \AppserverIo\Appserver\WebSocketProtocol\HandlerContextInterface $handlerManager The handler manager
     * @param \AppserverIo\Appserver\WebSocketProtocol\RequestInterface        $request        The request instance
     *
     * @return \Ratchet\MessageComponentInterface The handler that maps the request instance
     *
     * @throws \AppserverIo\Appserver\WebSocketServer\HandlerNotFoundException
     *
     * @see \AppserverIo\Appserver\WebSocketServer\Service\Locator\ResourceLocatorInterface::locate()
     */
    public function locate(HandlerContextInterface $handlerManager, RequestInterface $request)
    {

        // load the path to the (almost virtual handler)
        $handlerPath = $request->getHandlerPath();

        // iterate over all handlers and return the matching one
        foreach ($handlerManager->getHandlerMappings() as $urlPattern => $handlerName) {
            if (fnmatch($urlPattern, $handlerPath)) {
                return $handlerManager->getHandler($handlerName);
            }
        }

        // throw an exception if no servlet matches the handler path
        throw new HandlerNotFoundException(
            sprintf("Can't find handler for requested path %s", $handlerPath)
        );
    }
}