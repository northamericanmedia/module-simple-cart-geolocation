<?php

declare(strict_types=1);

namespace NAM\SimpleCartGeolocation\Controller\Geolocator;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Index extends Action implements HttpPostActionInterface
{


    private const CACHE_KEY = 'address_data_by_latitude_longitude_';
    private const CACHE_TAG = 'GEOLOCATION_RESULTS';
    private const GOOGLE_MAPS_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
    public const LATITUDE = 'latitude';
    public const LONGITUDE = 'longitude';
    private const XML_PATH_API_KEY = 'cataloginventory/source_selection_distance_based_google/api_key';

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     * @param Curl $curl
     * @param RegionFactory $regionFactory
     * @param CacheInterface $cacheManager
     * @param Validator $formKeyValidator
     */
    public function __construct(
        Context $context,
        private readonly LoggerInterface $logger,
        private readonly Json $json,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Curl $curl,
        private readonly RegionFactory $regionFactory,
        private readonly CacheInterface $cacheManager,
        private readonly Validator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    /**
     * Instantiate model and pass Geolocation request to it
     *
     * @return ResultInterface
     * @throws LocalizedException
     */
    public function execute(): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $geolocationResults = [];

        try {
            $request = $this->getRequest();
            $latitude = $request->getParam(self::LATITUDE);
            $longitude = $request->getParam(self::LONGITUDE);
            $apiKey = $this->scopeConfig->getValue(
                self::XML_PATH_API_KEY,
                ScopeInterface::SCOPE_STORE
            );
            if (!$apiKey) {
                return $result->setData($geolocationResults);
            }
            $cacheKey = self::CACHE_KEY . sha1($this->json->serialize([
                self::LATITUDE => $latitude,
                self::LONGITUDE => $longitude
            ]));
            if ($geolocationResults = $this->cacheManager->load($cacheKey)) {
                $geolocationResults = $this->json->unserialize($geolocationResults);
            } else {
                $geolocationResults = $this->getGeolocationResults($latitude, $longitude, $apiKey);
                if (!empty($geolocationResults)) {
                    $this->cacheManager->save(
                        $this->json->serialize($geolocationResults),
                        $cacheKey,
                        [self::CACHE_TAG]
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
        return $result->setData($geolocationResults);
    }

    /**
     * Get geolocation results
     *
     * @param string $latitude
     * @param string $longitude
     * @param string $apiKey
     * @return array
     */
    private function getGeolocationResults(
        string $latitude,
        string $longitude,
        string $apiKey
    ): array {
        $response = [];
        $url = self::GOOGLE_MAPS_URL . '?latlng=' . $latitude . ',' . $longitude . '&key=' . $apiKey;
        $this->curl->get($url);
        if ($this->curl->getStatus() === 200) {
            $responseBody = $this->json->unserialize($this->curl->getBody());
            if ($responseBody['status'] === 'OK' &&
                isset($responseBody['results'][0]['address_components'])
            ) {
                foreach ($responseBody['results'][0]['address_components'] as $addressComponent) {
                    if ($addressComponent['types'][0] === 'administrative_area_level_1') {
                        $response['region_code'] = $addressComponent['short_name'];
                        $response['region'] = $addressComponent['long_name'];
                    }
                    if ($addressComponent['types'][0] === 'country') {
                        $response['country_id'] = $addressComponent['short_name'];
                    }
                    if ($addressComponent['types'][0] === 'postal_code') {
                        $response['postcode'] = $addressComponent['short_name'];
                    }
                }
                if ($response['region'] && $response['country_id']) {
                    $region = $this->regionFactory->create();
                    $region->loadByCode($response['region_code'], $response['country_id']);
                    $response['region_id'] = $region->getRegionId();
                }
            }
        }
        return $response;
    }
}
