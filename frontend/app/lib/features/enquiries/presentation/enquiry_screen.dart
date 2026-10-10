import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/attachments/pending_attachment.dart';
import '../../../core/auth/auth_session.dart';
import '../../../navigation/app_routes.dart';
import '../../../theme/app_spacing.dart';
import '../../catalog/presentation/catalog_image.dart';
import '../data/enquiry_draft.dart';
import '../data/enquiry_repository.dart';
import 'enquiry_controller.dart';
import 'enquiry_success_view.dart';
import 'widgets/enquiry_attachment_field.dart';

/// Public `/contact` enquiry intake (ENQ-001).
///
/// Anonymous visitors can send an enquiry without an account. An authenticated
/// CUSTOMER uses the existing Clerk bearer boundary and Laravel derives
/// ownership. The screen never creates a furniture request, order, quotation,
/// payment, or reservation.
class EnquiryScreen extends StatefulWidget {
  const EnquiryScreen({
    super.key,
    required this.repository,
    this.authSession,
    this.product,
  });

  final EnquiryRepository repository;
  final AuthSession? authSession;
  final EnquiryProductContext? product;

  @override
  State<EnquiryScreen> createState() => _EnquiryScreenState();
}

class _EnquiryScreenState extends State<EnquiryScreen> {
  late final EnquiryController _controller = EnquiryController(
    repository: widget.repository,
    authSession: widget.authSession,
    product: widget.product,
  );
  final _formKey = GlobalKey<FormState>();
  final _fields = <String, TextEditingController>{};

  @override
  void dispose() {
    _controller.cancel();
    _controller.dispose();
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: _controller,
    builder: (context, _) {
      final submitted = _controller.submitted;
      return Scaffold(
        appBar: AppBar(title: const Text('Contact us')),
        body: SafeArea(
          child:
              submitted != null &&
                  _controller.state == EnquirySubmissionState.success
              ? EnquirySuccessView(
                  submitted: submitted,
                  onClose: () => _leave(context),
                )
              : _form(context),
        ),
      );
    },
  );

  Widget _form(BuildContext context) {
    final draft = _controller.draft;
    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.space4),
        children: <Widget>[
          Semantics(
            header: true,
            child: Text(
              'How can we help?',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
          ),
          const SizedBox(height: AppSpacing.space3),
          Text(
            'Have a question about our furniture, services, or an existing '
            'enquiry? Send us a message and our team will get back to you.',
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          if (widget.product case final product?)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.space4),
              child: _productContext(context, product),
            ),
          _section(context, 'Enquiry details', <Widget>[
            _field(
              'subject',
              'Subject',
              required: true,
              maxLength: enquirySubjectMaxLength,
            ),
            _field(
              'message',
              'Message',
              required: true,
              maxLength: enquiryMessageMaxLength,
              maxLines: 6,
              textInputAction: TextInputAction.newline,
            ),
          ]),
          _section(context, 'Contact details', <Widget>[
            const Text(
              'Your name and at least one way to reach you are required.',
            ),
            _field('name', 'Your name', maxLength: enquiryNameMaxLength),
            _field(
              'phone',
              'Phone number',
              maxLength: enquiryPhoneMaxLength,
              keyboard: TextInputType.phone,
            ),
            _field(
              'email',
              'Email address',
              maxLength: enquiryEmailMaxLength,
              keyboard: TextInputType.emailAddress,
            ),
            if (_controller.errors['contact'] != null)
              _error(_controller.errors['contact']!),
          ]),
          _section(context, 'Reference attachment', <Widget>[
            const Text(
              'Optional: one JPEG, PNG, WebP, or PDF, up to 5 MiB. '
              'Attachments are private.',
            ),
            const SizedBox(height: AppSpacing.space2),
            EnquiryAttachmentField(
              attachment: draft.attachment,
              enabled: !_controller.isSubmitting,
              onPick: (attachment) async => _setAttachment(attachment),
              onRemove: () => _setAttachment(null),
            ),
            if (_controller.errors['attachment'] != null)
              _error(_controller.errors['attachment']!),
          ]),
          if (_controller.message case final message?)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.space3),
              child: _error(message),
            ),
          FilledButton(
            key: const ValueKey<String>('enquiry.submit'),
            onPressed: _controller.isSubmitting ? null : _submit,
            child: Text(
              _controller.isSubmitting
                  ? 'Sending enquiry...'
                  : _controller.isUncertain
                  ? 'Send again'
                  : 'Send enquiry',
            ),
          ),
          const SizedBox(height: AppSpacing.space4),
          const Text(
            'This is an enquiry. It is not an order, quotation, payment, or '
            'delivery confirmation.',
          ),
          const SizedBox(height: AppSpacing.space3),
          TextButton(
            key: const ValueKey<String>('enquiry.furniture_request_link'),
            onPressed: () => context.go(AppRoutes.furnitureRequests),
            child: const Text(
              'Want furniture made to your specifications? Submit a furniture '
              'request.',
            ),
          ),
        ],
      ),
    );
  }

  Widget _productContext(BuildContext context, EnquiryProductContext product) =>
      Container(
        padding: const EdgeInsets.all(AppSpacing.space3),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).colorScheme.outline),
        ),
        child: Row(
          children: <Widget>[
            SizedBox(
              width: AppSpacing.space9,
              height: AppSpacing.space9,
              child: CatalogImage(
                url: product.imageUrl ?? '',
                semanticLabel: product.imageAlt ?? product.name,
              ),
            ),
            const SizedBox(width: AppSpacing.space3),
            Expanded(
              child: Text(
                'About: ${product.name}',
                style: Theme.of(context).textTheme.titleSmall,
              ),
            ),
          ],
        ),
      );

  Widget _section(BuildContext context, String title, List<Widget> children) =>
      Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.space7),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Semantics(
              header: true,
              child: Text(title, style: Theme.of(context).textTheme.titleLarge),
            ),
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
    TextInputAction? textInputAction,
  }) {
    final controller = _fields.putIfAbsent(
      field,
      () => TextEditingController(text: _value(field)),
    );
    return TextFormField(
      key: ValueKey<String>('enquiry.field.$field'),
      controller: controller,
      enabled: !_controller.isSubmitting,
      decoration: InputDecoration(
        labelText: required ? '$label *' : label,
        errorText: _controller.errors[field],
      ),
      keyboardType: keyboard,
      maxLength: maxLength,
      maxLines: maxLines,
      textInputAction: textInputAction,
      onChanged: (value) => _changeField(field, value),
    );
  }

  Widget _error(String message) => Semantics(
    liveRegion: true,
    child: Text(
      message,
      style: TextStyle(color: Theme.of(context).colorScheme.error),
    ),
  );

  void _leave(BuildContext context) {
    if (context.canPop()) {
      context.pop();
      return;
    }
    context.go(AppRoutes.home);
  }

  void _setAttachment(PendingAttachment? attachment) => _controller.update(
    _controller.draft.copyWith(
      attachment: attachment,
      clearAttachment: attachment == null,
    ),
  );

  Future<void> _submit() => _controller.submit();

  void _changeField(String field, String value) {
    final draft = _controller.draft;
    _controller.update(switch (field) {
      'subject' => draft.copyWith(subject: value),
      'message' => draft.copyWith(message: value),
      'name' => draft.copyWith(name: value),
      'phone' => draft.copyWith(phone: value),
      _ => draft.copyWith(email: value),
    });
  }

  String _value(String field) => switch (field) {
    'subject' => _controller.draft.subject,
    'message' => _controller.draft.message,
    'name' => _controller.draft.name,
    'phone' => _controller.draft.phone,
    _ => _controller.draft.email,
  };
}
