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
    'Magento_Checkout/js/view/payment/default',
    'Trive_Mstart/js/action/set-payment-method',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/checkout-data',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/view/billing-address',
    'Magento_Checkout/js/action/set-billing-address',
    'mage/url'
  ],
  function (
    $,
    Component,
    setPaymentMethodAction,
    placeOrderAction,
    selectPaymentMethodAction,
    customer,
    checkoutData,
    customerData,
    additionalValidators,
    billingAddress,
    setBillingAddressAction,
    url
  ) {
    'use strict';

    return Component.extend({
      defaults: {
        template: 'Trive_Mstart/payment/mstart'
      },

      placeOrder: function (data, event) {
        if (event) {
          event.preventDefault();
        }
        var self = this,
          placeOrder,
          emailValidationResult = customer.isLoggedIn(),
          loginFormSelector = 'form[data-role=email-with-possible-login]';

        if (!customer.isLoggedIn()) {
          $(loginFormSelector).validation();
          emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
        }

        if (emailValidationResult && this.validate() && additionalValidators.validate()) {
          this.isPlaceOrderActionAllowed(false);
          placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

          $.when(placeOrder).fail(function () {
            self.isPlaceOrderActionAllowed(true);
          }).done(this.afterPlaceOrder.bind(this));
          return true;
        }
        return false;
      },

      selectPaymentMethod: function () {
        selectPaymentMethodAction(this.getData());
        checkoutData.setSelectedPaymentMethod(this.item.method);
        return true;
      },

      afterPlaceOrder: function () {
        window.location.replace(url.build('icheck/index/redirect/'));
      },

      /** Returns send check to info */
      getMailingAddress: function () {
        return window.checkoutConfig.payment.checkmo.mailingAddress;
      },

      getInstructions: function () {
        return window.checkoutConfig.payment.instructions[this.item.method];
      },

      continueMstart: function (data, event) {
        if (event) {
          event.preventDefault();
        }

        var emailValidationResult = customer.isLoggedIn(),
          loginFormSelector = 'form[data-role=email-with-possible-login]',
          sameAsShipping = '#billing-address-same-as-shipping-mstart',
          updateButton = '.payment-method.payment-mstart._active  button.action-update',
          mageError = '.payment-method.payment-mstart._active  .mage-error',
          messageError = '.message.message-error.error'
        ;

        if (!customer.isLoggedIn()) {
          $(loginFormSelector).validation();
          emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
        }

        var noErrors = true;

        //Check for payment address data only if not checked option "Same As Shipping"
        if (!$(sameAsShipping).is(':checked')) {
          $(updateButton).click();
          if ($(mageError).length) {
            noErrors = false;
          }
        }

        if (emailValidationResult && this.validate() && additionalValidators.validate() && noErrors) {
          //update payment method information if additional data was changed
          this.selectPaymentMethod();
          //setBillingAddressAction(this.messageContainer);
          setPaymentMethodAction(this.messageContainer, this.getData()).done(
            function () {
              customerData.invalidate(['cart']);

              if ($(messageError).length) {
                return false;
              } else {
                $.mage.redirect(
                  url.build('icheck/index/redirect/')
                );
              }

            }
          );
          return false;
        }
      }
    });
  }
);
