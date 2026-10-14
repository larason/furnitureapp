import Chip from "@mui/material/Chip";

export type StatusIndicatorStatus = "success" | "warning" | "danger" | "info" | "neutral";
type StatusIndicatorChipColor = "default" | "error" | "success" | "warning" | "info";

const CHIP_COLORS: Readonly<Record<StatusIndicatorStatus, StatusIndicatorChipColor>> = {
  success: "success",
  warning: "warning",
  danger: "error",
  info: "info",
  neutral: "default",
};

export function StatusIndicator({ status, label }: Readonly<{ status: StatusIndicatorStatus; label: string }>) {
  return <Chip component="span" label={label} color={CHIP_COLORS[status]} variant="outlined" aria-label={`Status: ${label}`} />;
}
