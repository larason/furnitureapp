<?php

namespace App\Support;

/**
 * CLOSED Version 1 category-recommendation relation vocabulary (phases/group-C-phases.md §3.3.7).
 * The relationship is directed: one row per (category_id, recommended_category_id) pair.
 */
enum CategoryRecommendationType: string
{
    case COMPLEMENTARY = 'COMPLEMENTARY';
    case PAIR_WITH = 'PAIR_WITH';
    case COMPLETE_THE_LOOK = 'COMPLETE_THE_LOOK';
    case ALTERNATIVE = 'ALTERNATIVE';
}
