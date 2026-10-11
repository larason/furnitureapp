import 'package:flutter/material.dart';

import '../../../core/diagnostics/app_diagnostics.dart';
import 'widgets/legal_document_view.dart';

class TermsConditionsScreen extends StatelessWidget {
  const TermsConditionsScreen({
    super.key,
    this.diagnostics = const NoopAppDiagnostics(),
  });

  final AppDiagnostics diagnostics;

  @override
  Widget build(BuildContext context) => LegalDocumentView(
    title: 'Terms & Conditions',
    assetPath: 'assets/legal/terms-of-service.txt',
    diagnostics: diagnostics,
  );
}
