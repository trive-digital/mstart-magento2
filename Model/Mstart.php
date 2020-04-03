<?php
/**
 * @package trivedigital/mstart-magento2
 * @author Trive d.o.o.
 * @link https://trive.digital/products/mstart-ipg
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 *
 * MIT License
 *
 * Copyright (c) 2020 Trive d.o.o.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Extension is written and maintained by Trive d.o.o. (https://trive.digital/) and is serves as direct
 * integration for mStart's Internet Payment Gateway solution with Magento 2 platform
 *
 * IPG URL: https://mstart.hr/
 */

namespace Trive\Mstart\Model;

use Exception;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order\Payment;
use SimpleXMLElement;
use Trive\Mstart\Helper\Data;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Class Mstart
 * @package Trive\Mstart\Model
 */
class Mstart extends AbstractMethod
{
    const PAYMENT_METHOD_MSTART_CODE = "mstart";

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_MSTART_CODE;

    /**
     * @var string
     */
    protected $_infoBlockType = "Magento\Payment\Block\Info\Instructions";

    /**
     * @var string
     */
    protected $_formBlockType = "Trive\Mstart\Block\Form\Mstart";

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        Resolver $resolver,
        ManagerInterface $messageManager,
        Data $helperData
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );

        $this->resolver = $resolver;
        $this->messageManager = $messageManager;
        $this->helper = $helperData;
    }

    /**
     * Send authorize request to gateway
     *
     * @param DataObject|InfoInterface $payment
     * @param float $amount
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        //After redirect from gateway (in the moment of order create)
        $payment->setAdditionalInformation("payment_type", "preauth");
    }

    /**
     * Send capture request to gateway
     *
     * @param InfoInterface $payment
     * @param $amount
     * @return $this
     * @throws Exception
     */
    public function capture(InfoInterface $payment, $amount)
    {
        //In the moment on invoice create
        if ($amount <= 0) {
            throw new LocalizedException(__("Invalid amount for capture."));
        }

        $order = $payment->getOrder();

        $checkStatusArray = (array)$this->resolver->checkStatus($order, $this->prepareConfigData());
        $checkStatusJson = json_encode($checkStatusArray);
        $this->helper->createMstartLog(
            "mStartResponseDebugLogging", $checkStatusJson,
            "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}"
        );

        if ($checkStatusArray["response_result"] !== "000") {
            $this->loggerMstart($checkStatusJson, "mStartStatusError.log");
            $this->helper->createMstartLog("mStartStatusError", $checkStatusJson,
                "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
            throw new LocalizedException(__($checkStatusJson["response_message"]));
        } else {
            // Check if response is Authorized
            if ($checkStatusArray["response_message"] === "Authorized") {
                $responseJson = json_encode($this->resolver->resolveCapture($order, $this->prepareConfigData()));
                $responseArray = json_decode($responseJson, 1);

                //Check response code and throw exception if not success
                if ($responseArray["response_result"] !== "000") {
                    $this->loggerMstart($responseJson, "mStartCaptureError.log");
                    $this->helper->createMstartLog("mStartCaptureError", $responseJson,
                        "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                    throw new LocalizedException(__("Mstart Capture Error " . $responseArray["response_message"]));
                } else {
                    $this->loggerMstart($responseJson);
                    $this->helper->createMstartLog("mStartCapture", $responseJson,
                        "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                }
            } else {
                if ($checkStatusArray["response_message"] === "Completed") {
                    $this->loggerMstart($checkStatusJson, "mStartStatusCompleted.log");
                    $this->helper->createMstartLog("mStartStatusCompleted", $checkStatusJson,
                        "Order already completed Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                } else {
                    $this->loggerMstart($checkStatusJson, "mStartStatusErrorNotAuthorized.log");
                    $this->helper->createMstartLog("mStartStatusError", $checkStatusJson,
                        "Order not in status Authorized Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                    throw new LocalizedException(__("mStart Status Error " . $checkStatusJson["response_message"]));
                }
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function prepareConfigData()
    {
        $configData = [];
        $configData["customer_lang"] = $this->getConfigData("language");
        $configData["merchant_id"] = $this->getConfigData("merchant_id");
        $configData["key"] = $this->getConfigData("key");
        $configData["test_url"] = $this->getConfigData("test_url");
        $configData["test_mode"] = $this->getConfigData("test_mode");
        $configData["test_url"] = $this->getConfigData("test_url");
        $configData["production_url"] = $this->getConfigData("production_url");
        $configData["currency"] = $this->getConfigData("currency");
        $configData["trantype"] = $this->getConfigData("trantype");
        $configData["test_cert"] = $this->getConfigData("test_cert");
        $configData["production_cert"] = $this->getConfigData("production_cert");

        return $configData;
    }

    /**
     * @param $message
     * @param string $file
     */
    public function loggerMstart($message, $file = "mStartCapture.log")
    {
        $writer = new Stream(BP . "/var/log/" . $file, "a+");
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        return trim($this->getConfigData("instructions"));
    }

    /**
     * @param $quote
     * @return SimpleXMLElement|SimpleXMLElement[]|null
     * @throws Exception
     */
    public function getRedirectUrl($quote)
    {
        return $this->resolver->resolveUrl($quote, $this->prepareConfigData());
    }

    /**
     * @return array
     */
    public function getPreparedConfigData()
    {
        return $this->prepareConfigData();
    }

    /**
     * @return mixed
     */
    public function getOrderStatusSuccess()
    {
        return $this->getConfigData("order_status_on_success");
    }

    /**
     * @return mixed
     */
    public function getOrderStatusFail()
    {
        return $this->getConfigData("order_status_on_fail");
    }

    /**
     * @return Resolver
     */
    public function getResolver()
    {
        return $this->resolver;
    }
}
