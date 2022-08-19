<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ApplicationServer\GraphQl;

use Magento\Framework\Config\DataInterface;
use Magento\Framework\GraphQl\Config\ConfigElementFactoryInterface;
use Magento\Framework\GraphQl\Config\ConfigElementInterface;
use Magento\Framework\GraphQl\ConfigInterface;
use Magento\Framework\GraphQl\Query\Fields;

/**
 * Provides access to typing information for a configured GraphQL schema.
 */
class Config implements ConfigInterface
{
    /**
     * @var DataInterface
     */
    private $configData;

    /**
     * @var ConfigElementFactoryInterface
     */
    private $configElementFactory;

    /**
     * @var Fields
     */
    private $queryFields;

    /**
     * @param DataInterface $data
     * @param ConfigElementFactoryInterface $configElementFactory
     * @param Fields $queryFields
     */
    public function __construct(
        DataInterface $data,
        ConfigElementFactoryInterface $configElementFactory,
        Fields $queryFields
    ) {
        $this->configData = $data;
        $this->configElementFactory = $configElementFactory;
        $this->queryFields = $queryFields;
    }

    /**
     * @inheritdoc
     */
    public function getConfigElement(string $configElementName) : ConfigElementInterface
    {
        $data = $this->configData->get($configElementName);
        if (!isset($data['type'])) {
            throw new \LogicException(
                sprintf('Config element "%s" is not declared in GraphQL schema', $configElementName)
            );
        }

        return $this->configElementFactory->createFromConfigData($data);
    }

    /**
     * @inheritdoc
     */
    public function getDeclaredTypes() : array
    {
        $types = [];
        foreach ($this->configData->get(null) as $item) {
            if (isset($item['type'])) {
                $types[] = [
                    'name' => $item['name'],
                    'type' => $item['type'],
                ];
            }
        }

        return $types;
    }
}
