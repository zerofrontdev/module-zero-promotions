<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Promotions\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;
use Psr\Log\LoggerInterface;

/**
 * Free gifts in the cart. While the totals are collected, each "Free gift"
 * rule that applies is noted (applies()); after, sync() makes the cart's gift
 * lines match: the rule's SKUs (simple products), Discount Qty each (1 by
 * default), price 0, marked as gifts and kept apart from the same product
 * bought; gifts of rules that no longer apply go. A gift's quantity is the
 * rule's, whatever the cart asks.
 */
class Gifts
{
    public const OPTION = 'zero_gift_rule';

    /** @var array<int, array<int, array{skus: string[], qty: float, label: string}>> By quote, by rule */
    private array $applying = [];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function isGift(AbstractItem $item): bool
    {
        return (bool) $item->getOptionByCode(self::OPTION);
    }

    public function reset(Quote $quote): void
    {
        $this->applying[spl_object_id($quote)] = [];
    }

    public function applies(Quote $quote, Rule $rule): void
    {
        $skus = array_values(array_filter(array_map('trim', explode(',', (string) $rule->getData('zero_gift_skus')))));
        if ($skus) {
            $this->applying[spl_object_id($quote)][(int) $rule->getId()] = [
                'skus' => $skus,
                'qty' => max(1.0, (float) $rule->getDiscountQty()),
                'label' => (string) ($rule->getStoreLabel($quote->getStoreId()) ?: $rule->getName()),
            ];
        }
    }

    /**
     * Lines and rules are matched by SKU in any case (MySQL finds the product
     * either way, so a rule's `wh10-s-blue` adds `WH10-S-Blue`); a new line
     * is still looked up by the rule's own SKU.
     *
     * @return bool Whether the cart's lines changed
     */
    public function sync(Quote $quote): bool
    {
        $wanted = [];
        foreach ($this->applying[spl_object_id($quote)] ?? [] as $ruleId => $gift) {
            foreach ($gift['skus'] as $sku) {
                $wanted[$ruleId . '|' . mb_strtolower($sku)] ??= ['rule' => $ruleId, 'sku' => $sku] + $gift;
            }
        }
        $changed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            if (!self::isGift($item)) {
                continue;
            }
            $key = $item->getOptionByCode(self::OPTION)->getValue() . '|' . mb_strtolower((string) $item->getSku());
            if (!isset($wanted[$key])) {
                $quote->removeItem($item->getId() ?: $item->getItemId());
                if (!$item->getId()) {
                    $item->isDeleted(true);
                }
                $changed = true;
                continue;
            }
            if ((float) $item->getQty() !== (float) $wanted[$key]['qty']) {
                $item->setQty($wanted[$key]['qty']);
                $changed = true;
            }
            unset($wanted[$key]);
        }
        foreach ($wanted as $gift) {
            $changed = $this->add($quote, $gift) || $changed;
        }

        return $changed;
    }

    private function add(Quote $quote, array $gift): bool
    {
        try {
            $product = $this->productRepository->get($gift['sku'], false, $quote->getStoreId(), true);
        } catch (\Throwable) {
            $this->logger->warning(sprintf('Zero_Promotions: rule %d: no gift product %s', $gift['rule'], $gift['sku']));

            return false;
        }
        if ($product->getTypeId() !== 'simple' && $product->getTypeId() !== 'virtual') {
            return false;
        }
        // An option of its own keeps the gift apart from the same product bought.
        $product->addCustomOption(self::OPTION, (string) $gift['rule']);
        $item = $quote->addProduct($product, new DataObject(['qty' => $gift['qty']]));
        if (!$item instanceof AbstractItem) {
            $this->logger->warning(sprintf('Zero_Promotions: rule %d: gift %s not added: %s', $gift['rule'], $gift['sku'], (string) $item));

            return false;
        }
        $item->setCustomPrice(0)->setOriginalCustomPrice(0);
        $item->getProduct()->setIsSuperMode(true);
        $item->addOption([
            'code' => 'additional_options',
            'value' => json_encode([['label' => (string) __('Gift'), 'value' => $gift['label']]]),
            'product_id' => $product->getId(),
        ]);

        return true;
    }
}
