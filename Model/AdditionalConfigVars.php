<?php

declare(strict_types=1);

namespace NAM\SimpleCartGeolocation\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class AdditionalConfigVars implements ConfigProviderInterface
{

    private const XML_PATH_CART_USE_GEOLOCATION = 'checkout/cart/use_geolocation';
    private const GEOLOCATION_URL = 'geolocationUrl';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $additionalVariables = [];
        if ($this->scopeConfig->isSetFlag(
            self::XML_PATH_CART_USE_GEOLOCATION,
            ScopeInterface::SCOPE_STORE
        )) {
            $additionalVariables[self::GEOLOCATION_URL] = $this->urlBuilder->getUrl('scg/geolocator');
        }
        return $additionalVariables;
    }
}
