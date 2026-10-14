import Paper from "@mui/material/Paper";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import type { ReactNode } from "react";

export interface DataTableColumn<Row> { key: string; label: string; render: (row: Row) => ReactNode; }
interface DataTableProps<Row> { columns: readonly DataTableColumn<Row>[]; rows: readonly Row[]; getRowKey: (row: Row) => string; empty?: ReactNode; }

export function DataTable<Row>({ columns, rows, getRowKey, empty }: DataTableProps<Row>) {
  return <TableContainer component={Paper} variant="outlined" sx={{ overflowX: "auto" }}><Table>
    <TableHead><TableRow>{columns.map((column) => <TableCell key={column.key} component="th" scope="col">{column.label}</TableCell>)}</TableRow></TableHead>
    <TableBody>
      {rows.length === 0 && empty ? <TableRow><TableCell colSpan={columns.length}>{empty}</TableCell></TableRow> : null}
      {rows.map((row) => <TableRow key={getRowKey(row)}>{columns.map((column) => <TableCell key={column.key}>{column.render(row)}</TableCell>)}</TableRow>)}
    </TableBody>
  </Table></TableContainer>;
}
