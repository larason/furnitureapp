import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'theme/app_theme.dart';
import 'theme/preview/theme_preview.dart';

/// Root application widget.
///
/// Screen implementation belongs to later Group P phases. Debug builds render
/// the theme preview harness; release builds render a neutral placeholder until
/// the catalog shell is implemented.
class SLFurnituresApp extends StatelessWidget {
  const SLFurnituresApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SL Furnitures',
      theme: AppTheme.light(),
      home: kDebugMode
          ? const ThemePreviewScreen()
          : const BootstrapPlaceholder(),
    );
  }
}

/// Neutral first screen. Replaced by the real catalog shell in later phases.
class BootstrapPlaceholder extends StatelessWidget {
  const BootstrapPlaceholder({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: SafeArea(child: Center(child: Text('SL Furnitures'))),
    );
  }
}
