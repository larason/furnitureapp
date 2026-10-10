import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../core/auth/auth_session.dart';
import '../../../core/attachments/attachment_read.dart';
import '../../../core/attachments/pending_attachment.dart';
import '../../../theme/app_spacing.dart';
import '../data/furniture_request.dart';
import '../data/furniture_request_repository.dart';
import 'furniture_request_controller.dart';

class FurnitureRequestScreen extends StatefulWidget {
  const FurnitureRequestScreen({
    super.key,
    required this.repository,
    this.authSession,
    this.product,
  });

  final FurnitureRequestRepository repository;
  final AuthSession? authSession;
  final FurnitureRequestProductContext? product;

  @override
  State<FurnitureRequestScreen> createState() => _FurnitureRequestScreenState();
}

class _FurnitureRequestScreenState extends State<FurnitureRequestScreen> {
  late final FurnitureRequestController _controller =
      FurnitureRequestController(
        repository: widget.repository,
        authSession: widget.authSession,
        product: widget.product,
      );
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  String? _attachmentRejection;

  @override
  void dispose() {
    _controller.cancel();
    _controller.dispose();
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: _controller,
    builder: (context, _) => Scaffold(
      appBar: AppBar(title: const Text('Furniture request')),
      body: SafeArea(
        child: _controller.state == FurnitureRequestSubmissionState.success
            ? _success(context)
            : _form(context),
      ),
    ),
  );

  Widget _success(BuildContext context) {
    final submitted = _controller.submitted!;
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.space6),
      child: Semantics(
        liveRegion: true,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Your request has been received.',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: AppSpacing.space3),
            Text(_confirmationMessage),
            const SizedBox(height: AppSpacing.space3),
            Text('Status: ${submitted.status}'),
            const SizedBox(height: AppSpacing.space6),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Done'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _form(BuildContext context) {
    final draft = _controller.draft;
    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.space4),
        children: <Widget>[
          if (widget.product != null) _productContext(context),
          if (widget.product == null) ...<Widget>[
            Text(
              'Tell us about your furniture idea.',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: AppSpacing.space3),
          ],
          _section(context, 'Furniture requirements', <Widget>[
            _field(
              'quantity',
              'Quantity (optional)',
              keyboard: TextInputType.number,
            ),
            _field(
              'length',
              'Length in cm (optional)',
              keyboard: const TextInputType.numberWithOptions(decimal: true),
            ),
            _field(
              'width',
              'Width in cm (optional)',
              keyboard: const TextInputType.numberWithOptions(decimal: true),
            ),
            _field(
              'height',
              'Height in cm (optional)',
              keyboard: const TextInputType.numberWithOptions(decimal: true),
            ),
            _field(
              'material',
              'Material preference (optional)',
              maxLength: 500,
            ),
            _field('color', 'Color preference (optional)', maxLength: 200),
            _field(
              'notes',
              'Tell us about your idea (optional)',
              maxLength: 5000,
              maxLines: 5,
            ),
          ]),
          _section(context, 'Contact details', <Widget>[
            const Text(
              'Your name and at least one way to reach you are required.',
            ),
            _field(
              'name',
              'Your name',
              required: true,
              maxLength: 120,
              keyboard: TextInputType.name,
            ),
            _field(
              'phone',
              'Phone number',
              maxLength: furnitureRequestPhoneMaxLength,
              keyboard: TextInputType.phone,
            ),
            _field(
              'email',
              'Email address',
              maxLength: furnitureRequestEmailMaxLength,
              keyboard: TextInputType.emailAddress,
            ),
            if (_controller.errors['contact'] != null)
              _error(_controller.errors['contact']!),
          ]),
          _section(context, 'Reference attachment', <Widget>[
            const Text(
              'Optional: one JPEG, PNG, WebP, or PDF, up to 5 MiB. Attachments are private.',
            ),
            const SizedBox(height: AppSpacing.space2),
            OutlinedButton(
              onPressed: _controller.isSubmitting ? null : _selectAttachment,
              child: Text(
                draft.attachment == null ? 'Choose a file' : 'Replace file',
              ),
            ),
            if (draft.attachment != null) ...<Widget>[
              Text(
                '${draft.attachment!.name} (${_fileSize(draft.attachment!.size)})',
              ),
              TextButton(
                onPressed: _controller.isSubmitting
                    ? null
                    : () {
                        setState(() => _attachmentRejection = null);
                        _change(clearAttachment: true);
                      },
                child: const Text('Remove file'),
              ),
            ],
            if (_attachmentRejection != null) _error(_attachmentRejection!),
            if (_controller.errors['attachment'] != null)
              _error(_controller.errors['attachment']!),
          ]),
          if (_controller.message != null)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.space3),
              child: _error(_controller.message!),
            ),
          FilledButton(
            key: const ValueKey<String>('furniture_request.submit'),
            onPressed: _controller.isSubmitting ? null : _submit,
            child: Text(
              _controller.isSubmitting
                  ? 'Sending request...'
                  : _controller.state ==
                        FurnitureRequestSubmissionState.uncertain
                  ? 'Submit again'
                  : 'Submit furniture request',
            ),
          ),
          const SizedBox(height: AppSpacing.space3),
          const Text(
            'This is a request, not an order, quotation, payment, or delivery confirmation.',
          ),
        ],
      ),
    );
  }

  /// The catalog product name is absent for a deep link that carried only a
  /// product id, so the copy never falls back to that identifier.
  String get _confirmationMessage {
    final name = widget.product?.name;
    return name == null
        ? 'Our team will review your furniture request and contact you using the details you supplied.'
        : 'Our team will review your request for $name and contact you using the details you supplied.';
  }

  Widget _productContext(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.space4),
    margin: const EdgeInsets.only(bottom: AppSpacing.space6),
    decoration: BoxDecoration(
      border: Border.all(color: Theme.of(context).colorScheme.outline),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Requesting: ${widget.product!.name ?? 'this furniture piece'}',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.space1),
        const Text('Made to order'),
      ],
    ),
  );

  Widget _section(BuildContext context, String title, List<Widget> children) =>
      Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.space7),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: AppSpacing.space3),
            ...children.map(
              (child) => Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.space3),
                child: child,
              ),
            ),
          ],
        ),
      );

  Widget _field(
    String field,
    String label, {
    bool required = false,
    int? maxLength,
    int maxLines = 1,
    TextInputType? keyboard,
  }) {
    final controller = _controllers.putIfAbsent(
      field,
      () => TextEditingController(text: _value(field)),
    );
    return TextFormField(
      controller: controller,
      enabled: !_controller.isSubmitting,
      decoration: InputDecoration(
        labelText: label,
        errorText: _controller.errors[field],
      ),
      keyboardType: keyboard,
      maxLength: maxLength,
      maxLines: maxLines,
      textInputAction: maxLines > 1
          ? TextInputAction.newline
          : TextInputAction.next,
      onChanged: (value) => _changeValue(field, value),
    );
  }

  Widget _error(String message) => Semantics(
    liveRegion: true,
    child: Text(
      message,
      style: TextStyle(color: Theme.of(context).colorScheme.error),
    ),
  );

  Future<void> _selectAttachment() async {
    setState(() => _attachmentRejection = null);
    PlatformFile? file;
    try {
      file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: const <String>['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      );
    } catch (_) {
      return;
    }
    if (!mounted || file == null) {
      return;
    }
    final pickedFile = file;
    final int? size = pickedFile.lengthSync() ?? await pickedFile.length();
    if (!mounted) return;
    if (size != null && size > furnitureRequestMaxAttachmentBytes) {
      setState(() => _attachmentRejection = attachmentTooLargeMessage);
      return;
    }
    // A null length means the size is unknown, so the bounded read below still
    // enforces the limit instead of buffering an arbitrarily large file.
    final read = await readAttachmentBytes(pickedFile.readAsByteStream());
    if (!mounted) return;
    switch (read) {
      case AttachmentReadTooLarge():
        setState(() => _attachmentRejection = attachmentTooLargeMessage);
        return;
      case AttachmentReadFailed():
        setState(() => _attachmentRejection = 'We could not read that file.');
        return;
      case AttachmentReadSuccess(:final bytes):
        setState(() => _attachmentRejection = null);
        _change(
          attachment: FurnitureRequestAttachment(
            name: pickedFile.name,
            bytes: bytes,
            contentType: _contentType(pickedFile.extension),
          ),
        );
    }
  }

  Future<void> _submit() async {
    await _controller.submit();
  }

  void _changeValue(String field, String value) {
    switch (field) {
      case 'quantity':
        _change(quantity: value);
      case 'name':
        _change(name: value);
      case 'phone':
        _change(phone: value);
      case 'email':
        _change(email: value);
      case 'length':
        _change(length: value);
      case 'width':
        _change(width: value);
      case 'height':
        _change(height: value);
      case 'material':
        _change(material: value);
      case 'color':
        _change(color: value);
      case 'notes':
        _change(notes: value);
    }
  }

  void _change({
    String? quantity,
    String? name,
    String? phone,
    String? email,
    String? length,
    String? width,
    String? height,
    String? material,
    String? color,
    String? notes,
    FurnitureRequestAttachment? attachment,
    bool clearAttachment = false,
  }) => _controller.update(
    _controller.draft.copyWith(
      quantity: quantity,
      name: name,
      phone: phone,
      email: email,
      length: length,
      width: width,
      height: height,
      material: material,
      color: color,
      notes: notes,
      attachment: attachment,
      clearAttachment: clearAttachment,
    ),
  );

  String _value(String field) => switch (field) {
    'quantity' => _controller.draft.quantity,
    'name' => _controller.draft.name,
    'phone' => _controller.draft.phone,
    'email' => _controller.draft.email,
    'length' => _controller.draft.length,
    'width' => _controller.draft.width,
    'height' => _controller.draft.height,
    'material' => _controller.draft.material,
    'color' => _controller.draft.color,
    'notes' => _controller.draft.notes,
    _ => '',
  };

  String _contentType(String? extension) => switch (extension?.toLowerCase()) {
    'jpg' || 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
    _ => 'application/octet-stream',
  };
  String _fileSize(int bytes) => '${(bytes / 1024).ceil()} KiB';
}
