import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'config/app_config.dart';
import 'core/auth/clerk_auth_adapter.dart';
import 'core/network/api_client.dart';
import 'theme/app_spacing.dart';
import 'theme/app_theme.dart';
import 'theme/preview/theme_preview.dart';

/// Root application widget.
///
/// [config] is validated by [ConfigValidator.loadCompileTime] before the widget
/// is constructed and is injected here rather than read globally. Phase 16.4
/// consumes the validated config through [apiClient]; no request is made during
/// startup.
///
/// Screen implementation belongs to later Group P phases. Debug builds render
/// the theme preview harness; release builds render a neutral placeholder until
/// the catalog shell is implemented.
class SLFurnituresApp extends StatefulWidget {
  const SLFurnituresApp({
    super.key,
    required this.config,
    this.apiClient,
    this.ownsApiClient = false,
    this.authAdapter,
    this.ownsAuthAdapter = false,
  });

  final AppConfig config;
  final ApiClient? apiClient;
  final bool ownsApiClient;
  final ClerkAuthAdapter? authAdapter;
  final bool ownsAuthAdapter;

  @override
  State<SLFurnituresApp> createState() => _SLFurnituresAppState();
}

class _SLFurnituresAppState extends State<SLFurnituresApp> {
  @override
  void dispose() {
    if (widget.ownsApiClient) {
      widget.apiClient?.close();
    }
    if (widget.ownsAuthAdapter) {
      widget.authAdapter?.dispose();
    }
    super.dispose();
  }

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

/// Shown instead of the application when configuration fails validation.
///
/// Startup stops here by design so the app can never continue in an unintended
/// environment. [message] names fields and rules only — never a configuration
/// value — so nothing sensitive is displayed.
class ConfigFailureApp extends StatelessWidget {
  const ConfigFailureApp({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SL Furnitures',
      theme: AppTheme.light(),
      // Resolved inside the MaterialApp so the diagnostic uses the brand
      // typography and colours rather than the ambient fallback theme.
      home: Builder(
        builder: (context) {
          final textTheme = Theme.of(context).textTheme;
          return Scaffold(
            body: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.space6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Text(
                      'Configuration error',
                      style: textTheme.headlineMedium,
                    ),
                    const SizedBox(height: AppSpacing.space3),
                    Text(message, style: textTheme.bodyMedium),
                  ],
                ),
              ),
            ),
          );
        },
      ),
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
