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

namespace Trive\Mstart\Controller;

use Exception;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Trive\Mstart\Helper\Data;
use Trive\Mstart\Model\Mstart as MstartPaymentModel;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Class Mstart
 * @package Trive\Mstart\Controller
 */
abstract class Mstart extends Action implements CsrfAwareActionInterface
{
    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var Quote
     */
    protected $quote = false;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var MstartPaymentModel
     */
    protected $paymentMethod;

    /**
     * Checkout data
     *
     * @var CheckoutData
     */
    protected $checkoutData;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Mstart constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param QuoteFactory $quoteFactory
     * @param StoreManagerInterface $storeManager
     * @param PageFactory $resultPageFactory
     * @param MstartPaymentModel $paymentMethod
     * @param CheckoutData $checkoutData
     * @param OrderSender $orderSender
     * @param CartManagementInterface $quoteManagement
     * @param ManagerInterface $eventManager
     * @param CustomerSession $customerSession
     * @param Data $helperData
     * @param OrderRepository $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param array $params
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        QuoteFactory $quoteFactory,
        StoreManagerInterface $storeManager,
        PageFactory $resultPageFactory,
        MstartPaymentModel $paymentMethod,
        CheckoutData $checkoutData,
        OrderSender $orderSender,
        CartManagementInterface $quoteManagement,
        ManagerInterface $eventManager,
        CustomerSession $customerSession,
        Data $helperData,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        $params = []
    ) {
        parent::__construct($context);

        $session = $customerSession;
        if (isset($params['session']) === true && $params['session'] instanceof CustomerSession) {
            $session = $params['session'];
        }

        $this->_checkoutSession = $checkoutSession;
        $this->quoteFactory = $quoteFactory;
        $this->storeManager = $storeManager;
        $this->resultPageFactory = $resultPageFactory;
        $this->paymentMethod = $paymentMethod;
        $this->checkoutData = $checkoutData;
        $this->orderSender = $orderSender;
        $this->quoteManagement = $quoteManagement;
        $this->eventManager = $eventManager;
        $this->customerSession = $session;
        $this->helper = $helperData;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @return MstartPaymentModel
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * Get checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }

        if (!$this->quote->getCheckoutMethod()) {
            if ($this->checkoutData->isAllowedGuestCheckout($this->quote)) {
                $this->quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $this->quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }

        return $this->quote->getCheckoutMethod();
    }

    /**
     * @return mixed
     */
    public function displayResponseErrorMessage()
    {
        return $this->paymentMethod->getConfigData("response_message");
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Return checkout quote object
     *
     * @return Quote
     */
    protected function getQuote()
    {
        if (!$this->quote) {
            $this->quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->quote;
    }

    /**
     * Return checkout session object
     *
     * @return Session
     */
    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * @param $reservedOrderId
     * @throws NoSuchEntityException
     */
    protected function initQuote($reservedOrderId)
    {
        if (!$this->quote) {
            $quote = $this->loadQuoteByReservedOrderId($reservedOrderId);
            $this->quote = $quote;
        }
    }

    /**
     * @param $reservedOrderId
     *
     * @return Quote
     * @throws NoSuchEntityException
     */
    protected function loadQuoteByReservedOrderId($reservedOrderId)
    {
        $quote = $this->quoteFactory->create();
        $loadField = 'reserved_order_id';

        $quote->setStoreId($this->storeManager->getStore()->getId())->load($reservedOrderId, $loadField);
        if (!$quote->getId()) {
            throw NoSuchEntityException::singleField($loadField, $reservedOrderId);
        }

        return $quote;
    }

    /**
     * @param $code
     * @return mixed
     * @throws Exception
     */
    protected function getResponseCodeString(string $code)
    {
        $response["000"] = "Approved/Accepted";
        $response["100"] = "Your orders is declined.";
        $response["101"] = "Expired card";
        $response["104"] = "Restricted card";
        $response["109"] = "Invalid merchant";
        $response["111"] = "Card not on file";
        $response["115"] = "Requested function not supported";
        $response["121"] = "Insufficient funds";
        $response["400"] = "Reversal accepted";
        $response["909"] = "Unable to process request";
        $response["912"] = "Server not available";
        $response["930"] = "Transaction not found";
        $response["931"] = "Transaction voided/reserved";

        if (isset($response[$code]) === false) {
            throw new Exception(__("Response code is unrecognized."));
        }

        return $response[$code];
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function prepareGuestQuote()
    {
        $quote = $this->quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * @param $message
     * @param string $file
     */
    protected function loggerMstart($message, $file = 'mStart.log')
    {
        $writer = new Stream(BP . '/var/log/' . $file, 'a+');
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }

    /**
     * @param $incrementId
     * @return OrderInterface|null
     */
    protected function getOrderByIncrement($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')->create();
        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

        if (count($orderList)) {
            foreach ($orderList as $order) {
                if ($order->getIncrementId() === $incrementId) {
                    return $order;
                }
            }
        }

        return null;
    }
}
