<?php

declare(strict_types=1);

namespace NAM\SimpleCartGeolocation\Controller\Geolocator;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RemoteServiceUnavailableException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Index extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{

    private const XML_PATH_API_KEY = 'cataloginventory/source_selection_distance_based_google/api_key';
    private const LATITUDE = 'latitude';
    private const LONGITUDE = 'longitude';

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonFactory $resultJsonFactory
     * @param Curl $curl
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        Context $context,
        private readonly LoggerInterface $logger,
        private readonly Json $json,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Curl $curl,
        private readonly RegionFactory $regionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        //return null;
        return true;
    }

    /**
     * Instantiate model and pass Geolocation request to it
     *
     * @return ResultInterface
     * @throws LocalizedException
     */
    public function execute(): ResultInterface
    {
        try {
            $response = [];
            $result = $this->resultJsonFactory->create();
            $request = $this->getRequest();
            $latitude = $request->getParam(self::LATITUDE);
            $longitude = $request->getParam(self::LONGITUDE);
            $apiKey = $this->scopeConfig->getValue(
                self::XML_PATH_API_KEY,
                ScopeInterface::SCOPE_STORE
            );
            $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$apiKey}"; // phpcs:ignore Generic.Files.LineLength
            $this->curl->get($url);
            if ($this->curl->getStatus() === 200) {
                $responseBody = $this->json->unserialize($this->curl->getBody());
                if ($responseBody['status'] === 'OK') {
                    if (isset($responseBody['results'][0]['address_components'])) {
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
            }
            return $result->setData($response);
        } catch (RemoteServiceUnavailableException $e) {
            $this->logger->critical($e);
            $this->getResponse()->setStatusHeader(503, '1.1', 'Service Unavailable')->sendResponse();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }
}
