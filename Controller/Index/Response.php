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

namespace Trive\Mstart\Controller\Index;

use Exception;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Trive\Mstart\Controller\Mstart;

/**
 * Class Response
 * @package Trive\Mstart\Controller\Index
 */
class Response extends Mstart
{
    /**
     * @return void|null
     * @throws Exception
     */
    public function execute()
    {
        try {
            $params = $response = $this->getRequest()->getParams();

            $this->loggerMstart(json_encode($params), "mStartResponse.log");
            $this->helper->createMstartLog("mStartResponse", json_encode($params));

            if (!array_key_exists("response_result", $params)) {
                $this->messageManager->addErrorMessage("Response from mStart not valid!");
                $this->_redirect("checkout/cart");
                return null;
            }

            if ($params["response_result"] !== "000") {
                $this->messageManager->addErrorMessage("mStart payment is not successful!");
                $this->_redirect("checkout/cart");
                return null;
            }

            $this->initQuote($params["order_number"]);

            if ($this->getCheckoutMethod() === Onepage::METHOD_GUEST) {
                $this->prepareGuestQuote();
            }

            $this->helper->createMstartLog(
                "mStartResponseQuoteBefore",
                json_encode($this->_quote->getData())
            );

            $order = $this->quoteManagement->submit($this->_quote);

            $this->helper->createMstartLog("mStartResponseQuoteAfter", json_encode($this->_quote->getData()));

            if (!$order) {
                throw new LocalizedException(__("Order cannot be placed!"));
            }

            $order = $this->handleOrderStatus($order, $params);
            $order->save();

            $this->_getCheckoutSession()->setLastQuoteId($this->_quote->getId());
            $this->_getCheckoutSession()->setLastSuccessQuoteId($this->_quote->getId());
            $this->_getCheckoutSession()->setLastOrderId($order->getId());
            $this->_getCheckoutSession()->setLastRealOrderId($order->getIncrementId());
            $this->_getCheckoutSession()->setLastOrderStatus($order->getStatus());

            switch ($order->getState()) {
                case Order::STATE_PENDING_PAYMENT:
                    // TODO
                    break;
                // regular placement, when everything is ok
                case Order::STATE_PROCESSING:
                case Order::STATE_COMPLETE:
                case Order::STATE_PAYMENT_REVIEW:
                    if (!$order->getEmailSent()) {
                        $this->orderSender->send($order);
                    }
                    $this->_checkoutSession->start();
                    break;
                default:
                    break;
            }

            $this->helper->createMstartLog(
                "mStartResponseOrder",
                json_encode($order->getData()),
                "Order Id: {$order->getEntityId()} , Increment Id: {$order->getIncrementId()}"
            );

            $this->eventManager->dispatch("checkout_submit_all_after", ["order" => $order, "quote" => $this->_quote]);
            $this->_redirect("checkout/onepage/success");
        } catch (LocalizedException $e) {
            $this->helper->createMstartLog(
                "mStartResponseErrorLocalized",
                json_encode($params),
                $e->getMessage()
            );

            try {
                $this->saveResponseData($params);
            } catch (Exception $exception) {
                $this->helper->createMstartLog(
                    "mStartResponseErrorSave",
                    "Saving response data ERROR : Order: {$params["order_number"]} " . $e->getMessage()
                );
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_redirect("checkout/cart");
            return;
        } catch (Exception $e) {
            $this->helper->createMstartLog("mStartResponseError", json_encode($params), $e->getMessage());

            //If order is saved but data from response not, try save those data
            try {
                $this->saveResponseData($params);
            } catch (Exception $exception) {
                $this->helper->createMstartLog(
                    "mStartResponseErrorSave",
                    "Saving response data ERROR : Order: {$params["order_number"]} " . $e->getMessage()
                );
            }

            $this->_objectManager->get("Psr\Log\LoggerInterface")->critical($e);
            $this->_redirect("checkout", ["_fragment" => "payment"]);
            return;
        }
    }

    /**
     * @param $params
     * @return OrderInterface|null
     */
    private function saveResponseData($params)
    {
        $order = $this->getOrderByIncrement($params["order_number"]);

        if ($order && !$order->getData("acquirer")) {
            $order = $this->handleOrderStatus($order, $params);
            $this->orderRepository->save($order);
        }

        return $order;
    }

    /**
     * @param $order
     * @param array $params
     * @return OrderInterface
     */
    private function handleOrderStatus(OrderInterface $order, array $params)
    {
        $successStatus = $this->getPaymentMethod()->getOrderStatusSuccess();
        $status = $successStatus ? $successStatus : "processing";

        if ($order->getStatus() !== $status) {
            $order->addStatusHistoryComment(__("Order successfully saved!"), $status);
        } else {
            $order->addStatusHistoryComment(__("Order successfully saved!"));
        }

        $order->setData("acquirer", $params["acquirer"]);
        $order->setData("purchase_installments", $params["purchase_installments"]);
        $order->setData("card_type", $params["card_type"]);

        return $order;
    }
}
