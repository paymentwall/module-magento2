<?php

namespace Paymentwall\Paymentwall\Test\Unit\Block;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Paymentwall\Paymentwall\Block\Paymentwall;

/**
 * Class PaymentwallTest
 *
 * @package Paymentwall\Paymentwall\Test\Unit\Block
 */
class PaymentwallTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Paymentwall
     */
    private $block;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->block = (new ObjectManager($this))->getObject(
            '\Paymentwall\Paymentwall\Block\Paymentwall'
        );
    }

    public function testGetWidget()
    {
        $this->block->setData(
            'widget',
            '<html><p>test</p></html>'
        );

        $this->assertSame(
            '<html><p>test</p></html>',
            $this->block->getWidget()
        );
    }
}
