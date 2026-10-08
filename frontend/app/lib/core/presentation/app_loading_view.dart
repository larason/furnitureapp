import 'package:flutter/material.dart';

import '../../theme/app_spacing.dart';

class AppLoadingView extends StatelessWidget {
  const AppLoadingView({
    super.key,
    this.message = 'Loading',
    this.inline = false,
  });

  final String message;
  final bool inline;

  @override
  Widget build(BuildContext context) {
    final indicator = Semantics(
      label: message,
      liveRegion: true,
      child: const CircularProgressIndicator(),
    );
    final content = inline
        ? Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              indicator,
              const SizedBox(width: AppSpacing.space3),
              Text(message),
            ],
          )
        : Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              indicator,
              const SizedBox(height: AppSpacing.space3),
              Text(message),
            ],
          );
    return Center(child: content);
  }
}
