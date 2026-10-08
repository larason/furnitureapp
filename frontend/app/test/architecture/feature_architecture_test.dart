import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/core/auth/auth_session.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/diagnostics/diagnostic_sink.dart';
import 'package:sl_furnitures/core/feature_dependencies.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';

import '../support/test_config.dart';

void main() {
  test('feature dependencies use injected shared services', () {
    final apiClient = ApiClient(config: localTestConfig());
    final authSession = _FakeAuthSession();
    final diagnostics = DefaultAppDiagnostics(
      enabled: true,
      sink: InMemoryDiagnosticSink(),
    );
    final dependencies = FeatureDependencies(
      apiClient: apiClient,
      authSession: authSession,
      diagnostics: diagnostics,
    );

    expect(dependencies.apiClient, same(apiClient));
    expect(dependencies.authSession, same(authSession));
    expect(dependencies.diagnostics, same(diagnostics));

    apiClient.close();
    authSession.dispose();
  });

  test('public feature composition does not require an auth session', () {
    final apiClient = ApiClient(config: localTestConfig());
    final dependencies = FeatureDependencies(apiClient: apiClient);

    expect(dependencies.authSession, isNull);
    apiClient.close();
  });

  test('the central router keeps deferred commerce paths unregistered', () {
    final authSession = _FakeAuthSession();
    final router = AppRouter.create(
      authState: authSession,
      getAuthStatus: () => authSession.status,
    );

    expect(router, isA<GoRouter>());
    expect(router.namedLocation(AppRoutes.productsName), AppRoutes.products);
    expect(
      () => router.namedLocation('checkout'),
      throwsA(isA<AssertionError>()),
    );

    router.dispose();
    authSession.dispose();
  });
}

class _FakeAuthSession extends ChangeNotifier implements AuthSession {
  @override
  ClerkAuthStatus status = ClerkAuthStatus.signedOut;

  @override
  bool get isSignedIn => status == ClerkAuthStatus.signedIn;

  @override
  Future<String?> getToken() async => null;

  @override
  Future<void> signOut() async {
    status = ClerkAuthStatus.signedOut;
    notifyListeners();
  }
}
