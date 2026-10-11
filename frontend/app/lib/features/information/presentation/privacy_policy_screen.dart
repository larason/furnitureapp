import 'package:flutter/material.dart';

import '../../../core/diagnostics/app_diagnostics.dart';
import 'widgets/legal_document_view.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({
    super.key,
    this.diagnostics = const NoopAppDiagnostics(),
  });

  final AppDiagnostics diagnostics;

  @override
  Widget build(BuildContext context) => LegalDocumentView(
    title: 'Privacy Policy',
    assetPath: 'assets/legal/privacy-policy.txt',
    diagnostics: diagnostics,
  );
}
