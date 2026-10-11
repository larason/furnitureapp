import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/features/account/data/customer_profile.dart';
import 'package:sl_furnitures/features/account/data/profile_repository.dart';
import 'package:sl_furnitures/features/account/presentation/account_screen.dart';
import 'package:sl_furnitures/theme/app_theme.dart';

void main() {
  testWidgets('centers the account actions control', (tester) async {
    await tester.pumpWidget(_app(_StubProfileRepository()));
    await tester.pumpAndSettle();

    final center = tester.getCenter(find.byKey(const Key('account.actions')));
    expect(center.dx, tester.getSize(find.byType(MaterialApp)).width / 2);
  });

  testWidgets('disables both profile fields while saving', (tester) async {
    final saveCompleter = Completer<CustomerProfile>();
    final repository = _StubProfileRepository(saveCompleter: saveCompleter);
    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save profile'));
    await tester.pump();

    expect(
      tester.widget<TextField>(find.byType(TextField).at(0)).enabled,
      false,
    );
    expect(
      tester.widget<TextField>(find.byType(TextField).at(1)).enabled,
      false,
    );

    saveCompleter.complete(
      const CustomerProfile(
        id: 'user_01h8x9j2m4k5n6p7q8r9s0t1',
        role: 'CUSTOMER',
        name: 'Asha Mushi',
        email: 'asha@example.test',
        phone: null,
        emailVerified: true,
      ),
    );
    await tester.pumpAndSettle();
  });

  testWidgets('keeps the account actions available when the profile fails', (
    tester,
  ) async {
    final repository = _StubProfileRepository(loadFailure: StateError('down'));

    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    expect(find.text('Try again'), findsOneWidget);
    expect(
      find.text('We could not complete that request. Please try again later.'),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('account.actions')),
      findsOneWidget,
      reason: 'A failed profile read must not remove the sign-out control.',
    );
    expect(
      find.text('Open source licenses'),
      findsOneWidget,
      reason: 'Account resources must remain available when the profile fails.',
    );
  });

  testWidgets('maps a rejected field onto its control and keeps the edit', (
    tester,
  ) async {
    final repository = _StubProfileRepository(
      updateFailure: const ApiError(
        statusCode: 422,
        errors: <ApiErrorItem>[
          ApiErrorItem(
            code: 'INVALID_VALUE',
            message: 'The name must be at least 2 characters.',
            field: 'name',
          ),
        ],
      ),
    );
    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'A');
    await tester.tap(find.text('Save profile'));
    await tester.pumpAndSettle();

    expect(
      find.text('The name must be at least 2 characters.'),
      findsOneWidget,
    );
    expect(
      find.text('A'),
      findsOneWidget,
      reason: 'A rejected save must not discard what the customer typed.',
    );
  });

  testWidgets('reports a connection failure without claiming a save', (
    tester,
  ) async {
    final repository = _StubProfileRepository(
      updateFailure: const ApiTransportException(
        kind: ApiTransportFailureKind.connection,
      ),
    );
    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'Asha');
    await tester.tap(find.text('Save profile'));
    await tester.pumpAndSettle();

    expect(find.text('Check your connection and try again.'), findsOneWidget);
    expect(repository.updateCalls, 1);
    expect(find.text('Asha'), findsOneWidget);
  });

  testWidgets('re-enables the save action after a failure', (tester) async {
    final repository = _StubProfileRepository(
      updateFailure: const ApiTransportException(
        kind: ApiTransportFailureKind.connection,
      ),
    );
    await tester.pumpWidget(_app(repository));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save profile'));
    await tester.pumpAndSettle();

    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Save profile'),
    );
    expect(button.onPressed, isNotNull);
  });
}

Widget _app(ProfileRepository repository) => MaterialApp(
  theme: AppTheme.light(),
  home: AccountScreen(
    repository: repository,
    accountActions: IconButton(
      key: const Key('account.actions'),
      onPressed: () {},
      icon: const Icon(Icons.person),
    ),
  ),
);

class _StubProfileRepository implements ProfileRepository {
  _StubProfileRepository({
    this.updateFailure,
    this.loadFailure,
    this.saveCompleter,
  });

  final Object? updateFailure;
  final Object? loadFailure;
  final Completer<CustomerProfile>? saveCompleter;
  int updateCalls = 0;

  @override
  Future<CustomerProfile> getProfile() {
    final failure = loadFailure;
    if (failure != null) return Future<CustomerProfile>.error(failure);
    return Future<CustomerProfile>.value(
      const CustomerProfile(
        id: 'user_01h8x9j2m4k5n6p7q8r9s0t1',
        role: 'CUSTOMER',
        name: 'Asha Mushi',
        email: 'asha@example.test',
        phone: null,
        emailVerified: true,
      ),
    );
  }

  @override
  Future<CustomerProfile> updateProfile({
    String? name,
    String? phone,
    bool clearPhone = false,
  }) {
    updateCalls++;
    final failure = updateFailure;
    if (failure != null) return Future<CustomerProfile>.error(failure);
    if (saveCompleter != null) return saveCompleter!.future;
    return Future<CustomerProfile>.value(
      CustomerProfile(
        id: 'user_01h8x9j2m4k5n6p7q8r9s0t1',
        role: 'CUSTOMER',
        name: name,
        email: 'asha@example.test',
        phone: phone,
        emailVerified: true,
      ),
    );
  }
}
