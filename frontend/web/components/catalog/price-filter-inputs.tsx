"use client";

import Box from "@mui/material/Box";
import TextField from "@mui/material/TextField";
import { useRef } from "react";
import { priceTzsToMinorUnits } from "@/lib/catalog/filters";

type PriceFilterInputsProps = Readonly<{ minPriceTzs: string; maxPriceTzs: string; minPriceMinorUnits?: string; maxPriceMinorUnits?: string }>;

export type PriceVisibleInput = Readonly<{ value: string; setCustomValidity: (message: string) => void }>;
export type PriceHiddenInput = { name: string; value: string; readonly dataset: Readonly<Record<string, string | undefined>> };

const INVALID_PRICE_MESSAGE = "Enter a whole, non-negative TZS amount.";
const INVERTED_RANGE_MESSAGE = "Maximum price must be greater than or equal to minimum price.";

export function PriceFilterInputs({ minPriceTzs, maxPriceTzs, minPriceMinorUnits, maxPriceMinorUnits }: PriceFilterInputsProps) {
  const minInput = useRef<HTMLInputElement>(null);
  const maxInput = useRef<HTMLInputElement>(null);
  const minHiddenInput = useRef<HTMLInputElement>(null);
  const maxHiddenInput = useRef<HTMLInputElement>(null);

  function syncPrices() {
    const min = syncPriceField(minInput.current, minHiddenInput.current);
    const max = syncPriceField(maxInput.current, maxHiddenInput.current);
    if (minInput.current && maxInput.current && min && max && isInvertedRange(min, max)) {
      maxInput.current.setCustomValidity(INVERTED_RANGE_MESSAGE);
    }
  }

  return (
    <Box sx={{ display: "contents" }}>
      <TextField inputRef={minInput} type="text" inputMode="numeric" label="Minimum price (TZS)" defaultValue={minPriceTzs} onChange={syncPrices} fullWidth />
      <input ref={minHiddenInput} type="hidden" data-query-name="min_price" name={minPriceMinorUnits ? "min_price" : ""} defaultValue={minPriceMinorUnits} />
      <TextField inputRef={maxInput} type="text" inputMode="numeric" label="Maximum price (TZS)" defaultValue={maxPriceTzs} onChange={syncPrices} fullWidth />
      <input ref={maxHiddenInput} type="hidden" data-query-name="max_price" name={maxPriceMinorUnits ? "max_price" : ""} defaultValue={maxPriceMinorUnits} />
    </Box>
  );
}

export function syncPriceField(visible: PriceVisibleInput | null, hidden: PriceHiddenInput | null): string {
  if (!visible || !hidden) {
    return "";
  }
  const trimmed = visible.value.trim();
  const converted = trimmed ? priceTzsToMinorUnits(trimmed) : "";
  visible.setCustomValidity(converted === null ? INVALID_PRICE_MESSAGE : "");
  hidden.name = converted ? hidden.dataset.queryName ?? "" : "";
  hidden.value = converted ?? "";
  return converted ?? "";
}

export function isInvertedRange(minimum: string, maximum: string): boolean {
  return minimum.length > maximum.length || (minimum.length === maximum.length && minimum > maximum);
}
