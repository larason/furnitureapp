import FormControl from "@mui/material/FormControl";
import InputLabel from "@mui/material/InputLabel";
import Select from "@mui/material/Select";
import type { ReactNode } from "react";

interface FilterSelectProps {
  label: string;
  name: string;
  value: string;
  children: ReactNode;
}

/**
 * The control stays uncontrolled so the native GET form can submit the user's
 * selection, but it is keyed by the resolved query value. When the URL changes —
 * applying or clearing filters — React mounts a fresh Select, which is how MUI
 * expects an uncontrolled default to be reset. Without the key the same instance
 * receives a changed `defaultValue` after mount, which is the reported warning.
 */
export function FilterSelect({ label, name, value, children }: Readonly<FilterSelectProps>) {
  const labelId = `${name}-label`;

  return (
    <FormControl fullWidth>
      <InputLabel id={labelId}>{label}</InputLabel>
      <Select key={value} labelId={labelId} id={name} name={name} defaultValue={value}>
        {children}
      </Select>
    </FormControl>
  );
}