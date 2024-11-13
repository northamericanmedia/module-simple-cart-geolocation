<?php

namespace NAM\SimpleCartGeolocation\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use NAM\SimpleCartGeolocation\Model\AdditionalConfigVars;
use PHPUnit\Framework\TestCase;

/**
 * @covers \NAM\SimpleCartGeolocation\Model\AdditionalConfigVars
 */
class AdditionalConfigVarsTest extends TestCase
{
    private $scopeConfig;
    private $urlBuilder;
    private $additionalConfigVars;

    /**
     * Method to set up dependencies for the test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->additionalConfigVars = new AdditionalConfigVars(
            $this->scopeConfig,
            $this->urlBuilder
        );
    }

    /**
     * Test for 'getConfig' method when XML_PATH_CART_USE_GEOLOCATION is not set.
     *
     * @return void
     */
    public function testGetConfigWhenNotUseGeolocation(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturn(false);

        $result = $this->additionalConfigVars->getConfig();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test for 'getConfig' method when XML_PATH_CART_USE_GEOLOCATION is set.
     *
     * @return void
     */
    public function testGetConfigWhenUseGeolocation(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturn(true);

        $this->urlBuilder->method('getUrl')
            ->with('scg/geolocator')
            ->willReturn('http://someurl/scg/geolocator');

        $result = $this->additionalConfigVars->getConfig();
        $this->assertIsArray($result);
        $this->assertEquals('http://someurl/scg/geolocator', $result[AdditionalConfigVars::GEOLOCATION_URL]);
    }
}
