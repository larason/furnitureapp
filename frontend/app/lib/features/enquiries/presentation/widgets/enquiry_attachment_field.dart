import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../../core/attachments/pending_attachment.dart';
import '../../../../theme/app_spacing.dart';

/// Optional private reference attachment for an enquiry.
///
/// Uses Android's document picker, so no broad storage permission is
/// requested. Only the file name and size are shown; the bytes stay in memory
/// and are never written to app storage.
class EnquiryAttachmentField extends StatefulWidget {
  const EnquiryAttachmentField({
    super.key,
    required this.attachment,
    required this.onPick,
    required this.onRemove,
    this.enabled = true,
  });

  final PendingAttachment? attachment;
  final Future<void> Function(PendingAttachment attachment) onPick;
  final VoidCallback onRemove;
  final bool enabled;

  @override
  State<EnquiryAttachmentField> createState() => _EnquiryAttachmentFieldState();
}

class _EnquiryAttachmentFieldState extends State<EnquiryAttachmentField> {
  String? _rejection;

  @override
  Widget build(BuildContext context) {
    final selected = widget.attachment;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        OutlinedButton.icon(
          onPressed: widget.enabled ? () => _pick() : null,
          icon: const Icon(Icons.attach_file),
          label: Text(selected == null ? 'Choose a file' : 'Replace file'),
        ),
        if (selected != null) ...<Widget>[
          const SizedBox(height: AppSpacing.space2),
          Semantics(
            container: true,
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Text('${selected.name} (${_fileSize(selected.size)})'),
                ),
                TextButton(
                  onPressed: widget.enabled ? _remove : null,
                  child: const Text('Remove file'),
                ),
              ],
            ),
          ),
        ],
        if (_rejection != null) ...<Widget>[
          const SizedBox(height: AppSpacing.space2),
          Semantics(
            container: true,
            liveRegion: true,
            child: Text(
              _rejection!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        ],
      ],
    );
  }

  void _remove() {
    setState(() => _rejection = null);
    widget.onRemove();
  }

  /// The size is checked before the bytes are read, so an oversized file is
  /// never loaded into memory on a low-memory device. Laravel still applies the
  /// authoritative limit to whatever is actually uploaded.
  Future<void> _pick() async {
    PlatformFile? file;
    try {
      file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: const <String>['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      );
    } catch (_) {
      return;
    }
    if (file == null) return;
    // Android's picker reports the size as part of the pick result, so this
    // normally costs no I/O. Only fall back to measuring when it did not.
    final int? size = file.lengthSync() ?? await file.length();
    if (size != null && size > maxAttachmentBytes) {
      _reject(attachmentTooLargeMessage);
      return;
    }
    Uint8List bytes;
    try {
      bytes = await file.readAsBytes();
    } catch (_) {
      return;
    }
    if (!mounted) return;
    await widget.onPick(
      PendingAttachment(
        name: file.name,
        bytes: bytes,
        contentType: attachmentContentTypeForExtension(file.extension) ?? '',
      ),
    );
    if (!mounted) return;
    setState(() => _rejection = null);
  }

  /// The customer can navigate away while the picker or a measurement is
  /// still pending, so every state change is guarded by [mounted]. A framework
  /// error here must not be swallowed: it is a defect, not an expected
  /// failure, and only the file read is treated as recoverable.
  void _reject(String message) {
    if (!mounted) return;
    setState(() => _rejection = message);
  }

  String _fileSize(int bytes) => '${(bytes / 1024).ceil()} KiB';
}
