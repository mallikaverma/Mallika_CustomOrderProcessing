<?php
namespace Mallika\CustomOrderProcessing\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Webapi\Request;
use Magento\Framework\Webapi\Response;

class OrderStatusApiTest extends \PHPUnit\Framework\TestCase
{
    private $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = Bootstrap::createHttpClient();
    }

    public function testOrderStatusUpdateApi()
    {
        $incrementId = 100000066;
        $status = 'shipped';
        $token = 'test_bearer_token';

        $request = $this->httpClient->createRequest(Request::METHOD_POST, '/rest/V1/orders/status')
            ->setHeader('Authorization', 'Bearer ' . $token)
            ->setBody(json_encode(['orderId' => $incrementId, 'status' => $status]));

        $response = $this->httpClient->sendRequest($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getBody(), true));
    }
}