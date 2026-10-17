import Box from "@mui/material/Box";
import Link from "@mui/material/Link";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { SectionHeader } from "@/components/ui/section-header";
import {
  attachmentContentTypeLabel,
  formatAttachmentSize,
  REQUEST_ATTACHMENT_NONE,
  REQUEST_ATTACHMENT_TTL_NOTICE,
  REQUEST_ATTACHMENT_UNAVAILABLE,
} from "@/lib/requests/request-presentation";
import type { RequestAttachmentRecord } from "@/lib/requests/request-resource";

const HEADING_ID = "request-attachment-heading";

/**
 * Attachment inspection. Only the metadata Laravel serializes is shown — the
 * filename, the server-detected content type and the size — plus the temporary
 * signed link when one was issued. Storage keys, disks and paths are never
 * rendered, no URL is ever constructed from an identifier, and no bytes are
 * fetched from this component: the link opens in a new tab.
 */
export function RequestAttachmentPanel({ attachments }: Readonly<{ attachments: readonly RequestAttachmentRecord[] }>) {
  return (
    <Paper component="section" variant="outlined" aria-labelledby={HEADING_ID} sx={{ p: 3 }}>
      <SectionHeader title="Attachment" titleId={HEADING_ID} />
      {attachments.length === 0 ? (
        <Typography sx={{ mt: 2 }}>{REQUEST_ATTACHMENT_NONE}</Typography>
      ) : (
        <Box sx={{ display: "flex", flexDirection: "column", gap: 3, mt: 2 }}>
          {attachments.map((attachment) => (
            <AttachmentEntry key={attachment.id} attachment={attachment} />
          ))}
          <Typography variant="body2" color="text.secondary">{REQUEST_ATTACHMENT_TTL_NOTICE}</Typography>
        </Box>
      )}
    </Paper>
  );
}

function AttachmentEntry({ attachment }: Readonly<{ attachment: RequestAttachmentRecord }>) {
  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
      <Typography component="h3" variant="h4" sx={{ overflowWrap: "anywhere" }}>{attachment.filename}</Typography>
      <Typography color="text.secondary">
        {attachmentContentTypeLabel(attachment.contentType)} · {formatAttachmentSize(attachment.size)}
      </Typography>
      {attachment.url === null ? (
        <Typography color="text.secondary">{REQUEST_ATTACHMENT_UNAVAILABLE}</Typography>
      ) : (
        <Box>
          <Link href={attachment.url} target="_blank" rel="noopener noreferrer" aria-label={`Open attachment ${attachment.filename}`}>
            Open attachment
          </Link>
        </Box>
      )}
    </Box>
  );
}
