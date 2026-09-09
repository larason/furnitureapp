<?php

namespace App\Support;

/**
 * CLOSED Version 1 product assembly requirement (phases/group-C-phases.md §3.4.10).
 */
enum AssemblyRequired: string
{
    case NONE = 'none';
    case PARTIAL = 'partial';
    case FULL = 'full';
}
