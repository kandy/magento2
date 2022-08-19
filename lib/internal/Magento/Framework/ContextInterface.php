<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework;

/**
 * @api
 * @since
 */
interface ContextInterface
{
    /**
     * Configure object manager
     *
     * @param array $configuration
     * @return ObjectManagerInterface
     */
    public function withContext(array $configuration): ObjectManagerInterface;
}
