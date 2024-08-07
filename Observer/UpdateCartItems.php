<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProductResource;
use Magento\Customer\Model\Session;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class UpdateCartItems implements ObserverInterface
{
    protected $configurableProductResource;
    protected $customerSession;
    protected $curl;
    protected $logger;
    protected $scopeConfig;
    protected $cookieManager;
    protected $cookieMetadataFactory;

    public function __construct(
        ConfigurableProductResource $configurableProductResource,
        Session $customerSession,
        Curl $curl,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->configurableProductResource = $configurableProductResource;
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    public function execute(Observer $observer)
    {
        $item = $observer->getEvent()->getItem();
        $quoteId = $item->getQuote()->getId();

        $oldQty = $item->getOrigData('qty');
        $newQty = $item->getQty();
        $qtyDiff = $newQty - $oldQty;

        if ($qtyDiff == 0) {
            return; // No change in quantity
        }

        $changeType = ($qtyDiff > 0) ? 'increase' : 'decrease';
        $product = $item->getProduct();
        
        $currentWindow = floor(time() / 5) * 5;

        // Obtain the parent product ID if this is a simple product part of a configurable product
        if ($product->getTypeId() == 'simple') {
            $parentIds = $this->configurableProductResource->getParentIdsByChild($product->getId());
            $productId = (!empty($parentIds)) ? $parentIds[0] : $product->getId();
        } else {
            $productId = $product->getId();
        }

        $actionId = uniqid($quoteId . '-' . $productId . '-' . $currentWindow . '-', true);
        
        $token = $this->cookieManager->getCookie('gopersonal_jwt');
        if (!$token) {
            $this->logger->info('No API token found in session.');
            return;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
        $url = 'https://discover.gopersonal.ai/interaction';  // Default URL

        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/interaction';  // Development URL if client ID starts with 'D-'
        }

        // Setup headers and payload for the API request
        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $this->curl->addHeader("Content-Type", "application/json");

        $postData = json_encode([
            'event' => ($changeType == 'increase') ? 'cart' : 'remove-cart',
            'item' => $productId,
            'quantity' => abs($qtyDiff),
            'transactionId' => $actionId 
        ]);

        $this->curl->post($url, $postData);
        if ($this->curl->getStatus() != 200) {
            $this->logger->error('API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
        }
    }
}
