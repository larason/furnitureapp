import 'dart:typed_data';

import 'pending_attachment.dart';

/// Outcome of a size-bounded attachment read.
sealed class AttachmentRead {
  const AttachmentRead();
}

/// The file was read and fits the limit.
final class AttachmentReadSuccess extends AttachmentRead {
  const AttachmentReadSuccess(this.bytes);

  final Uint8List bytes;
}

/// The file exceeded the limit and was abandoned before being fully buffered.
final class AttachmentReadTooLarge extends AttachmentRead {
  const AttachmentReadTooLarge();
}

/// The bytes could not be read at all.
final class AttachmentReadFailed extends AttachmentRead {
  const AttachmentReadFailed();
}

/// Reads a file stream without ever buffering more than [maxBytes].
///
/// `PlatformFile.length`/`lengthSync` return `null` when the size cannot be
/// determined, which is distinct from a genuinely empty file, so a length check
/// alone cannot be trusted to keep an oversized file out of memory. Callers
/// therefore pass the picked file's byte stream here, and this stops as soon as
/// the accumulated size passes the limit. An unknown-size file stays bounded.
///
/// Laravel remains authoritative and re-checks the upload.
Future<AttachmentRead> readAttachmentBytes(
  Stream<Uint8List> bytes, {
  int maxBytes = maxAttachmentBytes,
}) async {
  final chunks = BytesBuilder(copy: false);
  var total = 0;
  try {
    await for (final chunk in bytes) {
      total += chunk.length;
      if (total > maxBytes) return const AttachmentReadTooLarge();
      chunks.add(chunk);
    }
  } catch (_) {
    return const AttachmentReadFailed();
  }
  return AttachmentReadSuccess(chunks.takeBytes());
}
