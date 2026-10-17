/**
 * Laravel `PermissionName` identifiers referenced by admin presentation gates.
 * This is not a permission catalog, it grants nothing, and it must never be used
 * as an authorization authority: Laravel remains authoritative for every
 * decision (see `backend/laravel/app/Support/PermissionName.php`).
 */
export const AdminPermissionName = {
  REQUESTS_VIEW: "requests.view",
  ENQUIRIES_VIEW: "enquiries.view",
  NOTIFICATIONS_READ_OPERATIONAL: "notifications.read_operational",
  PRODUCTS_MANAGE: "products.manage",
  INVENTORY_VIEW: "inventory.view",
  INVENTORY_MANAGE: "inventory.manage",
  USERS_MANAGE_AUTHORIZED: "users.manage_authorized",
  AUDIT_VIEW: "audit.view",
} as const;
