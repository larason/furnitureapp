import 'package:flutter/material.dart';

/// Root application widget.
///
/// Brand theming is intentionally deferred to Phase 16.2. This shell exists so
/// the project builds, runs, and is testable before that mapping is applied.
class SLFurnituresApp extends StatelessWidget {
  const SLFurnituresApp({super.key});

  @override
  Widget build(BuildContext context) {
    return const MaterialApp(
      title: 'SL Furnitures',
      home: BootstrapPlaceholder(),
    );
  }
}

/// Neutral first screen. Replaced by the real catalog shell in later phases.
class BootstrapPlaceholder extends StatelessWidget {
  const BootstrapPlaceholder({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: SafeArea(
        child: Center(
          child: Text('SL Furnitures'),
        ),
      ),
    );
  }
}
