"use client";

import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogContentText from "@mui/material/DialogContentText";
import DialogTitle from "@mui/material/DialogTitle";

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
}

export function ConfirmDialog({ open, title, description, confirmLabel = "Confirm", cancelLabel = "Cancel", onConfirm, onCancel }: ConfirmDialogProps) {
  return <Dialog open={open} onClose={onCancel} aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-description">
    <DialogTitle id="confirm-dialog-title">{title}</DialogTitle>
    <DialogContent><DialogContentText id="confirm-dialog-description">{description}</DialogContentText></DialogContent>
    <DialogActions><Button onClick={onCancel} autoFocus>{cancelLabel}</Button><Button onClick={onConfirm} variant="contained">{confirmLabel}</Button></DialogActions>
  </Dialog>;
}
