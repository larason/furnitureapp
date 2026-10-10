import 'dart:typed_data';

/// Frozen Version 1 attachment limits shared by the customer intake endpoints.
///
/// These mirror Laravel `config/attachments.php`. They exist for client-side
/// guidance only: the server re-detects content type, verifies the signature,
/// and is authoritative for every attachment rule.
const int maxAttachmentBytes = 5 * 1024 * 1024;

const Set<String> attachmentContentTypes = <String>{
  'image/jpeg',
  'image/png',
  'image/webp',
  'application/pdf',
};

/// The canonical multipart field name for an inline attachment.
const String attachmentMultipartField = 'attachment';

/// Best-effort content type for a picked file.
///
/// The extension is a client-side hint only and is never trusted; Laravel
/// detects the real type from the bytes. Returning `null` for an unknown
/// extension makes the advisory type check reject the file before any bytes
/// leave the device.
String? attachmentContentTypeForExtension(String? extension) =>
    switch (extension?.toLowerCase()) {
      'jpg' || 'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'webp' => 'image/webp',
      'pdf' => 'application/pdf',
      _ => null,
    };

/// A file the customer selected, held in memory until its submission sends it.
///
/// Bytes are never written to app storage, logged, or displayed. Only the file
/// name and size are customer-visible.
class PendingAttachment {
  const PendingAttachment({
    required this.name,
    required this.bytes,
    required this.contentType,
  });

  final String name;
  final Uint8List bytes;
  final String contentType;

  int get size => bytes.lengthInBytes;

  String? get validationMessage {
    if (!attachmentContentTypes.contains(contentType)) {
      return 'Choose a JPEG, PNG, WebP, or PDF file.';
    }
    if (size > maxAttachmentBytes) {
      return 'Choose a file no larger than 5 MiB.';
    }
    return null;
  }
}
