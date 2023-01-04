<?php

namespace Magento\ApplicationServer\ObjectManager;

use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\FactoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Layout;

/**
 * ObjectManager for integration test framework.
 */
class AppObjectManager extends \Magento\Framework\App\ObjectManager
{
    /**
     * @var array
     */
    private $_classesToDestruct = [
        Layout::class,
        Registry::class,
        Magento\Framework\App\Response\Http::class,
    ];

    /**
     * @var array
     */
    private $persistedInstances = [];


    /**
     * Clear InstanceManager cache.
     *
     * @return ObjectManager
     */
    public function clear()
    {
        foreach ($this->_classesToDestruct as $className) {
            if (isset($this->_sharedInstances[$className])) {
                $this->_sharedInstances[$className] = null;
            }
        }

        return $this;
    }

    public function __construct(
        ObjectManagerInterface $parentObjectManager,
        array $sharedInstances = []
    ) {
        $this->_config = $parentObjectManager->get(ConfigInterface::class);

        $getParentInstances = (fn ($om) => $om->_sharedInstances)->bindTo($parentObjectManager);

        $this->persistedInstance = $getParentInstances($parentObjectManager);

        $getFactory = (fn ($om) => $om->_factory)->bindTo($parentObjectManager);

        $factory = clone $getFactory($parentObjectManager);
        $factory->setObjectManager($this);
        $this->_factory = $factory;


        $this->_sharedInstances = $sharedInstances;
        $this->_sharedInstances[ObjectManagerInterface::class] = $this;
        $this->persistedInstances[ObjectManagerInterface::class] = $this;
        self::setInstance($this);
    }

    /**
     * @param $type
     * @inheritDoc
     */
    public function get($type)
    {
        $type = \ltrim($type, '\\');
        $type = $this->_config->getPreference($type);

        if (!isset($this->_sharedInstances[$type])) {
            if (isset($this->persistedInstances[$type]) /* parent have shared instance */) {
                return $this->persistedInstances[$type];
            }
            $this->_sharedInstances[$type] = $this->_factory->create($type);
        }

        return $this->_sharedInstances[$type];
    }


    /**
     * Get list of services requested from OM
     *
     * @return array
     */
    public function getServiceList(): array
    {
        return array_keys($this->_sharedInstances);
    }

}
