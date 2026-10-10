import 'package:flutter/material.dart';

import 'widgets/legal_document_view.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) => const LegalDocumentView(
    title: 'Privacy Policy',
    assetPath: 'assets/legal/privacy-policy.txt',
  );
}
