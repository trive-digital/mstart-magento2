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

namespace Trive\Mstart\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Class InstallSchema
 * @package Trive\Mstart\Setup
 */
class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup->startSetup();

        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'acquirer',
            [
                'type' => Table::TYPE_TEXT,
                255,
                [],
                'comment' => 'Acquirer'
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'purchase_installments',
            [
                'type' => Table::TYPE_INTEGER,
                null,
                [],
                'comment' => 'Purchase Installments'
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'card_type',
            [
                'type' => Table::TYPE_TEXT,
                255,
                [],
                'comment' => 'Card Type'
            ]
        );

        $installer->endSetup();
    }
}
