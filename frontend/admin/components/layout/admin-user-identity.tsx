import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { operationalDisplayEmail, operationalDisplayName, operationalRoleLabel } from "@/lib/auth/identity-display";
import type { OperationalIdentity } from "@/lib/auth/types";

/** Renders only the Laravel-resolved local fields the shell is allowed to show. */
export function AdminUserIdentity({ identity }: Readonly<{ identity: OperationalIdentity }>) {
  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: "var(--space-1)" }}>
      <Typography variant="body2" sx={{ fontWeight: "var(--font-weight-medium)" }}>
        {operationalDisplayName(identity)}
      </Typography>
      <Typography variant="body2" color="text.secondary">
        {operationalDisplayEmail(identity)}
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Role: {operationalRoleLabel(identity.role)}
      </Typography>
    </Box>
  );
}
