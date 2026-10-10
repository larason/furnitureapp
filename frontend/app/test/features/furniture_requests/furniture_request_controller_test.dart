import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
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

  @override
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) {
    entered.complete();
    return release.future.then(
      (_) => const SubmittedFurnitureRequest(
        id: 'req_01h8x9j2m4k5n6p7q8r9s0t1',
        status: 'SUBMITTED',
      ),
    );
  }
}
