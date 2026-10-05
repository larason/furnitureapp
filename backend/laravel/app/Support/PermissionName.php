<?php

namespace App\Support;

/**
 * CLOSED Version 1 permission vocabulary (phases/group-C-phases.md §3.2.3).
 * Names are capabilities, not unconditional access; ownership, operational
 * scope and business state are still enforced by domain policies.
 */
enum PermissionName: string
{
    case PRODUCTS_VIEW = 'products.view';
    case PRODUCTS_MANAGE = 'products.manage';

    case INVENTORY_VIEW = 'inventory.view';
    case INVENTORY_MANAGE = 'inventory.manage';

    case ORDERS_VIEW_OPERATIONAL = 'orders.view_operational';
    case ORDERS_ACCEPT = 'orders.accept';
    case ORDERS_PROCESS = 'orders.process';
    case ORDERS_READY_FOR_PICKUP = 'orders.ready_for_pickup';
    case ORDERS_SHIP = 'orders.ship';
    case ORDERS_DELIVER = 'orders.deliver';
    case ORDERS_COMPLETE = 'orders.complete';
    case ORDERS_SET_DELIVERY_FEE = 'orders.set_delivery_fee';

    case REQUESTS_VIEW = 'requests.view';
    case REQUESTS_MANAGE = 'requests.manage';

    case ENQUIRIES_VIEW = 'enquiries.view';
    case ENQUIRIES_MANAGE = 'enquiries.manage';

    case STAFF_APPROVE = 'staff.approve';
    case STAFF_MANAGE = 'staff.manage';

    case USERS_MANAGE_AUTHORIZED = 'users.manage_authorized';
    case AUDIT_VIEW = 'audit.view';
}
