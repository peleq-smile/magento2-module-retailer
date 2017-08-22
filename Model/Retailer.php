<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\Retailer
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\Retailer\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Smile\Retailer\Api\Data\RetailerInterface;
use Smile\Seller\Model\Seller;

/**
 * Retailer Model class
 *
 * @category Smile
 * @package  Smile\Retailer
 * @author   Aurelien FOUCRET <aurelien.foucret@smile.fr>
 */
class Retailer extends Seller implements RetailerInterface
{
    /**
     * {@inheritDoc}
     */
    public function setExtensionAttributes(\Smile\Retailer\Api\Data\RetailerExtensionInterface $extensionAttributes)
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Retrieve AttributeSetName
     *
     * @return string
     */
    public function getAttributeSetName()
    {
        return ucfirst(self::ATTRIBUTE_SET_RETAILER);
    }

    /**
     * {@inheritDoc}
     */
    public function beforeSave()
    {
        // Validation
        foreach (['seller_code', 'name'] as $requiredData) {
            if (null === $this->getData($requiredData) || empty($this->getData($requiredData))) {
                throw new CouldNotSaveException(__("Missing $requiredData data."));
            }
        }

        $extensionAttributes = $this->getExtensionAttributes();
        if (null !== $extensionAttributes && null === $extensionAttributes->getAddress()) {
            throw new CouldNotSaveException(__('Missing address data.'));
        }

        return parent::beforeSave();
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionAttributes()
    {
        $extensionAttributes = $this->_getExtensionAttributes();
        if (!$extensionAttributes) {
            return $this->extensionAttributesFactory->create('Smile\Retailer\Api\Data\RetailerInterface');
        }

        return $extensionAttributes;
    }
}
