<?php

declare(strict_types=1);

namespace NAM\SimpleCartGeolocation\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

class AdditionalConfigVars implements ConfigProviderInterface
{

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
        $additionalVariables[self::GEOLOCATION_URL] = $this->urlBuilder->getUrl('scg/geolocator');
        return $additionalVariables;
    }
}
