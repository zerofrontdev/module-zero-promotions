<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Promotions\Test\Unit\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zero\Promotions\Model\Gifts;

/**
 * Gift lines match their rule's SKUs in any case: no remove and re-add on
 * every totals run when the rule's spelling differs from the product's.
 */
class GiftsTest extends TestCase
{
    public function testSkuCaseDoesNotChurn(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getAllVisibleItems')->willReturn([$this->giftLine(5, 'WH10-S-Blue', 1.0)]);
        $quote->expects($this->never())->method('removeItem');
        $quote->expects($this->never())->method('addProduct');
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->never())->method('get');
        $gifts = new Gifts($repository, $this->createStub(LoggerInterface::class));

        $gifts->reset($quote);
        $gifts->applies($quote, $this->rule(5, 'wh10-s-blue'));

        $this->assertFalse($gifts->sync($quote));
    }

    public function testAGiftOfARuleThatNoLongerAppliesGoes(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getAllVisibleItems')->willReturn([$this->giftLine(6, 'WH10-S-Blue', 1.0)]);
        $quote->expects($this->once())->method('removeItem')->with(91);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->never())->method('get');
        $gifts = new Gifts($repository, $this->createStub(LoggerInterface::class));

        // Rule 6 gave the line; this totals run, no rule applies.
        $gifts->reset($quote);

        $this->assertTrue($gifts->sync($quote));
    }

    public function testANewGiftIsLookedUpByTheRulesOwnSkuOnce(): void
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getAllVisibleItems')->willReturn([]);
        // Listed twice in two spellings: one line, by the first spelling.
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('get')->with('wh10-s-blue', false, 1, true)
            ->willThrowException(new NoSuchEntityException(__('missing')));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Zero_Promotions: rule 5: no gift product wh10-s-blue');
        $gifts = new Gifts($repository, $logger);

        $gifts->reset($quote);
        $gifts->applies($quote, $this->rule(5, 'wh10-s-blue, WH10-S-BLUE'));

        $this->assertFalse($gifts->sync($quote));
    }

    private function rule(int $id, string $skus): Rule
    {
        $rule = $this->createStub(Rule::class);
        $rule->method('getId')->willReturn($id);
        $rule->method('getData')->willReturnCallback(static fn ($key = '') => $key === 'zero_gift_skus' ? $skus : null);
        $rule->method('getStoreLabel')->willReturn('Free bag');

        return $rule;
    }

    private function giftLine(int $ruleId, string $sku, float $qty): Item
    {
        $item = $this->createMock(Item::class);
        $item->method('getOptionByCode')->willReturnCallback(
            static fn ($code) => $code === Gifts::OPTION ? new DataObject(['value' => (string) $ruleId]) : null
        );
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn($qty);
        $item->method('getId')->willReturn(91);
        $item->expects($this->never())->method('setQty');

        return $item;
    }
}
