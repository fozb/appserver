<?php

/**
 * AppserverIo\Appserver\PersistenceContainer\GenericObjectFactory
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

namespace AppserverIo\Appserver\PersistenceContainer;

use Rhumsaa\Uuid\Uuid;
use AppserverIo\Storage\GenericStackable;
use AppserverIo\Psr\Application\ApplicationInterface;

/**
 * A thread which creates timer instances.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class GenericObjectFactory extends \Thread implements ObjectFactoryInterface
{

    /**
     * Initializes and the timer factory.
     */
    public function __construct()
    {
        // initialize the member variables
        $this->dispatched = false;
        $this->mutex = \Mutex::create();
    }

    /**
     * Injects the application instance.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function injectApplication(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Injects the container for factory created instances.
     *
     * @param \AppserverIo\Storage\GenericStackable $instances The container for the factory created instances
     *
     * @return void
     */
    public function injectInstances(GenericStackable $instances)
    {
        $this->instances = $instances;
    }

    /**
     * Returns the application instance.
     *
     * @return \AppserverIo\Psr\Application\ApplicationInterface The application instance
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Create a new instance with the passed data.
     *
     * @param string      $className The fully qualified class name to return the instance for
     * @param string|null $sessionId The session-ID, necessary to inject stateful session beans (SFBs)
     * @param array       $args      Arguments to pass to the constructor of the instance
     *
     * @return object The instance itself
     */
    public function newInstance($className, $sessionId = null, array $args = array())
    {

        // lock the method
        \Mutex::lock($this->mutex);

        do {
            // create a counter
            $counter = 0;

            // if this is the first loop
            if ($counter === 0) {
                // we're not dispatched
                $this->dispatched = false;

                // initialize the data
                $this->args = $args;
                $this->sessionId = $sessionId;
                $this->className = $className;

                // notify the thread
                $this->synchronized(function ($self) {
                    $self->notify();
                }, $this);
            }

            // raise the counter
            $counter++;

            // we wait for 100 iterations
            if ($counter > 100) {
                throw new \Exception('Requested instance can\'t be created');
            }

            // lower system load a bit
            usleep(100);

        } while ($this->dispatched === false);

        // try to load the last created instance
        if (isset($this->instances[$last = sizeof($this->instances) - 1])) {
            $instance = $this->instances[$last];
        } else {
            throw new \Exception('Requested instance can\'t be created');
        }

        // unlock the method
        \Mutex::unlock($this->mutex);

        // return the created instance
        return $instance;
    }

    /**
     * Invoked when the thread starts.
     *
     * @return void
     * @see Stackable::run()
     */
    public function run()
    {

        // make the application available and register the class loaders
        $application = $this->getApplication();
        $application->registerClassLoaders();

        // run forever
        while (true) {
            // wait until we've been notified
            $this->synchronized(function ($self) {
                $self->wait();
            }, $this);

            // create the instance
            $instance = $application->search('ProviderInterface')
                                    ->newInstance(
                                        $this->className,
                                        $this->sessionId,
                                        $this->args
                                    );

            // stack the instance
            $this->instances[] = $instance;

            // we're dispatched now
            $this->dispatched = true;
        }
    }
}
