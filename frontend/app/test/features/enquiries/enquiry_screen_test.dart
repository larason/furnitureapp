import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/auth/auth_session.dart';
import 'package:sl_furnitures/core/diagnostics/app_diagnostics.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_repository.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/enquiries/data/enquiry_repository.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request_repository.dart';
import 'package:sl_furnitures/navigation/app_router.dart';
import 'package:sl_furnitures/navigation/app_routes.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  group('contact route', () {
    testWidgets('renders the enquiry form for an anonymous visitor', (
      tester,
    ) async {
      final harness = _Harness(tester);

      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(find.text('How can we help?'), findsOneWidget);
      expect(find.text('Contact us'), findsOneWidget);
      expect(find.text('Subject *'), findsOneWidget);
      expect(find.text('Message *'), findsOneWidget);
      expect(find.text('Send enquiry'), findsOneWidget);
      expect(
        find.text(
          'This is an enquiry. It is not an order, quotation, '
          'payment, or delivery confirmation.',
        ),
        findsOneWidget,
      );
    });

    testWidgets('stays public without authentication', (tester) async {
      final harness = _Harness(tester);

      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(AppRoutes.isProtectedPath(AppRoutes.contact), isFalse);
      expect(find.text('Sign in'), findsNothing);
    });

    testWidgets('shows inline field errors and keeps entered values', (
      tester,
    ) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.subject')),
        'Hi',
      );
      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      expect(find.text('Use between 5 and 200 characters.'), findsOneWidget);
      expect(find.text('Enter a message.'), findsOneWidget);
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey<String>('enquiry.field.subject')),
            )
            .controller
            ?.text,
        'Hi',
      );
    });

    testWidgets('requires anonymous contact details', (tester) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.subject')),
        'Do you deliver to Dodoma?',
      );
      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.message')),
        'I would like to know whether you deliver to Dodoma.',
      );
      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      expect(find.text('Enter your name.'), findsOneWidget);
      expect(
        find.text('Enter a phone number, email address, or both.'),
        findsOneWidget,
      );
    });

    testWidgets('does not state the anonymous contact requirement to a '
        'signed-in customer', (tester) async {
      final harness = _Harness(tester, signedIn: true);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(
        find.text('Your name and at least one way to reach you are required.'),
        findsNothing,
      );
      expect(
        find.byKey(const ValueKey<String>('enquiry.field.name')),
        findsOne,
      );
    });

    testWidgets('a signed-in customer submits without contact details', (
      tester,
    ) async {
      final harness = _Harness(tester, signedIn: true);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.subject')),
        'Do you deliver to Dodoma?',
      );
      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.message')),
        'I would like to know whether you deliver to Dodoma.',
      );
      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      expect(find.text('Enter your name.'), findsNothing);
      expect(
        find.text('Enter a phone number, email address, or both.'),
        findsNothing,
      );
      expect(find.text('Your enquiry has been received.'), findsOneWidget);
    });

    testWidgets('confirms a created enquiry without contacting a private '
        'endpoint', (tester) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.subject')),
        'Do you deliver to Dodoma?',
      );
      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.message')),
        'I would like to know whether you deliver to Dodoma.',
      );
      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.name')),
        'Asha Mushi',
      );
      await tester.enterText(
        find.byKey(const ValueKey<String>('enquiry.field.phone')),
        '+255700000000',
      );
      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      expect(find.text('Your enquiry has been received.'), findsOneWidget);
      expect(
        find.text('Reference: enq_01h8y5a1b2c3d4e5f6g7h8j9'),
        findsOneWidget,
      );
      expect(find.text('Status: OPEN'), findsOneWidget);
      expect(harness.requestedPaths, <String>['/api/v1/enquiries']);
    });

    testWidgets('disables the submit action while a submission is in flight', (
      tester,
    ) async {
      final harness = _Harness(tester, pending: true);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();
      await _fillValidEnquiry(tester);

      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pump();

      expect(find.text('Sending enquiry...'), findsOneWidget);
      final button = tester.widget<FilledButton>(
        find.byKey(const ValueKey<String>('enquiry.submit')),
      );
      expect(button.onPressed, isNull);

      harness.release.complete();
      await tester.pumpAndSettle();
      expect(find.text('Your enquiry has been received.'), findsOneWidget);
    });

    testWidgets('reports a rate limit without an invented deadline', (
      tester,
    ) async {
      final harness = _Harness(tester, rateLimited: true);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();
      await _fillValidEnquiry(tester);

      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      expect(
        find.text('Please wait 60 seconds before trying again.'),
        findsOneWidget,
      );
    });

    testWidgets('reports an uncertain outcome truthfully', (tester) async {
      final harness = _Harness(tester, offline: true);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();
      await _fillValidEnquiry(tester);

      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      expect(
        find.textContaining('could not confirm whether your enquiry'),
        findsOneWidget,
      );
      expect(find.text('Send again'), findsOneWidget);
      expect(find.textContaining('was not submitted'), findsNothing);
    });

    testWidgets('offers a furniture request link without merging the forms', (
      tester,
    ) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey<String>('enquiry.furniture_request_link')),
        findsOneWidget,
      );
      expect(find.text('Furniture requirements'), findsNothing);

      await tester.tap(
        find.byKey(const ValueKey<String>('enquiry.furniture_request_link')),
      );
      await tester.pumpAndSettle();

      expect(find.text('Furniture requirements'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();

      expect(find.text('Enquiry details'), findsOneWidget);
    });

    testWidgets('shows an optional attachment control with its privacy note', (
      tester,
    ) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(find.text('Choose a file'), findsOneWidget);
      expect(
        find.text(
          'Optional: one JPEG, PNG, WebP, or PDF, up to 5 MiB. '
          'Attachments are private.',
        ),
        findsOneWidget,
      );
    });

    testWidgets('exposes the page heading as a semantic header', (
      tester,
    ) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(
        tester.getSemantics(find.text('How can we help?')),
        matchesSemantics(
          isHeader: true,
          label: 'How can we help?',
          textDirection: TextDirection.ltr,
        ),
      );
    });

    testWidgets('remains usable at 2x text scale on a narrow screen', (
      tester,
    ) async {
      tester.platformDispatcher.textScaleFactorTestValue = 2;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final harness = _Harness(tester);
      await harness.pump(viewport: const Size(1080, 2408));

      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();
      expect(find.text('How can we help?'), findsOneWidget);
      expect(tester.takeException(), isNull);

      const submit = ValueKey<String>('enquiry.submit');
      for (
        var step = 0;
        step < 20 && find.byKey(submit).evaluate().isEmpty;
        step++
      ) {
        await tester.drag(find.byType(ListView), const Offset(0, -300));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }

      expect(find.byKey(submit), findsOne);
      expect(
        find.byKey(const ValueKey<String>('enquiry.field.subject')),
        findsNothing,
      );
    });

    testWidgets('returns to the catalog when leaving the confirmation', (
      tester,
    ) async {
      final harness = _Harness(tester);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();
      await _fillValidEnquiry(tester);
      await tester.tap(find.byKey(const ValueKey<String>('enquiry.submit')));
      await tester.pumpAndSettle();

      await tester.tap(
        find.byKey(const ValueKey<String>('enquiry.success.close')),
      );
      await tester.pumpAndSettle();

      expect(find.text('Your enquiry has been received.'), findsNothing);
    });

    testWidgets('falls back to a placeholder without a repository', (
      tester,
    ) async {
      final harness = _Harness(tester, withRepository: false);
      await harness.pump();
      harness.router.go(AppRoutes.contact);
      await tester.pumpAndSettle();

      expect(find.text('How can we help?'), findsNothing);
      expect(
        find.byKey(const ValueKey<String>('enquiry.submit')),
        findsNothing,
      );
    });
  });
}

Future<void> _fillValidEnquiry(WidgetTester tester) async {
  await tester.enterText(
    find.byKey(const ValueKey<String>('enquiry.field.subject')),
    'Do you deliver to Dodoma?',
  );
  await tester.enterText(
    find.byKey(const ValueKey<String>('enquiry.field.message')),
    'I would like to know whether you deliver to Dodoma.',
  );
  await tester.enterText(
    find.byKey(const ValueKey<String>('enquiry.field.name')),
    'Asha Mushi',
  );
  await tester.enterText(
    find.byKey(const ValueKey<String>('enquiry.field.phone')),
    '+255700000000',
  );
}

class _Harness {
  _Harness(
    this.tester, {
    this.rateLimited = false,
    this.offline = false,
    this.withRepository = true,
    this.pending = false,
    this.signedIn = false,
  });

  final WidgetTester tester;
  final bool rateLimited;
  final bool offline;
  final bool withRepository;
  final bool pending;
  final bool signedIn;
  final Completer<void> release = Completer<void>();
  late final GoRouter router;
  final List<String> requestedPaths = <String>[];
  final ValueNotifier<ClerkAuthStatus> _status = ValueNotifier(
    ClerkAuthStatus.signedOut,
  );
  AuthSession? _authSession;

  Future<void> pump({Size? viewport}) async {
    tester.view.physicalSize = viewport ?? const Size(1200, 7200);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final transport = _StubTransport(
      this,
      rateLimited: rateLimited,
      offline: offline,
      pending: pending,
    );
    final client = ApiClient(
      config: const AppConfig(
        environment: AppEnvironment.local,
        apiBaseUrl: 'http://127.0.0.1:8000',
      ),
      transport: transport,
      authTokenProvider: signedIn ? _StubTokenProvider() : null,
    );
    addTearDown(() {
      router.dispose();
      _status.dispose();
      (_authSession as _StubAuthSession?)?.dispose();
    });
    _authSession = signedIn ? _StubAuthSession() : null;
    router = AppRouter.create(
      authState: _status,
      getAuthStatus: () => _status.value,
      diagnostics: const NoopAppDiagnostics(),
      catalogRepository: const _EmptyCatalogRepository(),
      categoryRepository: const _EmptyCategoryRepository(),
      furnitureRequestRepository: _EmptyFurnitureRequestRepository(),
      enquiryRepository: withRepository ? ApiEnquiryRepository(client) : null,
      authSession: _authSession,
    );
    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light(), routerConfig: router),
    );
    await tester.pumpAndSettle();
  }
}

class _StubTokenProvider implements AuthTokenProvider {
  @override
  Future<String?> getToken() async => 'clerk-session-token';
}

class _StubAuthSession extends ChangeNotifier implements AuthSession {
  @override
  ClerkAuthStatus get status => ClerkAuthStatus.signedIn;

  @override
  bool get isSignedIn => true;

  @override
  Future<String?> getToken() async => 'clerk-session-token';

  @override
  Future<void> signOut() async => notifyListeners();
}

class _StubTransport implements ApiTransport {
  _StubTransport(
    this._harness, {
    required this.rateLimited,
    required this.offline,
    required this.pending,
  });

  final _Harness _harness;
  final bool rateLimited;
  final bool offline;
  final bool pending;

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest request) async {
    _harness.requestedPaths.add(request.uri.path);
    if (offline) throw Exception('offline');
    if (pending) await _harness.release.future;
    if (rateLimited) {
      return ApiTransportResponse(
        statusCode: 429,
        headers: const <String, String>{
          'Content-Type': 'application/json',
          'Retry-After': '60',
        },
        bodyBytes: _encode(<String, Object?>{
          'errors': <Object?>[
            <String, Object?>{
              'code': 'RATE_LIMITED',
              'message': 'Too many requests.',
            },
          ],
          'meta': <String, Object?>{'request_id': 'req-429'},
        }),
      );
    }
    return ApiTransportResponse(
      statusCode: 201,
      headers: const <String, String>{'Content-Type': 'application/json'},
      bodyBytes: _encode(<String, Object?>{
        'data': <String, Object?>{
          'id': 'enq_01h8y5a1b2c3d4e5f6g7h8j9',
          'enquiry_status': 'OPEN',
        },
        'meta': <String, Object?>{'request_id': 'req-201'},
      }),
    );
  }

  Uint8List _encode(Map<String, Object?> payload) =>
      Uint8List.fromList(utf8.encode(jsonEncode(payload)));

  @override
  Future<void> close() async {}
}

class _EmptyCatalogRepository implements CatalogRepository {
  const _EmptyCatalogRepository();

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnimplementedError(invocation.memberName.toString());
}

class _EmptyCategoryRepository implements CategoryRepository {
  const _EmptyCategoryRepository();

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnimplementedError(invocation.memberName.toString());
}

class _EmptyFurnitureRequestRepository implements FurnitureRequestRepository {
  @override
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) async => const SubmittedFurnitureRequest(
    id: 'req_01h8x9j2m4k5n6p7q8r9s0t1',
    status: 'SUBMITTED',
  );
}
