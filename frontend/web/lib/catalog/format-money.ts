import type { Money } from "./types";

const formatter = new Intl.NumberFormat("en-TZ", {
  style: "currency",
  currency: "TZS",
  currencyDisplay: "code",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

export function formatMoney(money: Money): string {
  if (!Number.isSafeInteger(money.amount) || money.amount < 0 || money.currency !== "TZS") {
    throw new RangeError("Invalid catalog money.");
  }

  return formatter.format(money.amount / 100);
}
