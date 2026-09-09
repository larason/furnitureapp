<?php

namespace App\Support;

/**
 * CLOSED Version 1 category usage context (phases/group-C-phases.md §3.3.4/§3.3.6).
 * Describes where a category is intended to be used; not the hierarchy itself.
 */
enum SpaceType: string
{
    case HOME = 'home';
    case OFFICE = 'office';
    case HYBRID = 'hybrid';
}
