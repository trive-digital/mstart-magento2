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

define(
  [
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/full-screen-loader'
  ],
  function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader) {
    'use strict';

    return function (messageContainer, paymentData) {

      var serviceUrl, payload, method = 'post';
      payload = {
        cartId: quote.getQuoteId(),
        billingAddress: quote.billingAddress(),
        paymentMethod: paymentData
      };

      if (customer.isLoggedIn()) {
        serviceUrl = urlBuilder.createUrl('/carts/mine/set-payment-information', {});
      } else {
        serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/set-payment-information', {
          quoteId: quote.getQuoteId()
        });
        payload.email = quote.guestEmail;
      }

      fullScreenLoader.startLoader();

      return storage[method](
        serviceUrl, JSON.stringify(payload)
      ).fail(
        function (response) {
          errorProcessor.process(response, messageContainer);
          fullScreenLoader.stopLoader();
        }
      );
    };
  }
);
