import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../../core/attachments/pending_attachment.dart';
import '../../../../theme/app_spacing.dart';

/// Optional private reference attachment for an enquiry.
///
/// Uses Android's document picker, so no broad storage permission is
/// requested. Only the file name and size are shown; the bytes stay in memory
/// and are never written to app storage.
class EnquiryAttachmentField extends StatelessWidget {
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
  Widget build(BuildContext context) {
    final selected = attachment;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        OutlinedButton.icon(
          onPressed: enabled ? () => _pick() : null,
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
                  onPressed: enabled ? onRemove : null,
                  child: const Text('Remove file'),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

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
    try {
      await onPick(
        PendingAttachment(
          name: file.name,
          bytes: await file.readAsBytes(),
          contentType: attachmentContentTypeForExtension(file.extension) ?? '',
        ),
      );
    } catch (_) {
      return;
    }
  }

  String _fileSize(int bytes) => '${(bytes / 1024).ceil()} KiB';
}
