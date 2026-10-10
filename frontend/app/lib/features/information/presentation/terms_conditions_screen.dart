import 'package:flutter/material.dart';

import 'widgets/legal_document_view.dart';

class TermsConditionsScreen extends StatelessWidget {
  const TermsConditionsScreen({super.key});

  @override
  Widget build(BuildContext context) => const LegalDocumentView(
    title: 'Terms & Conditions',
    assetPath: 'assets/legal/terms-of-service.txt',
  );
}
