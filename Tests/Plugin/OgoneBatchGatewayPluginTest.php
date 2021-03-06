<?php

namespace ETS\Payment\OgoneBundle\Tests\Plugin;

use ETS\Payment\OgoneBundle\Plugin\OgoneBatchGatewayPluginMock;
use ETS\Payment\OgoneBundle\Service\OgoneFileBuilder;
use JMS\Payment\CoreBundle\Entity\ExtendedData;
use JMS\Payment\CoreBundle\Entity\FinancialTransaction;
use JMS\Payment\CoreBundle\Entity\Payment;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;

use ETS\Payment\OgoneBundle\Plugin\OgoneBatchGatewayPlugin;
use ETS\Payment\OgoneBundle\Test\RequestStubber;
use Symfony\Component\HttpKernel\Tests\Logger;

/**
 * Copyright 2013 ETSGlobal <ecs@etsglobal.org>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * OgoneBatchGatewayPluginTest tests
 */
class OgoneBatchGatewayPluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \ETS\Payment\OgoneBundle\Test\RequestStubber
     */
    private $requestStubber;

    public function setUp()
    {
        $this->requestStubber = new RequestStubber(array(
            array('orderID', null, false, 42),
            array('amount', null, false, '42'),
            array('currency', null, false, 'EUR'),
            array('PM', null, false, 'credit card'),
            array('STATUS', null, false, 5),
            array('CARDNO', null, false, 4567123478941234),
            array('PAYID', null, false, 43),
            array('SHASign', null, false, 'fzgzgzghz4648zh6z5h')
        ));
    }

    /**
     * @return array
     */
    public function provideTestTestRequestUrls()
    {
        return array(
            array(true, false, 'getStandardOrderUrl', 'https://secure.ogone.com/ncol/test/orderstandard.asp'),
            array(false, false, 'getStandardOrderUrl', 'https://secure.ogone.com/ncol/prod/orderstandard.asp'),
            array(true, false, 'getDirectQueryUrl', 'https://secure.ogone.com/ncol/test/querydirect.asp'),
            array(false, false, 'getDirectQueryUrl', 'https://secure.ogone.com/ncol/prod/querydirect.asp'),
            array(true, false, 'getBatchUrl', 'https://secure.ogone.com/ncol/test/AFU_agree.asp'),
            array(false, true, 'getBatchUrl', 'https://secure.ogone.com/ncol/prod/AFU_agree.asp'),
        );
    }

    /**
     * @param boolean $debug    Debug mode
     * @param boolean $utf8     UTF8 mode
     * @param string  $method   Method to test
     * @param string  $expected Expected result
     *
     * @dataProvider provideTestTestRequestUrls
     */
    public function testRequestUrls($debug, $utf8, $method, $expected)
    {
        $plugin = $this->createPluginMock(null, $debug, $utf8);

        $reflectionMethod = new \ReflectionMethod('ETS\Payment\OgoneBundle\Plugin\OgoneBatchGatewayPlugin', $method);
        $reflectionMethod->setAccessible(true);

        $this->assertEquals($expected, $reflectionMethod->invoke($plugin));
    }

    public function testNewTransactionRequiresAnAction()
    {
        $plugin = $this->createPluginMock();

        $extendedData = array(
            'ORDERID' => 1234,
            'PAYID' => 9876,
            'CLIENTID' => 'CLIENT1',
            'CLIENTREF' => 123456,
            'ALIASID' => 'ALIASID',
            'ARTICLES' => array(),
        );

        $transaction = $this->createTransaction(42, 'EUR', $extendedData);
        $transaction->getExtendedData()->set('lang', 'en_US');

        try {
            $plugin->approveAndDeposit($transaction, 42);
        } catch(\Exception $e) {
            $this->assertTrue($e instanceof ActionRequiredException);
        }

        $transaction->setState(FinancialTransactionInterface::STATE_PENDING);
        $transaction->setReasonCode('action_required');
        $transaction->setResponseCode('pending');

        return $transaction;
    }

    /**
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException
     * @expectedExceptionMessage Transaction needs to be in state 4
     */
    public function testApproveRequiresAnActionForNewTransactions()
    {
        $plugin = $this->createPluginMock();
        $extendedData = array(
            'ORDERID' => 1234,
            'PAYID' => 9876,
            'CLIENTID' => 'CLIENT1',
            'ALIASID' => 'ALIASID',
            'ARTICLES' => array(),
        );

        $transaction = $this->createTransaction(42, 'EUR', $extendedData);
        $transaction->getExtendedData()->set('lang', 'en_US');

        $plugin->approve($transaction, 42);
    }

    /**
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException
     * @expectedExceptionMessage Transaction needs to be in state 4
     */
    public function testDepositRequiresAnActionForNewTransactions()
    {
        $plugin = $this->createPluginMock();
        $extendedData = array(
            'ORDERID' => 1234,
            'PAYID' => 9876,
            'CLIENTID' => 'CLIENT1',
            'ALIASID' => 'ALIASID',
            'ARTICLES' => array(),
        );

        $transaction = $this->createTransaction(42, 'EUR', $extendedData);
        $transaction->getExtendedData()->set('lang', 'en_US');

        $plugin->deposit($transaction, 42);
    }

    /**
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException
     * @expectedExceptionMessage Refund is still pending, status: 0
     * @depends testNewTransactionRequiresAnAction
     * @param FinancialTransaction $transaction
     */
    public function testNewRefundingTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('new_refund');
        $plugin->reverseDeposit($transaction, 42);
    }

    /**
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException
     * @expectedExceptionMessage Refund is still pending, status: 81
     * @depends testNewTransactionRequiresAnAction
     * @param FinancialTransaction $transaction
     */
    public function testRefundingTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('refunding');
        $plugin->reverseDeposit($transaction, 42);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     */
    public function testRefundedTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('refunded');

        $plugin->reverseDeposit($transaction, 42);

        $this->assertEquals(42, $transaction->getProcessedAmount());
        $this->assertEquals('success', $transaction->getResponseCode());
        $this->assertEquals('none', $transaction->getReasonCode());
        $this->assertEquals('1111111', $transaction->getReferenceNumber());
    }

    /**
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     * @expectedExceptionMessage Refund status 9 is not valid
     * @depends testNewTransactionRequiresAnAction
     * @param FinancialTransaction $transaction
     */
    public function testRefundWithNotValidStateTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('not_refunded');
        $plugin->reverseDeposit($transaction, 42);
    }

    /**
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     * @expectedExceptionMessage Ogone-Response was not successful: A technical problem has occurred. Please try again.
     * @depends testNewTransactionRequiresAnAction
     * @param FinancialTransaction $transaction
     */
    public function testRefundWithErrorTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('refund_error');
        $plugin->reverseDeposit($transaction, 42);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     */
    public function testApproveAndDepositWhenDeposited(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('deposited');

        $plugin->approveAndDeposit($transaction, false);

        $this->assertEquals(42, $transaction->getProcessedAmount());
        $this->assertEquals('success', $transaction->getResponseCode());
        $this->assertEquals('none', $transaction->getReasonCode());
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException
     * @expectedExceptionMessage Payment is still approving, status: 51.
     */
    public function testApprovingTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('approving');

        $plugin->approve($transaction, false);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     */
    public function testApprovedTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('approved');

        $plugin->approve($transaction, false);

        $this->assertEquals(42, $transaction->getProcessedAmount());
        $this->assertEquals('success', $transaction->getResponseCode());
        $this->assertEquals('none', $transaction->getReasonCode());
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException
     * @expectedExceptionMessage Payment is still pending, status: 91.
     */
    public function testDepositingTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('depositing');

        $plugin->deposit($transaction, false);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     */
    public function testDepositedTransaction(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('deposited');

        $plugin->deposit($transaction, false);

        $this->assertEquals(42, $transaction->getProcessedAmount());
        $this->assertEquals('success', $transaction->getResponseCode());
        $this->assertEquals('none', $transaction->getReasonCode());
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     * @expectedExceptionMessage Payment status "8" is not valid for approvment
     */
    public function testApproveWithUnknowStateGenerateAnException(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('not_managed');

        $plugin->approve($transaction, false);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     * @expectedExceptionMessage Payment status "8" is not valid for depositing
     */
    public function testDepositWithUnknowStateGenerateAnException(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('not_managed');

        $plugin->deposit($transaction, false);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     * @expectedExceptionMessage Ogone-Response was not successful: Some of the data entered is incorrect. Please retry.
     */
    public function testInvalidStateGenerateAnException(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('caa_invalid');

        $plugin->deposit($transaction, false);
    }

    /**
     * @param FinancialTransaction $transaction
     *
     * @depends testNewTransactionRequiresAnAction
     * @expectedException        \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     * @expectedExceptionMessage The API request was not successful (Status: 500):
     */
    public function testSendApiRequestFail(FinancialTransaction $transaction)
    {
        $plugin = $this->createPluginMock('500');

        $plugin->approve($transaction, false);
    }

    /**
     * Test the processes function
     */
    public function testProcesses()
    {
        $plugin = $this->createPluginMock('not_managed');

        $this->assertTrue($plugin->processes('ogone_caa'));
        $this->assertFalse($plugin->processes('paypal_express_checkout'));
    }

    /**
     * @param string $amount
     * @param string $currency
     * @param array  $extendedDataValues
     *
     * @return \JMS\Payment\CoreBundle\Entity\FinancialTransaction
     */
    protected function createTransaction($amount, $currency, array $extendedDataValues = array('CN' => 'Foo Bar'))
    {
        $transaction = new FinancialTransaction();
        $transaction->setRequestedAmount($amount);

        $extendedData = new ExtendedData();
        foreach ($extendedDataValues as $key => $value) {
            $extendedData->set($key, $value);
        }

        $paymentInstruction = new PaymentInstruction($amount, $currency, 'ogone_caa', $extendedData);

        $payment = new Payment($paymentInstruction, $amount);
        $payment->addTransaction($transaction);

        return $transaction;
    }

    /**
     * @param string  $state
     * @param boolean $debug
     *
     * @return OgoneBatchGatewayPlugin
     */
    protected function createPluginMock($state = null, $debug = true)
    {
        $tokenMock  = $this->getMock('ETS\Payment\OgoneBundle\Client\TokenInterface');
        $ogoneFileBuilder = new OgoneFileBuilder($tokenMock);
        $logger = new Logger();
        $pluginMock = new OgoneBatchGatewayPluginMock(
            $tokenMock,
            $ogoneFileBuilder,
            $logger,
            $debug
        );

        if (null !== $state) {
            $pluginMock->setFilename($state);
        }

        return $pluginMock;
    }
}
