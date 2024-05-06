<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <!-- Define preference to override the SearchInterface with your CustomSearch class -->
    <preference for="Magento\Search\Api\SearchInterface" type="Gopersonal\Magento\Model\CustomSearch"/>

    <!-- Configuration of dependencies for CustomSearch -->
    <type name="Gopersonal\Magento\Model\CustomSearch">
        <arguments>
            <argument name="defaultSearchEngine" xsi:type="object">Magento\Search\Model\SearchEngine</argument>
            <argument name="searchResultFactory" xsi:type="object">Magento\Framework\Api\Search\SearchResultFactory</argument>
        </arguments>
    </type>
</config>
