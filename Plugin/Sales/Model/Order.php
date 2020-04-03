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

namespace Trive\Mstart\Plugin\Sales\Model;

use Exception;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Trive\Mstart\Helper\Data;
use Trive\Mstart\Model\Mstart;

/**
 * Class Order
 * @package Trive\Mstart\Plugin\Sales\Model
 */
class Order
{
    /**
     * @var Mstart
     */
    protected $paymentMethod;

    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var Http
     */
    protected $request;
    /**
     * @var array
     */
    private $checkOrder = [];

    /**
     * Order constructor.
     * @param Mstart $paymentMethod
     * @param Data $helperData
     * @param Http $request
     */
    public function __construct(
        Mstart $paymentMethod,
        Data $helperData,
        Http $request
    ) {
        $this->paymentMethod = $paymentMethod;
        $this->helper = $helperData;
        $this->request = $request;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @param $result
     * @return bool
     * @throws Exception
     */
    public function afterCanCancel(\Magento\Sales\Model\Order $subject, $result)
    {
        if ((string)$this->helper->getReversalValidation() === "0") {
            return $result;
        }

        if ($result) {
            //check for full action name because canCancel is called from many places
            $actionName = $this->request->getFullActionName();
            if (($actionName !== "sales_order_cancel") && ($actionName !== "sales_order_massCancel")) {
                return $result;
            }

            $order = $subject->loadByIncrementId($subject->getIncrementId());

            //Check for payment method
            if ($order->getPayment()->getMethod() !== Mstart::PAYMENT_METHOD_MSTART_CODE) {
                return $result;
            }

            if (!array_key_exists($order->getEntityId(), $this->checkOrder)) {
                $this->checkOrder[$order->getEntityId()] = true;
            } else {
                return $result;
            }

            $checkStatusArray = (array)$this->paymentMethod
                ->getResolver()
                ->checkStatus(
                    $order,
                    $this->paymentMethod->getPreparedConfigData()
                );

            $checkStatusJson = json_encode($checkStatusArray);
            if ($checkStatusArray["response_result"] !== "000") {
                $result = false;
                $this->logInvalidResponse($order, $checkStatusArray);
            } else {
                // Check if response is Authorized
                if ($checkStatusArray["response_message"] === "Authorized") {
                    $responseJson = json_encode($this->paymentMethod->getResolver()->reversalOrder($order,
                        $this->paymentMethod->getPreparedConfigData()));
                    $responseArray = json_decode($responseJson, 1);

                    //Check response code and throw exception if not success
                    if ($responseArray["response_result"] !== "400") {
                        $result = false;
                        $this->logInvalidResponse($order, $responseArray);
                    } else {
                        $this->paymentMethod->loggerMstart($responseJson);
                        $this->helper->createMstartLog("mStartReversal", $responseJson,
                            "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                        $result = true;
                    }
                } else {
                    if ($checkStatusArray["response_message"] === "Canceled") {
                        $result = true;
                    } else {
                        $message = __("Order not in status Authorized. Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                        $this->paymentMethod->loggerMstart($checkStatusJson, "mStartReversalError.log");
                        $this->helper->createMstartLog("mStartReversalError", $checkStatusJson, $message);
                        try {
                            $this->helper->sendEmail($order, $message);
                        } catch (Exception $e) {
                            $this->helper->createMstartLog("mStartReversalError", $checkStatusJson,
                                $e->getMessage() . " Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}");
                        }
                        throw new LocalizedException($message);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     * @param array $checkStatusArray
     * @throws Exception
     */
    private function logInvalidResponse(OrderInterface $order, array $checkStatusArray)
    {
        $checkStatusJson = json_encode($checkStatusArray);

        $this->paymentMethod->loggerMstart(
            $checkStatusJson,
            "mStartReversalError.log"
        );

        $this->helper->createMstartLog(
            "mStartReversalError",
            $checkStatusJson,
            "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}"
        );

        try {
            $this->helper->sendEmail($order, $checkStatusArray["response_message"]);
        } catch (Exception $e) {
            $this->helper->createMstartLog(
                "mStartReversalError",
                $e->getMessage() . " Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}"
            );
        }
    }
}
