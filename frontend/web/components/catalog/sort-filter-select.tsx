"use client";

import FormControl from "@mui/material/FormControl";
import InputLabel from "@mui/material/InputLabel";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import { useState } from "react";

type SortOption = Readonly<{ value: string; label: string; sort: string; sortDirection: string }>;

export const SORT_OPTIONS: readonly SortOption[] = [
  { value: "", label: "Newest", sort: "", sortDirection: "" },
  { value: "price:asc", label: "Price: Low to high", sort: "price", sortDirection: "asc" },
  { value: "price:desc", label: "Price: High to low", sort: "price", sortDirection: "desc" },
  { value: "name:asc", label: "Name: A to Z", sort: "name", sortDirection: "asc" },
  { value: "name:desc", label: "Name: Z to A", sort: "name", sortDirection: "desc" },
];

export function toSortOptionValue(sort: string | undefined, sortDirection: string | undefined): string {
  const match = SORT_OPTIONS.find((option) => option.value !== "" && option.sort === sort && option.sortDirection === sortDirection);
  return match?.value ?? "";
}

export function SortFilterSelect({ sort, sortDirection }: Readonly<{ sort?: string; sortDirection?: string }>) {
  const [value, setValue] = useState(() => toSortOptionValue(sort, sortDirection));
  const selected = SORT_OPTIONS.find((option) => option.value === value) ?? SORT_OPTIONS[0];

  return (
    <>
      <FormControl fullWidth>
        <InputLabel id="sort-label" shrink>Sort by</InputLabel>
        <Select labelId="sort-label" label="Sort by" value={value} displayEmpty onChange={(event) => setValue(String(event.target.value))} renderValue={(selected) => SORT_OPTIONS.find((option) => option.value === selected)?.label ?? SORT_OPTIONS[0].label}>
          {SORT_OPTIONS.map((option) => (
            <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
          ))}
        </Select>
      </FormControl>
      <input type="hidden" readOnly name={selected.sort ? "sort" : ""} value={selected.sort} />
      <input type="hidden" readOnly name={selected.sortDirection ? "sort_direction" : ""} value={selected.sortDirection} />
    </>
  );
}
