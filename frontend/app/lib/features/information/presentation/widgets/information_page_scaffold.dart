import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';

class InformationPageScaffold extends StatelessWidget {
  const InformationPageScaffold({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Material(
    type: MaterialType.transparency,
    child: SafeArea(
      top: false,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.gutterPhone,
          AppSpacing.space6,
          AppSpacing.gutterPhone,
          AppSpacing.space8,
        ),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: child,
          ),
        ),
      ),
    ),
  );
}
