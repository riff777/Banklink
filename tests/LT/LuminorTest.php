<?php

namespace RKD\Banklink\Test\LT;

use RKD\Banklink;
use UnexpectedValueException;
use PHPUnit\Framework\TestCase;
use RKD\Banklink\Request\PaymentRequest;
use RKD\Banklink\Response\PaymentResponse;
use RKD\Banklink\Protocol\Helper\ProtocolHelper;

/**
 * Test suite for Luminor LT banklink.
 *
 * @author  Rene Korss <rene.korss@gmail.com>
 */
class LuminorTest extends TestCase
{
    protected $bankClass = "RKD\Banklink\LT\Luminor";
    protected $protocolClass = "RKD\Banklink\Protocol\IPizza2015";

    protected $requestUrl = 'https://ib.dnb.lt/loginb2b.aspx';
    protected $testRequestUrl = 'https://ib.dnb.lt/loginb2b.aspx';

    protected $bank;

    private $protocol;

    private $sellerId;
    private $sellerName;
    private $sellerAccount;

    private $senderName;

    private $orderId;
    private $amount;
    private $message;
    private $language;
    private $currency;
    private $timezone;
    private $datetime;
    private $expectedData;

    private $customRequestUrl;

    /**
     * Set test data.
     */
    public function setUp() : void
    {
        $this->sellerId         = 'id2000';
        $this->sellerName       = 'Ülo Pääsuke';
        $this->sellerAccount    = '1010342342354345435';

        $this->senderName       = 'Toomas Jäär';

        $this->orderId          = 100;
        $this->amount           = 10.00;
        $this->message          = 'First payment';
        $this->language         = 'ENG';
        $this->currency         = 'EUR';
        $this->timezone         = 'Europe/Tallinn';

        // From ENV variable
        $this->datetime         = getenv('TEST_DATETIME');

        $this->customRequestUrl = 'http://example.com';

        $this->expectedData = [
            'VK_SERVICE'  => '2001',
            'VK_VERSION'  => '008',
            'VK_SND_ID'   => $this->sellerId,
            'VK_STAMP'    => $this->orderId,
            'VK_AMOUNT'   => $this->amount,
            'VK_CURR'     => $this->currency,
            'VK_REF'      => ProtocolHelper::calculateReference($this->orderId),
            'VK_MSG'      => $this->message,
            'VK_RETURN'   => $this->customRequestUrl,
            'VK_LANG'     => $this->language,
            'VK_ACC'      => $this->sellerAccount,
            'VK_NAME'     => $this->sellerName,
            'VK_PANK'     => '40100',
            'VK_MAC'      => 'c8mA/p30t1yBGpVTJP9mCBx2TsRNO9/1sEGY1cQSkYQMmwCBnJq6BqdLt11eetnVDX4PezH799DuruoRPgQ7BZh7JIeaLmpj19qFvntWAPcuzNsxU7Neuy8xrDOkRd97UsmU+xhyc9Hq/MNPP/KvXXNh4xnfeeVzOxcNvWtIgTo=',
        ];

        // Set up banklink
        $this->setUpBanklinkWithSeller();
    }

    /**
     * Set up banklink object with seller data
     */

    protected function setUpBanklinkWithSeller()
    {
        $this->protocol = new $this->protocolClass(
            $this->sellerId,
            __DIR__.'/../keys/IPizza/private_key.pem',
            '',
            __DIR__.'/../keys/IPizza/public_key.pem',
            $this->customRequestUrl,
            $this->sellerName,
            $this->sellerAccount
        );

        // Use mb_strlen
        $this->protocol->useMbStrlen(true);
        $this->bank = new $this->bankClass($this->protocol);
    }

    /**
     * Test for correctly generated request data for service 1012.
     */
    public function testGetPaymentRequestService1012()
    {
        // Test service 1012
        $request = $this->bank->getPaymentRequest($this->orderId, $this->amount, $this->message, $this->language, $this->currency, [], $this->timezone);

        // Instance of PaymentRequest and data is same
        $this->assertInstanceOf('RKD\Banklink\Request\PaymentRequest', $request);
        $this->assertEquals($this->expectedData, $request->getRequestData());

        // Production env url
        $this->assertEquals($this->requestUrl, $request->getRequestUrl());
    }

    /**
     * Test for correctly generated request data for service 2011
     * Test debug url.
     */
    public function testGetPaymentRequestService2011()
    {
        $this->setUpBanklinkWithSeller();

        // Debug mode
        $this->bank->debugMode();

        // New expected values
        $this->expectedData['VK_SERVICE']  = '2001';
        $this->expectedData['VK_ACC']      = $this->sellerAccount;
        $this->expectedData['VK_NAME']     = $this->sellerName;
        $this->expectedData['VK_MAC']      = 'c8mA/p30t1yBGpVTJP9mCBx2TsRNO9/1sEGY1cQSkYQMmwCBnJq6BqdLt11eetnVDX4PezH799DuruoRPgQ7BZh7JIeaLmpj19qFvntWAPcuzNsxU7Neuy8xrDOkRd97UsmU+xhyc9Hq/MNPP/KvXXNh4xnfeeVzOxcNvWtIgTo=';

        $request = $this->bank->getPaymentRequest($this->orderId, $this->amount, $this->message, $this->language, $this->currency, [], $this->timezone);

        // Instance of PaymentRequest and data is same
        $this->assertInstanceOf('RKD\Banklink\Request\PaymentRequest', $request);
        $this->assertEquals($this->expectedData, $request->getRequestData());

        // Test env url
        $this->assertEquals($this->testRequestUrl, $request->getRequestUrl());

        // Get HTML
        $this->assertStringContainsStringIgnoringCase('<input type="hidden"', $request->getRequestInputs());
    }

    /**
     * Test successful payment response.
     */
    public function testHandlePaymentResponseSuccess()
    {
        $responseData = [
            'VK_SERVICE'    => '1101',
            'VK_VERSION'    => '008',
            'VK_SND_ID'     => $this->senderName,
            'VK_REC_ID'     => $this->sellerId,
            'VK_STAMP'      => $this->orderId,
            'VK_T_NO'       => 100,
            'VK_AMOUNT'     => $this->amount,
            'VK_CURR'       => $this->currency,
            'VK_REC_ACC'    => $this->sellerAccount,
            'VK_REC_NAME'   => $this->sellerName,
            'VK_SND_ACC'    => '101032423434543',
            'VK_SND_NAME'   => 'Mart Mets',
            'VK_REF'        => $this->orderId,
            'VK_MSG'        => $this->message,
            'VK_MAC'        => 'nlIyuBx4d3jOZd3pnZz7kvxP0p+HqnP2n8Tzbm5SDlPItWdFlLYRuHrMwhd0DpzfBeXay0DvMeLjCN1ZOeWqXxEe6COaXILm4k/G4NW2MrieznH8bOmJAJjaKGQiGxJv46zNzJpxbzzr0kcG2WGJQvY8JsZZWOUbPNsVBcIwBHg=',
            'VK_T_DATE'     => $this->datetime,
            'VK_AUTO'       => 'Y'
        ];

        $response = $this->bank->handleResponse($responseData);

        $this->assertInstanceOf('RKD\Banklink\Response\PaymentResponse', $response);
        $this->assertEquals(PaymentResponse::STATUS_SUCCESS, $response->getStatus());

        $this->assertEquals($this->sellerName, $response->getReceiver()->name);
        $this->assertEquals($this->sellerAccount, $response->getReceiver()->account);

        $this->assertEquals(getenv('TEST_DATETIME'), $response->getTransactionDate());

        // This is valid response
        $this->assertTrue($response->wasSuccessful());

        // Response was sent automatically by bank
        $this->assertTrue($response->isAutomatic());
    }

    /**
     * Test failed payment response.
     */
    public function testHandlePaymentResponseError()
    {
        $responseData = [
            'VK_SERVICE'  => '1901',
            'VK_VERSION'  => '008',
            'VK_SND_ID'   => $this->senderName,
            'VK_REC_ID'   => $this->sellerId,
            'VK_STAMP'    => $this->orderId,
            'VK_REF'      => $this->orderId,
            'VK_MSG'      => $this->message,
            'VK_MAC'      => 'o4rju0oEwITuIheUdtDjp2njKhBzvQv8RjKg+rdCB+fwGiUS8zpXzr0I+wj0vl13h+ACGAR1LO9gR2+IG1yq+AJdQdVszJIbeA1jcg1GFtl1xyLN8LXYfubHHUB/7EWwiEGZKcHrNp3pAsADlLwySQLRWatheMLPqRRk2FX96Ko=',
            'VK_DATE'     => $this->datetime,
            'VK_AUTO'     => 'N'
        ];

        $response = $this->bank->handleResponse($responseData);

        $this->assertInstanceOf('RKD\Banklink\Response\PaymentResponse', $response);
        $this->assertEquals(PaymentResponse::STATUS_ERROR, $response->getStatus());

        // This is not valid response, so validation should fail
        $this->assertFalse($response->wasSuccessful());

        // User pressed "Back to merchant" button
        $this->assertFalse($response->isAutomatic());
    }

    /**
     * Test custom request url.
     */
    public function testCustomRequestUrl()
    {
        $this->bank = new $this->bankClass($this->protocol);
        $this->bank->setRequestUrl('https://google.com');

        $request = $this->bank->getPaymentRequest($this->orderId, $this->amount, $this->message, $this->language, $this->currency, [], $this->timezone);

        // Get same data again, already exists
        $request = $this->bank->getPaymentRequest($this->orderId, $this->amount, $this->message, $this->language, $this->currency, [], $this->timezone);

        // Custom url
        $this->assertEquals('https://google.com', $request->getRequestUrl());
    }

    /**
     * Test custom request data
     */
    public function testCustomRequestData()
    {
        $this->bank = new $this->bankClass($this->protocol);

        $customData = [
            'INAPP' => 1, // new data
            'VK_REF' => 'mycustomref', // override data
            'VK_MAC' => 'Z9AtMa/gJygMoKbxkeoegyiKEoNqn2CKW1s3n9k6/r+0Ge488BCESCKznET9GPrGBsQUJU6WoezgRyOiYUrzTkT9zZ+o/Pq+IPDaCSZn1v3HRrLqx8oEvbCNNAzEHr7UfvXdUWeRTy9+SDqka+8UR3LvidU1IDpwcHuQuF3jVo8='
        ];

        $this->expectedData = array_merge($this->expectedData, $customData);

        $request = $this->bank->getPaymentRequest($this->orderId, $this->amount, $this->message, $this->language, $this->currency, $customData, $this->timezone);

        // Custom url
        $this->assertEquals($this->expectedData, $request->getRequestData());
    }

    /**
     * Test we can set multiple request urls
     */
    public function testMultipleRequestUrls()
    {
        $this->bank = new $this->bankClass($this->protocol);
        $this->bank->setRequestUrl([
            'auth' => 'https://custom.com/auth',
            'payment' => 'https://custom.com/pay'
        ]);

        // Custom url set
        $this->assertEquals('https://custom.com/auth', $this->bank->getRequestUrlFor('auth'));
        $this->assertEquals('https://custom.com/pay', $this->bank->getRequestUrlFor('payment'));
    }

    /**
     * Test we can set one request url which is used for payment and authentication
     */
    public function testSingleRequestUrl()
    {
        $this->bank = new $this->bankClass($this->protocol);
        $this->bank->setRequestUrl('https://custom.com/endpoint');

        // Custom url set
        $this->assertEquals('https://custom.com/endpoint', $this->bank->getRequestUrlFor('auth'));
        $this->assertEquals('https://custom.com/endpoint', $this->bank->getRequestUrlFor('payment'));
    }

    /**
     * getRequestUrlFor should throw an UnexpectedValueException if in wrong type
     */
    public function testRequestUrlWrongType()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->bank = new $this->bankClass($this->protocol);
        $this->bank->setRequestUrl(null);

        $this->assertEquals('https://custom.com/endpoint', $this->bank->getRequestUrlFor('payment'));
    }

    /**
     * getRequestUrlFor should throw an UnexpectedValueException if don't have correct type
     */
    public function testNoRequestUrlType()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->bank = new $this->bankClass($this->protocol);
        $this->bank->setRequestUrl([]);

        $this->assertEquals('https://custom.com/endpoint', $this->bank->getRequestUrlFor('payment'));
    }
}
