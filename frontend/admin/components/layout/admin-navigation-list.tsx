"use client";

import Box from "@mui/material/Box";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemIcon from "@mui/material/ListItemIcon";
import ListItemText from "@mui/material/ListItemText";
import Typography from "@mui/material/Typography";
import NextLink from "next/link";
import type { AdminNavigationGroup } from "@/lib/navigation/admin-navigation";

interface AdminNavigationListProps {
  groups: readonly AdminNavigationGroup[];
  activeItemId: string | null;
  onNavigate?: () => void;
}

const ITEM_SX = {
  borderRadius: "var(--radius-sm)",
  minHeight: 44,
  gap: 1.5,
  px: 1.5,
  borderLeft: "var(--border-width) solid transparent",
  "&.Mui-selected": { backgroundColor: "var(--surface-editorial)", color: "text.primary", borderLeftColor: "var(--accent-brand)" },
  "&.Mui-selected:hover": { backgroundColor: "var(--surface-editorial)" },
  "&.Mui-focusVisible": { boxShadow: "var(--focus-ring)" },
} as const;

export function AdminNavigationList({ groups, activeItemId, onNavigate }: Readonly<AdminNavigationListProps>) {
  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 3 }}>
      {groups.map((group) => (
        <Box key={group.id} component="nav" aria-labelledby={`navigation-group-${group.id}`}>
          <Typography id={`navigation-group-${group.id}`} variant="body2" color="text.secondary" sx={{ px: 1.5, pb: 1 }}>
            {group.label}
          </Typography>
          <List sx={{ py: 0 }}>
            {group.items.map((item) => {
              const active = item.id === activeItemId;
              const Icon = item.icon;
              return (
                <ListItem key={item.id} disablePadding>
                  <ListItemButton component={NextLink} href={item.href} selected={active} aria-current={active ? "page" : undefined} onClick={onNavigate} sx={ITEM_SX}>
                    <ListItemIcon sx={{ minWidth: 0, color: "inherit" }}>
                      <Icon aria-hidden fontSize="small" />
                    </ListItemIcon>
                    <ListItemText primary={item.label} slotProps={{ primary: { variant: "body2" } }} />
                  </ListItemButton>
                </ListItem>
              );
            })}
          </List>
        </Box>
      ))}
    </Box>
  );
}
