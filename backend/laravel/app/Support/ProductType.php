<?php

namespace App\Support;

enum ProductType: string
{
    case IN_STOCK = 'IN_STOCK';
    case MADE_TO_ORDER = 'MADE_TO_ORDER';
}
