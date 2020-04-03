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
use Magento\Framework\Exception\LocalizedException;
use Trive\Mstart\Controller\Mstart;

/**
 * Class Redirect
 * @package Trive\Mstart\Controller\Index
 */
class Redirect extends Mstart
{
    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     * @throws Exception
     */
    public function execute()
    {
        try {
            $quote = $this->getQuote();

            if (!$quote->hasItems() || $quote->getHasError()) {
                throw new LocalizedException(__("We can't initialize Mstart."));
            }

            $this->loggerMstart(json_encode($quote->getData()), "mStartRedirect.log");
            $this->helper->createMstartLog(
                "mStartRedirect",
                json_encode($quote->getData()),
                "Quote Id: " . $quote->getEntityId() . " Order Increment Id: " . $quote->getReservedOrderId()
            );

            $redirectUrl = $this->getPaymentMethod()->getRedirectUrl($quote);
            $this->loggerMstart(json_encode($quote->getData()), "mStartParams.log");

            /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($redirectUrl);
        } catch (Exception $e) {
            $this->loggerMstart($e->getMessage(), "mStartRedirectError.log");
            $this->helper->createMstartLog("mStartRedirectError", null, $e->getMessage());
            $this->messageManager->addErrorMessage(__("Something went wrong in the payment gateway."));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($this->_url->getUrl("checkout/cart"));
        }

        return $resultRedirect;
    }
}
