import 'package:flutter/material.dart';

import '../../theme/app_spacing.dart';
import 'error_presentation_mapper.dart';

class AppInlineError extends StatelessWidget {
  const AppInlineError({super.key, required this.error});

  final ErrorPresentation error;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      container: true,
      liveRegion: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error),
          const SizedBox(width: AppSpacing.space2),
          Expanded(child: Text(error.message)),
        ],
      ),
    );
  }
}
