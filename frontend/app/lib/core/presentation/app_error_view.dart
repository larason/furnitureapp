import 'package:flutter/material.dart';

import '../../theme/app_spacing.dart';
import 'error_presentation_mapper.dart';

class AppErrorView extends StatelessWidget {
  const AppErrorView({super.key, required this.error, this.onRecovery});

  final ErrorPresentation error;
  final VoidCallback? onRecovery;

  @override
  Widget build(BuildContext context) {
    final recoveryLabel = switch (error.recoveryAction) {
      ErrorRecoveryAction.retry => 'Try again',
      ErrorRecoveryAction.refresh => 'Refresh',
      ErrorRecoveryAction.none => null,
    };
    return LayoutBuilder(
      builder: (context, constraints) => SingleChildScrollView(
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: constraints.maxHeight),
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.space6),
            child: Center(
              child: Semantics(
                container: true,
                liveRegion: true,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Icon(
                      Icons.error_outline,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    const SizedBox(height: AppSpacing.space3),
                    Text(
                      error.title,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpacing.space2),
                    Text(error.message, textAlign: TextAlign.center),
                    if (error.requestId != null) ...<Widget>[
                      const SizedBox(height: AppSpacing.space2),
                      Text(
                        'Reference: ${error.requestId}',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                    if (onRecovery != null &&
                        recoveryLabel != null) ...<Widget>[
                      const SizedBox(height: AppSpacing.space4),
                      FilledButton(
                        onPressed: onRecovery,
                        child: Text(recoveryLabel),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
