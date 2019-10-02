<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Customer\Model\Address;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Model\Config as EavConfig;

/**
 * Provides customer address data.
 */
class CustomAttributesProcessor
{
    /**
     * @var AddressMetadataInterface
     */
    private $addressMetadata;

    /**
     * @var AttributeOptionManagementInterface
     */
    private $attributeOptionManager;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * CustomAttributesProcessor constructor.
     * @param AddressMetadataInterface $addressMetadata
     * @param AttributeOptionManagementInterface $attributeOptionManager
     * @param EavConfig $eavConfig
     */
    public function __construct(
        AddressMetadataInterface $addressMetadata,
        AttributeOptionManagementInterface $attributeOptionManager,
        EavConfig $eavConfig
    ) {
        $this->addressMetadata = $addressMetadata;
        $this->attributeOptionManager = $attributeOptionManager;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Set Labels to custom Attributes
     *
     * @param \Magento\Framework\Api\AttributeValue[] $customAttributes
     * @return array $customAttributes
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function setLabelsForAttributes(array $customAttributes): array
    {
        if (!empty($customAttributes)) {
            foreach ($customAttributes as $customAttributeCode => $customAttribute) {
                $attributeOptionLabels = $this->getAttributeLabels($customAttribute, $customAttributeCode);
                if (!empty($attributeOptionLabels)) {
                    $customAttributes[$customAttributeCode]['label'] = implode(', ', $attributeOptionLabels);
                }
            }
        }

        return $customAttributes;
    }
    /**
     * Get Labels by CustomAttribute and CustomAttributeCode
     *
     * @param array $customAttribute
     * @param string $customAttributeCode
     * @return array $attributeOptionLabels
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function getAttributeLabels(array $customAttribute, string $customAttributeCode) : array
    {
        $attributeOptionLabels = [];

        if (!empty($customAttribute['value'])) {
            $customAttributeValues = explode(',', $customAttribute['value']);
            $attributeOptions = $this->attributeOptionManager->getItems(
                \Magento\Customer\Model\Indexer\Address\AttributeProvider::ENTITY,
                $customAttributeCode
            );

            if (!empty($attributeOptions)) {
                foreach ($attributeOptions as $attributeOption) {
                    $attributeOptionValue = $attributeOption->getValue();
                    if (\in_array($attributeOptionValue, $customAttributeValues, false)) {
                        $attributeOptionLabels[] = $attributeOption->getLabel() ?? $attributeOptionValue;
                    }
                }
            }
        }

        return $attributeOptionLabels;
    }

    /**
     * Filter not visible on storefront custom attributes.
     *
     * @param array $attributes
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function filterNotVisibleAttributes(array $attributes): array
    {
        $attributesMetadata = $this->addressMetadata->getAllAttributesMetadata();
        foreach ($attributesMetadata as $attributeMetadata) {
            if (!$attributeMetadata->isVisible()) {
                unset($attributes[$attributeMetadata->getAttributeCode()]);
                continue;
            }

            $attribute = $this->eavConfig->getAttribute(
                'customer_address',
                $attributeMetadata->getAttributeCode()
            );

            $attributeForms = $attribute->getUsedInForms();

            if ($attributeForms) {
                $isAllowedOnFront = array_intersect(
                    ['customer_register_address', 'customer_address_edit'],
                    $attributeForms
                );

                if (!$isAllowedOnFront) {
                    unset($attributes[$attributeMetadata->getAttributeCode()]);
                }
            }
        }

        return $this->setLabelsForAttributes($attributes);
    }
}
