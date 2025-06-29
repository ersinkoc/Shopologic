<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Cart\Events;

use Shopologic\Core\Ecommerce\Cart\CartItem;

class ItemUpdated
{
    public CartItem $item;

    public function __construct(CartItem $item)
    {
        $this->item = $item;
    }
}