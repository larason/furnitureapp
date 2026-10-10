import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/auth/auth_session.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request_repository.dart';
import 'package:sl_furnitures/features/furniture_requests/presentation/furniture_request_controller.dart';

const _validDraft = FurnitureRequestDraft(
  name: 'Asha Mushi',
  phone: '+255700000000',
);

void main() {
  test('editing after an uncertain submission preserves its warning', () async {
    final controller = FurnitureRequestController(
      repository: _FailingRepository(),
      authSession: null,
    );
    addTearDown(controller.dispose);

    controller.update(
      const FurnitureRequestDraft(name: 'Asha Mushi', phone: '+255700000000'),
    );
    await controller.submit();
    final warning = controller.message;
    controller.update(
      const FurnitureRequestDraft(name: 'Asha Mushi', phone: '+255700000000'),
    );

    expect(controller.state, FurnitureRequestSubmissionState.uncertain);
    expect(controller.message, warning);
  });

  test('disposal during an in-flight submission notifies nothing', () async {
    final repository = _GatedRepository();
    final controller = FurnitureRequestController(
      repository: repository,
      authSession: null,
    );
    var notifications = 0;
    controller.addListener(() => notifications++);

    controller.update(_validDraft);
    final pending = controller.submit();
    await repository.entered.future;

    // Reproduces leaving the screen mid-submission: FurnitureRequestScreen
    // disposes the controller while the POST is still awaiting.
    controller.cancel();
    final beforeDisposal = notifications;
    controller.dispose();
    repository.release.complete();
    await pending;

    expect(notifications, beforeDisposal);
  });

  test('disposal cancels the in-flight request itself', () async {
    final repository = _GatedRepository();
    final controller = FurnitureRequestController(
      repository: repository,
      authSession: null,
    );
    controller.update(_validDraft);
    final pending = controller.submit();
    await repository.entered.future;
    final cancellation = repository.lastCancellation;

    controller.dispose();

    expect(
      cancellation?.isCancelled,
      isTrue,
      reason: 'Disposing must release the request without a screen cancelling.',
    );
    repository.release.complete();
    await expectLater(pending, completes);
  });

  test('a submission that completes after disposal raises nothing', () async {
    final repository = _GatedRepository();
    final controller = FurnitureRequestController(
      repository: repository,
      authSession: null,
    );
    controller.update(_validDraft);
    final pending = controller.submit();
    await repository.entered.future;

    controller.dispose();
    repository.release.complete();

    // Before the guard this completed through submit()'s finally block and
    // tripped ChangeNotifier's use-after-dispose assertion.
    await expectLater(pending, completes);
  });

  test('cancellation while fetching the token never submits', () async {
    final repository = _GatedRepository();
    final session = _SlowTokenSession();
    final controller = FurnitureRequestController(
      repository: repository,
      authSession: session,
    );
    addTearDown(controller.dispose);
    controller.update(_validDraft);

    final pending = controller.submit();
    await session.requested.future;
    controller.cancel();
    session.release.complete();
    await pending;

    expect(
      repository.entered.isCompleted,
      isFalse,
      reason: 'A cancelled submission must not reach the repository.',
    );
    expect(controller.state, FurnitureRequestSubmissionState.editing);
    expect(controller.message, isNull);
  });

  test('a cancelled session failure reports nothing', () async {
    final repository = _GatedRepository();
    final session = _SlowTokenSession(tokenOnRelease: null);
    final controller = FurnitureRequestController(
      repository: repository,
      authSession: session,
    );
    addTearDown(controller.dispose);
    controller.update(_validDraft);

    final pending = controller.submit();
    await session.requested.future;
    controller.cancel();
    session.release.complete();
    await pending;

    expect(controller.state, FurnitureRequestSubmissionState.editing);
    expect(
      controller.message,
      isNull,
      reason: 'A cancelled submission must not blame the session.',
    );
    expect(repository.entered.isCompleted, isFalse);
  });

  test(
    'a token failure inside the client is an authentication error',
    () async {
      final controller = FurnitureRequestController(
        repository: _AuthFailingRepository(),
        authSession: _SignedInTokenSession(),
      );
      addTearDown(controller.dispose);
      controller.update(_validDraft);

      await controller.submit();

      expect(
        controller.state,
        FurnitureRequestSubmissionState.editing,
        reason: 'A pre-send failure cannot have created a duplicate.',
      );
      expect(controller.message, contains('session could not be confirmed'));
      expect(controller.message, isNot(contains('another request')));
    },
  );
}

class _AuthFailingRepository implements FurnitureRequestRepository {
  @override
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) => throw const ApiAuthenticationException();
}

class _SignedInTokenSession implements AuthSession {
  @override
  ClerkAuthStatus get status => ClerkAuthStatus.signedIn;

  @override
  bool get isSignedIn => true;

  @override
  Future<String?> getToken() async => 'session-token';

  @override
  void addListener(VoidCallback listener) {}

  @override
  void removeListener(VoidCallback listener) {}

  @override
  Future<void> signOut() async {}
}

class _SlowTokenSession implements AuthSession {
  _SlowTokenSession({this.tokenOnRelease = 'session-token'});

  /// Token handed back once the pending lookup is released.
  final String? tokenOnRelease;

  final Completer<void> requested = Completer<void>();
  final Completer<void> release = Completer<void>();

  @override
  ClerkAuthStatus get status => ClerkAuthStatus.signedIn;

  @override
  bool get isSignedIn => true;

  @override
  Future<String?> getToken() {
    requested.complete();
    return release.future.then((_) => tokenOnRelease);
  }

  @override
  void addListener(VoidCallback listener) {}

  @override
  void removeListener(VoidCallback listener) {}

  @override
  Future<void> signOut() async {}
}

class _FailingRepository implements FurnitureRequestRepository {
  @override
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) async {
    throw StateError('request outcome is unknown');
  }
}

class _GatedRepository implements FurnitureRequestRepository {
  final Completer<void> entered = Completer<void>();
  final Completer<void> release = Completer<void>();
  RequestCancellation? lastCancellation;

  @override
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) {
    lastCancellation = cancellation;
    entered.complete();
    return release.future.then(
      (_) => const SubmittedFurnitureRequest(
        id: 'req_01h8x9j2m4k5n6p7q8r9s0t1',
        status: 'SUBMITTED',
      ),
    );
  }
}
