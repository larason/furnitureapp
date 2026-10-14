export type OperationalRole = "STAFF" | "ADMIN";

export interface OperationalIdentity {
  id: string;
  name: string | null;
  email: string | null;
  role: OperationalRole;
  accountState: "ACTIVE";
  permissions: readonly string[];
}
