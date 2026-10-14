import CircularProgress from "@mui/material/CircularProgress";
import Paper from "@mui/material/Paper";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";

interface LoadingStateProps { label: string; }

export function LoadingState({ label }: Readonly<LoadingStateProps>) {
  return <Paper component="section" variant="outlined" aria-busy="true" aria-label={label} sx={{ flex: 1, p: 3, minWidth: 0 }}><Box sx={{ display: "flex", flexDirection: "row", gap: 1.5, alignItems: "center" }}><CircularProgress size={24} aria-hidden="true" /><Typography color="text.secondary">{label}</Typography></Box></Paper>;
}
