<?php

class Bubble_Api_Model_Catalog_Product_Api extends Mage_Catalog_Model_Product_Api
{
    public function create($type, $set, $sku, $productData, $store = null)
    {
        // Allow attribute set name instead of id
        if (is_string($set) && !is_numeric($set)) {
            $set = Mage::helper('bubble_api')->getAttributeSetIdByName($set);
        }

        $ret = parent::create($type, $set, $sku, $productData, $store);

        //check if all simples are associated
        if($type == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $newProduct = Mage::getModel('catalog/product')->load($ret);

            if(count($productData['associated_skus']) != count($newProduct->getTypeInstance()->getUsedProductIds()))
            {
                $error = Mage::helper('bubble_api/catalog_product')->__('Not all products associated! Associated products: %s',
                    $newProduct->getConfigurableProductsData());
                $this->_fault('data_invalid', $error);
            }
        }

        return $ret;
    }

    protected function _prepareDataForSave($product, $productData)
    {
        /* @var $product Mage_Catalog_Model_Product */

        if (isset($productData['categories'])) {
            $categoryIds = Mage::helper('bubble_api/catalog_product')
                ->getCategoryIdsByNames((array) $productData['categories']);
            if (!empty($categoryIds)) {
                $productData['categories'] = array_unique($categoryIds);
            }
        }

        if (isset($productData['website_ids'])) {
            $websiteIds = $productData['website_ids'];
            foreach ($websiteIds as $i => $websiteId) {
                if (!is_numeric($websiteId)) {
                    $website = Mage::app()->getWebsite($websiteId);
                    if ($website->getId()) {
                        $websiteIds[$i] = $website->getId();
                    }
                }
            }
            $product->setWebsiteIds($websiteIds);
            unset($productData['website_ids']);
        }

        foreach ($productData as $code => $value) {
            $productData[$code] = Mage::helper('bubble_api/catalog_product')
                ->getOptionKeyByLabel($code, $value);
        }

        parent::_prepareDataForSave($product, $productData);

        if (isset($productData['associated_skus'])) {
            $simpleSkus = $productData['associated_skus'];
            $priceChanges = isset($productData['price_changes']) ? $productData['price_changes'] : array();
            $configurableAttributes = isset($productData['configurable_attributes']) ? $productData['configurable_attributes'] : array();
            Mage::helper('bubble_api/catalog_product')->associateProducts($product, $simpleSkus, $priceChanges, $configurableAttributes);
        }
        if (isset($productData['images'])) {
            $images = $productData['images'];
            Mage::helper('bubble_api/catalog_product')->addImages($product, $images);
        }
    }
}