import Chip from "@mui/material/Chip";

export type StatusIndicatorStatus = "success" | "warning" | "danger" | "info" | "neutral";
interface StatusIndicatorProps { status: StatusIndicatorStatus; label: string; }

export function StatusIndicator({ status, label }: StatusIndicatorProps) {
  const color: "default" | "error" | "success" | "warning" | "info" = status === "neutral" ? "default" : status === "danger" ? "error" : status;
  return <Chip component="span" label={label} color={color} variant="outlined" aria-label={`Status: ${label}`} />;
}
