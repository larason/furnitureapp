import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/clerk_auth_adapter.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('informational routes remain public while signed out', (
    tester,
  ) async {
    final status = ValueNotifier(ClerkAuthStatus.signedOut);
    final router = AppRouter.create(
      authState: status,
      getAuthStatus: () => status.value,
    );
    addTearDown(() {
      router.dispose();
      status.dispose();
    });
    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );

    final routes = <String, String>{
      AppRoutes.about: 'About SL Furnitures',
      AppRoutes.privacyPolicy: 'Privacy Policy',
      AppRoutes.termsAndConditions: 'Terms & Conditions',
      AppRoutes.openSourceLicenses: 'SL Furnitures',
    };
    for (final entry in routes.entries) {
      router.go(entry.key);
      await tester.pumpAndSettle();
      expect(find.text(entry.value), findsWidgets);
      expect(router.routeInformationProvider.value.uri.path, entry.key);
    }
  });
}
