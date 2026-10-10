import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';

class InformationPageScaffold extends StatelessWidget {
  const InformationPageScaffold({
    super.key,
    required this.title,
    required this.child,
    this.actions,
  });

  final String title;
  final Widget child;
  final List<Widget>? actions;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(title), actions: actions),
    body: SafeArea(
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
