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

use Magento\Framework\Model\AbstractModel;

/**
 * Class MstartLog
 * @package Trive\Mstart\Model
 */
class MstartLog extends AbstractModel
{
    /**
     * @param null $createdAt
     */
    public function setCreatedAt($createdAt = null)
    {
        $this->setData('created_at', $createdAt);
    }

    /**
     * @param null $type
     */
    public function setType($type = null)
    {
        $this->setData('type', $type);
    }

    /**
     * @param null $response
     */
    public function setResponse($response = null)
    {
        $this->setData('response', $response);
    }

    /**
     * @param null $message
     */
    public function setMessage($message = null)
    {
        $this->setData('message', $message);
    }

    protected function _construct()
    {
        $this->_init('Trive\Mstart\Model\ResourceModel\MstartLog');
    }
}
