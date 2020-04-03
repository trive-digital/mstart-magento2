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

use Trive\Mstart\Controller\Mstart;

/**
 * Class Cancel
 * @package Trive\Mstart\Controller\Index
 */
class Cancel extends Mstart
{
    public function execute()
    {
        try {
            $params = $response = $this->getRequest()->getParams();

            $this->loggerMstart(json_encode($params), "mStartCancel.log");
            $this->helper->createMstartLog("mStartCancel", json_encode($params));

            $message = "";
            if ($params["response_result"]) {
                $message = $this->getResponseCodeString((string) $params["response_result"]);
            }

            if ($params["order_number"] && $message) {
                $responseMessage = "";
                if ($this->displayResponseErrorMessage()) {
                    $responseMessage = $params["response_message"];
                }
                $this->messageManager->addErrorMessage(__($message) . " " . __($responseMessage));
            }

            $path = $this->_url->getUrl("checkout/cart/", ["_secure" => true]);
            $this->_redirect($path);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__("Something went wrong in the payment gateway."));
            $path = $this->_url->getUrl("checkout/cart/", ["_secure" => true]);
            $this->_redirect($path);
        }
    }
}
