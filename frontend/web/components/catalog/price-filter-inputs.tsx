"use client";

import Box from "@mui/material/Box";
import TextField from "@mui/material/TextField";
import { useRef } from "react";
import { priceTzsToMinorUnits } from "@/lib/catalog/filters";

type PriceFilterInputsProps = Readonly<{ minPriceTzs: string; maxPriceTzs: string; minPriceMinorUnits?: string; maxPriceMinorUnits?: string }>;

export function PriceFilterInputs({ minPriceTzs, maxPriceTzs, minPriceMinorUnits, maxPriceMinorUnits }: PriceFilterInputsProps) {
  const minInput = useRef<HTMLInputElement>(null);
  const maxInput = useRef<HTMLInputElement>(null);
  const minHiddenInput = useRef<HTMLInputElement>(null);
  const maxHiddenInput = useRef<HTMLInputElement>(null);

  function convertPrices(event: React.FormEvent<HTMLDivElement>) {
    const fields = [[minInput.current, minHiddenInput.current], [maxInput.current, maxHiddenInput.current]] as const;
    const convertedValues: (string | null)[] = [];
    for (const [visible, hidden] of fields) {
      if (!visible || !hidden) continue;
      const converted = visible.value.trim() ? priceTzsToMinorUnits(visible.value.trim()) : "";
      visible.setCustomValidity(converted === null ? "Enter a whole, non-negative TZS amount." : "");
      hidden.name = converted ? hidden.dataset.queryName ?? "" : "";
      hidden.value = converted ?? "";
      convertedValues.push(converted);
    }
    if (minInput.current && maxInput.current && isInvertedRange(convertedValues[0], convertedValues[1])) maxInput.current.setCustomValidity("Maximum price must be greater than or equal to minimum price.");
    if (minInput.current?.validationMessage || maxInput.current?.validationMessage) event.preventDefault();
  }

  return (
    <Box onSubmit={convertPrices} sx={{ display: "contents" }}>
      <TextField inputRef={minInput} type="text" inputMode="numeric" label="Minimum price (TZS)" defaultValue={minPriceTzs} fullWidth />
      <input ref={minHiddenInput} type="hidden" data-query-name="min_price" name={minPriceMinorUnits ? "min_price" : ""} defaultValue={minPriceMinorUnits} />
      <TextField inputRef={maxInput} type="text" inputMode="numeric" label="Maximum price (TZS)" defaultValue={maxPriceTzs} fullWidth />
      <input ref={maxHiddenInput} type="hidden" data-query-name="max_price" name={maxPriceMinorUnits ? "max_price" : ""} defaultValue={maxPriceMinorUnits} />
    </Box>
  );
}

function isInvertedRange(minimum: string | null | undefined, maximum: string | null | undefined): boolean {
  if (!minimum || !maximum) return false;
  return minimum.length > maximum.length || (minimum.length === maximum.length && minimum > maximum);
}
