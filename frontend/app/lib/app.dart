import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'config/app_config.dart';
import 'core/auth/clerk_auth_adapter.dart';
import 'core/diagnostics/app_diagnostics.dart';
import 'core/network/api_client.dart';
import 'features/catalog/data/catalog_repository.dart';
import 'features/catalog/data/fixture_catalog_repository.dart';
import 'features/categories/data/category_repository.dart';
import 'features/categories/data/fixture_category_repository.dart';
import 'navigation/app_router.dart';
import 'theme/app_spacing.dart';
import 'theme/app_theme.dart';

/// Root application widget.
///
/// [config] is validated by [ConfigValidator.loadCompileTime] before the widget
/// is constructed and is injected here rather than read globally. Phase 16.4
/// consumes the validated config through [apiClient]; no request is made during
/// startup.
///
/// Feature screens are introduced by their owning Group P phases. This root
/// only owns route registration and authentication-aware access control.
class SLFurnituresApp extends StatefulWidget {
  const SLFurnituresApp({
    super.key,
    required this.config,
    this.apiClient,
    this.ownsApiClient = false,
    this.authAdapter,
    this.ownsAuthAdapter = false,
    this.diagnostics = const NoopAppDiagnostics(),
  });

  final AppConfig config;
  final ApiClient? apiClient;
  final bool ownsApiClient;
  final ClerkAuthAdapter? authAdapter;
  final bool ownsAuthAdapter;
  final AppDiagnostics diagnostics;

  @override
  State<SLFurnituresApp> createState() => _SLFurnituresAppState();
}

class _SLFurnituresAppState extends State<SLFurnituresApp> {
  late final ValueNotifier<ClerkAuthStatus> _anonymousAuthState = ValueNotifier(
    ClerkAuthStatus.signedOut,
  );
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    final apiClient = widget.apiClient;
    final useFixtures =
        widget.config.catalogDataSource == CatalogDataSource.fixtures;
    final catalog = useFixtures
        ? FixtureCatalogRepository()
        : apiClient == null
        ? null
        : ApiCatalogRepository(apiClient);
    final categories = useFixtures
        ? const FixtureCategoryRepository()
        : apiClient == null
        ? null
        : ApiCategoryRepository(apiClient);
    _router = AppRouter.create(
      authState: widget.authAdapter ?? _anonymousAuthState,
      getAuthStatus: () =>
          widget.authAdapter?.status ?? ClerkAuthStatus.signedOut,
      diagnostics: widget.diagnostics,
      catalogRepository: catalog,
      categoryRepository: categories,
      showFixtureHero: useFixtures,
    );
  }

  @override
  void dispose() {
    _router.dispose();
    _anonymousAuthState.dispose();
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
    return MaterialApp.router(
      title: 'SL Furnitures',
      theme: AppTheme.light(),
      routerConfig: _router,
      debugShowCheckedModeBanner: false,
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
      debugShowCheckedModeBanner: false,
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
