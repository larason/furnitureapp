import 'package:flutter/material.dart' hide DiagnosticLevel;
import 'package:flutter/services.dart' hide DiagnosticLevel;

import '../../../../core/diagnostics/app_diagnostics.dart';
import '../../../../core/diagnostics/diagnostic_category.dart';
import '../../../../core/diagnostics/diagnostic_code.dart';
import '../../../../core/diagnostics/diagnostic_event.dart';
import '../../../../core/diagnostics/diagnostic_level.dart';
import '../../../../core/presentation/app_loading_view.dart';
import '../../../../theme/app_spacing.dart';
import 'information_page_scaffold.dart';

class LegalDocumentView extends StatefulWidget {
  const LegalDocumentView({
    super.key,
    required this.title,
    required this.assetPath,
    this.diagnostics = const NoopAppDiagnostics(),
  });

  final String title;
  final String assetPath;
  final AppDiagnostics diagnostics;

  @override
  State<LegalDocumentView> createState() => _LegalDocumentViewState();
}

class _LegalDocumentViewState extends State<LegalDocumentView> {
  late final Future<String> _document = rootBundle.loadString(widget.assetPath);
  bool _failureReported = false;

  @override
  Widget build(BuildContext context) => InformationPageScaffold(
    child: FutureBuilder<String>(
      future: _document,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: AppSpacing.space8),
            child: AppLoadingView(message: 'Loading document'),
          );
        }
        if (snapshot.hasError || !snapshot.hasData) {
          if (!_failureReported) {
            _failureReported = true;
            widget.diagnostics.record(
              DiagnosticEvent.now(
                level: DiagnosticLevel.error,
                category: DiagnosticCategory.presentation,
                code: DiagnosticCode.legalDocumentLoadFailed,
                context: DiagnosticContext(
                  operation: DiagnosticOperation.legalDocument,
                  assetPath: widget.assetPath,
                ),
              ),
            );
          }
          return const _DocumentError();
        }
        return SelectionArea(
          child: Text(
            snapshot.requireData,
            style: Theme.of(
              context,
            ).textTheme.bodyLarge?.copyWith(height: 1.75),
          ),
        );
      },
    ),
  );
}

class _DocumentError extends StatelessWidget {
  const _DocumentError();

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'This document is temporarily unavailable.',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.space2),
        const Text('Please try again later.'),
      ],
    ),
  );
}
