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

use DateTime;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use SimpleXMLElement;
use Trive\Mstart\Helper\Data;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Class Resolver
 * @package Trive\Mstart\Model
 */
class Resolver
{
    const TRAN_TYPE_PAY = "preauth";
    const TRAN_TYPE_CAPTURE = "completion";
    const TRAN_TYPE_STATUS = "preauth";
    const TRAN_TYPE_REVERSAL = "reversal";
    const SUBMIT_TYPE_PAY = "manual";
    const SUBMIT_TYPE_CAPTURE = "auto";
    const SUBMIT_TYPE_STATUS = "autocheckoutservice";
    const SUBMIT_TYPE_REVERSAL = "auto";
    const REQUEST_TYPE_PAY = "transaction";
    const REQUEST_TYPE_CAPTURE = "completion";
    const REQUEST_TYPE_STATUS = "checkstatus";
    const REQUEST_TYPE_REVERSAL = "reversal";

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        ScopeConfigInterface $scopeConfig,
        Registry $coreRegistry,
        Data $helperData
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->scopeConfig = $scopeConfig;
        $this->coreRegistry = $coreRegistry;
        $this->helper = $helperData;
    }

    /**
     * @param $quote
     * @param array $configData
     * @return SimpleXMLElement|SimpleXMLElement[]|null
     * @throws Exception
     */
    public function resolveUrl($quote, $configData = [])
    {
        $data = $this->preparePaymentData($quote, $configData);

        $this->loggerMstart($data);
        $this->helper->createMstartLog("payment", json_encode($data),
            "Quote Id: {$quote->getEntityId()} , Order Increment Id: {$quote->getReservedOrderId()}");

        return $this->makeCurlRequest($data, $configData);
    }

    /**
     * @param Quote $quote
     * @param array $configData
     * @return array
     */
    protected function preparePaymentData($quote, $configData = [])
    {
        $paymentData = [];
        $paymentData["request_type"] = $this->getRequestType("payment");
        $paymentData["trantype"] = $this->getTranType("payment");
        $paymentData["submit_type"] = $this->getSubmitType("payment");
        $paymentData["purchase_amount"] = round($quote->getGrandTotal(), 2);
        $paymentData["purchase_currency"] = $configData["currency"];
        $paymentData["purchase_description"] = $quote->getCustomerNote();
        $paymentData["customer_lang"] = $configData["customer_lang"];
        $paymentData["customer_name"] = $quote->getBillingAddress()->getFirstname();
        $paymentData["customer_surname"] = $quote->getBillingAddress()->getLastname();
        $paymentData["customer_address"] = $quote->getBillingAddress()->getStreet()["0"];
        $paymentData["customer_country"] = $this->getCountryByCode($quote->getBillingAddress()->getCountry());
        $paymentData["customer_city"] = $quote->getBillingAddress()->getCity();
        $paymentData["customer_zip"] = $quote->getBillingAddress()->getPostcode();
        $paymentData["customer_phone"] = $quote->getBillingAddress()->getTelephone();
        $paymentData["customer_email"] = $quote->getBillingAddress()->getEmail();
        $paymentData["merchant_id"] = $configData["merchant_id"];
        $paymentData["request_hash"] = $this->getHash($configData["merchant_id"], round($quote->getGrandTotal(), 2),
            $this->getOrderNumber($quote), $configData["key"]);
        $paymentData["order_number"] = $this->getOrderNumber($quote);
        $paymentData["proxy"] = "false";

        return $paymentData;
    }

    /**
     * @param string $type
     * @return string
     */
    public function getRequestType($type)
    {
        switch ($type) {
            case "payment":
                $requestType = self::REQUEST_TYPE_PAY;
                break;
            case "capture":
                $requestType = self::REQUEST_TYPE_CAPTURE;
                break;
            case "checkstatus":
                $requestType = self::REQUEST_TYPE_STATUS;
                break;
            case "reversal":
                $requestType = self::REQUEST_TYPE_REVERSAL;
                break;
            default:
                $requestType = null;
                break;
        }

        return $requestType;
    }

    /**
     * @param $type
     * @return null|string
     */
    protected function getTranType($type)
    {
        $tranType = null;
        switch ($type) {
            case "payment":
                $tranType = self::TRAN_TYPE_PAY;
                break;
            case "capture":
                $tranType = self::TRAN_TYPE_CAPTURE;
                break;
            case "checkstatus":
                $tranType = self::TRAN_TYPE_STATUS;
                break;
            case "reversal":
                $tranType = self::TRAN_TYPE_REVERSAL;
                break;
            default :
                $tranType = null;
                break;
        }

        return $tranType;
    }

    /**
     * @param $type
     * @return null|string
     */
    public function getSubmitType($type)
    {
        $submitType = null;

        switch ($type) {
            case "payment":
                $submitType = self::SUBMIT_TYPE_PAY;
                break;
            case "capture":
                $submitType = self::SUBMIT_TYPE_CAPTURE;
                break;
            case "checkstatus" :
                $submitType = self::SUBMIT_TYPE_STATUS;
                break;
            case "reversal":
                $submitType = self::SUBMIT_TYPE_REVERSAL;
                break;
            default:
                $submitType = null;
                break;
        }

        return $submitType;
    }

    public function getCountryByCode($countryCode)
    {
        switch (strtoupper($countryCode)) {
            case "SI":
                $countryName = "Slovenija";
                break;
            case "HR":
            default:
                $countryName = "Hrvatska";
                break;
        }

        return $countryName;
    }

    /**
     * @param $merchantId
     * @param $amount
     * @param $orderNumber
     * @param $secretKey
     * @return string
     */
    protected function getHash($merchantId, $amount, $orderNumber, $secretKey)
    {
        return sha1($merchantId . $amount . $orderNumber . $secretKey);
    }

    /**
     * @param Quote $quote
     * @return mixed
     */
    protected function getOrderNumber($quote)
    {
        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
            $this->quoteRepository->save($quote);
        }

        return $quote->getReservedOrderId();
    }

    /**
     * @param $message
     * @param string $file
     */
    protected function loggerMstart($message, $file = "mStartResolver.log")
    {
        $writer = new Stream(BP . "/var/log/" . $file, "a+");
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }

    /**
     * @param $payment_data
     * @param array $configData
     * @param null $url
     * @return SimpleXMLElement|null
     * @throws Exception
     */
    protected function makeCurlRequest($payment_data, $configData = [], $url = null)
    {
        if (!$url) {
            $url = $this->getConfigUrl($configData);
        }

        $payment_data = http_build_query($payment_data);

        $ch = curl_init();

        //PLEASE BE AWARE that “confirm.xhtml” should be changed to “autocheckoutservice.jsp” when //submit_type is “autocheckoutservice”
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_ENCODING, "UTF-8");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payment_data);
        curl_setopt($ch, CURLOPT_CAINFO, $this->getConfigCert($configData));
        curl_setopt($ch, CURLOPT_CAPATH, $this->getConfigCert($configData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);

        if ($curlError = curl_error($ch)) {
            $this->helper->createMstartLog("curlError", $payment_data, $curlError);
            throw new LocalizedException(__("Curl failed. " . $curlError));
        }

        curl_close($ch);

        $xml = simplexml_load_string($response);
        $responseResult = null;
        if ($xml) {
            if ($xml->payment_url) {
                $redirect = $xml->payment_url;
                $responseResult = $redirect;
            } else {
                $responseResult = $xml;
            }
        }

        return $responseResult;
    }

    /**
     * @param $configData
     * @return mixed
     */
    protected function getConfigUrl($configData)
    {
        $url = $configData["test_url"];
        if ($configData["test_mode"] === 0) {
            $url = $configData["production_url"];
        }
        return $url;
    }

    /**
     * @param $configData
     * @return mixed
     */
    protected function getConfigCert($configData)
    {
        $cert = $configData["test_cert"];
        if ($configData["test_mode"] === 0) {
            $cert = $configData["production_cert"];
        }
        return BP . "/" . $cert;
    }

    /**
     * @param OrderInterface $order
     * @param array $configData
     * @return SimpleXMLElement|SimpleXMLElement[]|null
     * @throws Exception
     */
    public function resolveCapture(OrderInterface $order, $configData = [])
    {
        $data = $this->prepareCaptureData($order, $configData);

        $this->loggerMstart($data);
        $this->helper->createMstartLog("capture", json_encode($data),
            "Capture Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");

        $url = str_replace("confirm.xhtml", "autocheckoutservice.jsp", $this->getConfigUrl($configData));
        return $this->makeCurlRequest($data, $configData, $url);
    }

    /**
     * @param OrderInterface $order
     * @param array $configData
     * @return array
     * @throws Exception
     */
    protected function prepareCaptureData(OrderInterface $order, $configData = [])
    {
        $captureData = [];
        $captureData["request_type"] = $this->getRequestType("capture");
        $captureData["trantype"] = $this->getTranType("capture");
        $captureData["submit_type"] = $this->getSubmitType("capture");
        $captureData["purchase_amount"] = round($order->getGrandTotal(), 2);
        $captureData["purchase_currency"] = $this->getCurrency($configData);
        $captureData["additional_compl_data1"] = $this->getCurrentDate();
        $captureData["order_number"] = $order->getIncrementId();
        $captureData["merchant_id"] = $configData["merchant_id"];
        $captureData["request_hash"] = $this->getHash(
            $configData["merchant_id"],
            round($order->getGrandTotal(), 2),
            $order->getIncrementId(),
            $configData["key"]
        );
        $captureData["proxy"] = "false";

        return $captureData;
    }

    /**
     * @param $configData
     * @return string
     */
    protected function getCurrency($configData)
    {
        $currencyCode = "191";
        if ($configData["currency"] === "EUR") {
            $currencyCode = "978";
        }

        return $currencyCode;
    }

    /**
     * Get current datetime
     *
     * @return string
     * @throws Exception
     */
    public function getCurrentDate()
    {
        $date = new DateTime();
        return $date->format("d/m/Y");
    }

    /**
     * @param OrderInterface $order
     * @param array $configData
     * @return SimpleXMLElement|null
     * @throws Exception
     */
    public function checkStatus(OrderInterface $order, $configData = [])
    {
        $data = $this->prepareCheckStatusData($order, $configData);

        $this->loggerMstart($data);
        $this->helper->createMstartLog("checkStatus", json_encode($data),
            "Provjeri status narudžbe. Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");

        $url = str_replace("confirm.xhtml", "autocheckoutservice.jsp", $this->getConfigUrl($configData));
        
        return $this->makeCurlRequest($data, $configData, $url);
    }

    /**
     * @param Order $order
     * @param array $configData
     * @return array
     */
    protected function prepareCheckStatusData($order, $configData = [])
    {
        $checkStatusData = [];
        $checkStatusData["request_type"] = $this->getRequestType("checkstatus");
        $checkStatusData["trantype"] = $this->getTranType("checkstatus");
        $checkStatusData["submit_type"] = $this->getSubmitType("checkstatus");
        $checkStatusData["order_number"] = $order->getIncrementId();
        $checkStatusData["purchase_amount"] = round($order->getGrandTotal(), 2);
        $checkStatusData["customer_lang"] = $configData["customer_lang"];
        $checkStatusData["merchant_id"] = $configData["merchant_id"];
        $checkStatusData["request_hash"] = $this->getHash(
            $configData["merchant_id"],
            round($order->getGrandTotal(), 2),
            $order->getIncrementId(),
            $configData["key"]
        );
        $checkStatusData["proxy"] = "false";

        return $checkStatusData;
    }

    /**
     * @param OrderInterface $order
     * @param array $configData
     * @return SimpleXMLElement|null
     * @throws Exception
     */
    public function reversalOrder(OrderInterface $order, $configData = [])
    {
        $data = $this->prepareReversalData($order, $configData);

        $this->loggerMstart($data);
        $this->helper->createMstartLog(
            "reversalOrder",
            json_encode($data),
            "Otkazivanje predautoriziranih narudžbi. Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}"
        );

        $url = str_replace("confirm.xhtml", "autocheckoutservice.jsp", $this->getConfigUrl($configData));
        return $this->makeCurlRequest($data, $configData, $url);
    }

    /**
     * @param OrderInterface $order
     * @param array $configData
     * @return array
     */
    protected function prepareReversalData(OrderInterface $order, $configData = [])
    {
        $reversalData = [];
        $reversalData["request_type"] = $this->getRequestType("reversal");
        $reversalData["trantype"] = $this->getTranType("reversal");
        $reversalData["submit_type"] = $this->getSubmitType("reversal");
        $reversalData["purchase_amount"] = round($order->getGrandTotal(), 2);
        $reversalData["purchase_currency"] = $this->getCurrency($configData);
        $reversalData["order_number"] = $order->getIncrementId();
        $reversalData["merchant_id"] = $configData["merchant_id"];
        $reversalData["request_hash"] = $this->getHash(
            $configData["merchant_id"],
            round($order->getGrandTotal(), 2),
            $order->getIncrementId(),
            $configData["key"]
        );
        $reversalData["proxy"] = "false";

        return $reversalData;
    }
}
