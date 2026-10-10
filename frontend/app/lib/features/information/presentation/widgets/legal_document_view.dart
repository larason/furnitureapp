import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../core/presentation/app_loading_view.dart';
import '../../../../theme/app_spacing.dart';
import 'information_page_scaffold.dart';

class LegalDocumentView extends StatefulWidget {
  const LegalDocumentView({
    super.key,
    required this.title,
    required this.assetPath,
  });

  final String title;
  final String assetPath;

  @override
  State<LegalDocumentView> createState() => _LegalDocumentViewState();
}

class _LegalDocumentViewState extends State<LegalDocumentView> {
  late final Future<String> _document = rootBundle.loadString(widget.assetPath);

  @override
  Widget build(BuildContext context) => InformationPageScaffold(
    title: widget.title,
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
          return _DocumentError(assetPath: widget.assetPath);
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
  const _DocumentError({required this.assetPath});

  final String assetPath;

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
        Text(
          'The bundled document could not be loaded. Check the app asset '
          'configuration for $assetPath.',
        ),
      ],
    ),
  );
}
