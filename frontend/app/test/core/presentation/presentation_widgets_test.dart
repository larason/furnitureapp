import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/presentation/app_empty_view.dart';
import 'package:sl_furnitures/core/presentation/app_error_view.dart';
import 'package:sl_furnitures/core/presentation/app_inline_error.dart';
import 'package:sl_furnitures/core/presentation/app_loading_view.dart';
import 'package:sl_furnitures/core/presentation/error_presentation_mapper.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

Widget _host(Widget child, {double textScale = 1}) => MaterialApp(
  theme: AppTheme.light(),
  home: MediaQuery(
    data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
    child: Scaffold(body: child),
  ),
);

void main() {
  testWidgets('loading view supports full and inline modes with semantics', (
    tester,
  ) async {
    await tester.pumpWidget(_host(const AppLoadingView()));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.text('Loading'), findsOneWidget);
    expect(find.bySemanticsLabel('Loading'), findsWidgets);

    await tester.pumpWidget(
      _host(const AppLoadingView(inline: true, message: 'Refreshing')),
    );
    expect(find.text('Refreshing'), findsOneWidget);
  });

  testWidgets('empty view renders without an action by default', (
    tester,
  ) async {
    await tester.pumpWidget(
      _host(
        const AppEmptyView(
          title: 'No results',
          description: 'Try a different search.',
          icon: Icons.search_off,
        ),
      ),
    );

    expect(find.text('No results'), findsOneWidget);
    expect(find.text('Try a different search.'), findsOneWidget);
    expect(find.byType(FilledButton), findsNothing);
  });

  testWidgets('empty view invokes the supplied action once', (tester) async {
    var calls = 0;
    await tester.pumpWidget(
      _host(
        AppEmptyView(
          title: 'Nothing here',
          actionLabel: 'Refresh',
          onAction: () => calls++,
        ),
      ),
    );

    await tester.tap(find.text('Refresh'));
    expect(calls, 1);
  });

  testWidgets('error view hides recovery when no callback is supplied', (
    tester,
  ) async {
    const error = ErrorPresentation(
      title: 'Connection problem',
      message: 'Check your connection and try again.',
      recoveryAction: ErrorRecoveryAction.retry,
      requestId: 'req_123',
    );
    await tester.pumpWidget(_host(const AppErrorView(error: error)));

    expect(find.text('Connection problem'), findsOneWidget);
    expect(find.text('Reference: req_123'), findsOneWidget);
    expect(find.text('Try again'), findsNothing);
  });

  testWidgets('error view invokes explicit recovery callback once', (
    tester,
  ) async {
    var calls = 0;
    const error = ErrorPresentation(
      title: 'State changed',
      message: 'Refresh and try again.',
      recoveryAction: ErrorRecoveryAction.refresh,
    );
    await tester.pumpWidget(
      _host(AppErrorView(error: error, onRecovery: () => calls++)),
    );

    await tester.tap(find.text('Refresh'));
    expect(calls, 1);
  });

  testWidgets('inline error is readable and uses semantic status', (
    tester,
  ) async {
    const error = ErrorPresentation(
      title: 'Validation',
      message: 'Enter a valid email.',
    );
    await tester.pumpWidget(_host(const AppInlineError(error: error)));

    expect(find.text('Enter a valid email.'), findsOneWidget);
    expect(find.bySemanticsLabel('Enter a valid email.'), findsOneWidget);
  });

  testWidgets('shared states remain usable at 2x text scale', (tester) async {
    const error = ErrorPresentation(
      title: 'A longer error title for accessibility testing',
      message: 'A longer message should wrap without overflowing the viewport.',
    );
    await tester.pumpWidget(
      _host(const AppErrorView(error: error), textScale: 2),
    );

    await tester.pump();
    expect(tester.takeException(), isNull);
  });
}
