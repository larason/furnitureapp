import 'package:flutter/material.dart';

import '../../../theme/app_spacing.dart';
import '../data/enquiry_draft.dart';

/// Confirmation shown only after Laravel confirmed ENQ-001 creation.
///
/// Displays the returned status and public reference. It makes no claim about
/// email or SMS delivery and promises no response time, and it never links to
/// a private enquiry detail endpoint: the reference is not a credential.
class EnquirySuccessView extends StatelessWidget {
  const EnquirySuccessView({
    super.key,
    required this.submitted,
    required this.onClose,
  });

  final SubmittedEnquiry submitted;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.all(AppSpacing.space6),
    child: Semantics(
      liveRegion: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Semantics(
            header: true,
            child: Text(
              'Your enquiry has been received.',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
          ),
          const SizedBox(height: AppSpacing.space3),
          const Text(
            'Thank you for contacting SL Furnitures. Our team will review your '
            'message and use the contact details you provided to respond.',
          ),
          const SizedBox(height: AppSpacing.space3),
          Text(
            'Reference: ${submitted.id}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: AppSpacing.space2),
          Text('Status: ${submitted.status}'),
          const SizedBox(height: AppSpacing.space6),
          FilledButton(
            key: const ValueKey<String>('enquiry.success.close'),
            onPressed: onClose,
            child: const Text('Done'),
          ),
        ],
      ),
    ),
  );
}
