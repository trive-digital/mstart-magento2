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

namespace Trive\Mstart\Helper;

use DateTime;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Trive\Mstart\Model\MstartLogFactory;

/**
 * Class Data
 * @package Trive\Mstart\Helper
 */
class Data extends AbstractHelper
{
    const XML_PATH_EMAIL_SEND = 'payment/mstart/reversal_email_send';
    const XML_PATH_EMAIL_TEMPLATE_FIELD = 'payment/mstart/reversal_email_template';
    const XML_PATH_EMAIL_ADDRESS = 'payment/mstart/reversal_email';
    const XML_PATH_EMAIL_ADDRESS_NAME = 'payment/mstart/reversal_email_name';
    const XML_PATH_REVERSAL_VALIDATION = 'payment/mstart/reversal_validation';
    const XML_PATH_EMAIL_IDENTITY = 'sales_email/order/identity';

    /**
     * @var MstartLogFactory
     */
    protected $mStartLog;

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var string
     */
    protected $templateId;

    /**
     * Data constructor.
     * @param Context $context
     * @param MstartLogFactory $mstartLogFactory
     * @param StoreManagerInterface $storeManager
     * @param StateInterface $inlineTranslation
     * @param TransportBuilder $transportBuilder
     */
    public function __construct(
        Context $context,
        MstartLogFactory $mstartLogFactory,
        StoreManagerInterface $storeManager,
        StateInterface $inlineTranslation,
        TransportBuilder $transportBuilder
    ) {
        parent::__construct($context);
        
        $this->mStartLog = $mstartLogFactory;
        $this->_storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
    }

    /**
     * @param $type
     * @param null $response
     * @param null $message
     * @return mixed
     * @throws Exception
     */
    public function createMstartLog($type, $response = null, $message = null)
    {
        $mStartLog = $this->mStartLog->create();
        $created = $this->getCurrentDateTime();

        $mStartLog->setCreatedAt($created);
        $mStartLog->setResponse($response);
        $mStartLog->setMessage($message);
        $mStartLog->setType($type);

        $logResource = $mStartLog->getResource();
        $logResource->save($mStartLog);

        return $mStartLog->getData('entity_id');
    }

    /**
     * Get current datetime
     *
     * @return string
     * @throws Exception
     */
    public function getCurrentDateTime()
    {
        $date = new DateTime();
        return $date->format('d.m.Y\TH:i:s');
    }

    /**
     * @param Order $order
     * @param $errorMessage
     * @throws LocalizedException
     * @throws MailException
     * @throws NoSuchEntityException
     */
    public function sendEmail(Order $order, $errorMessage)
    {
        if ((string)$this->getReversalEmailSend() === "1") {
            /* Receiver Detail  */
            $receiverInfo = [
                'name' => $this->getEmailAddressName(),
                'email' => $this->getEmailAddress()
            ];

            $emailTemplateVariables = [];
            $emailTemplateVariables['cancel_error_msg'] = $errorMessage;
            $emailTemplateVariables['order'] = $order;

            $this->templateId = $this->getTemplateId(self::XML_PATH_EMAIL_TEMPLATE_FIELD);
            $this->inlineTranslation->suspend();
            $this->generateTemplate($emailTemplateVariables, $receiverInfo);
            $transport = $this->_transportBuilder->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        }
    }

    /**
     * @return mixed
     */
    public function getReversalEmailSend()
    {
        return $this->getConfigValue(self::XML_PATH_EMAIL_SEND);
    }

    /**
     * @param $path
     * @param $storeId
     * @return mixed
     */
    protected function getConfigValue($path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return mixed
     */
    public function getEmailAddressName()
    {
        return $this->getConfigValue(self::XML_PATH_EMAIL_ADDRESS_NAME,
            $this->getStore()->getStoreId()) ?: "Administrator";
    }

    /**
     * @return StoreInterface
     */
    public function getStore()
    {
        return $this->_storeManager->getStore();
    }

    /**
     * @return mixed
     */
    public function getEmailAddress()
    {
        return $this->getConfigValue(self::XML_PATH_EMAIL_ADDRESS, $this->getStore()->getStoreId());
    }

    /**
     * @param $xmlPath
     * @return mixed
     */
    public function getTemplateId($xmlPath)
    {
        return $this->getConfigValue($xmlPath, $this->getStore()->getStoreId());
    }

    /**
     * @param $emailTemplateVariables
     * @param $receiverInfo
     * @return $this
     * @throws MailException
     * @throws NoSuchEntityException
     */
    public function generateTemplate($emailTemplateVariables, $receiverInfo)
    {
        $this->_transportBuilder->setTemplateIdentifier($this->templateId)
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $this->_storeManager->getStore()->getId(),
                ]
            )
            ->setTemplateVars($emailTemplateVariables)
            ->setFrom($this->getEmailIdentity())
            ->addTo($receiverInfo['email'], $receiverInfo['name']);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmailIdentity()
    {
        return $this->getConfigValue(self::XML_PATH_EMAIL_IDENTITY, $this->getStore()->getStoreId());
    }

    /**
     * @return mixed
     */
    public function getReversalValidation()
    {
        return $this->getConfigValue(self::XML_PATH_REVERSAL_VALIDATION);
    }
}
