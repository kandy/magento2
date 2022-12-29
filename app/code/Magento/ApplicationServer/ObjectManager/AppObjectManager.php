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
        Registry::class
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
        $this->persistedInstances = $getParentInstances($parentObjectManager);
        unset($this->persistedInstances[ObjectManagerInterface::class]);

       // var_dump(array_keys( $this->persistedInstances));

        $factory = clone $parentObjectManager->get(FactoryInterface::class);
        $factory->setObjectManager($this);
        $this->_factory = $factory;

        $this->_sharedInstances = $sharedInstances;
        $this->_sharedInstances[ObjectManagerInterface::class] = $this;

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


    public function getServiceList(): array
    {
        return array_keys($this->_sharedInstances);
    }

}
