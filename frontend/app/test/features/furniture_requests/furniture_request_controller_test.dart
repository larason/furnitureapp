import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request.dart';
import 'package:sl_furnitures/features/furniture_requests/data/furniture_request_repository.dart';
import 'package:sl_furnitures/features/furniture_requests/presentation/furniture_request_controller.dart';

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
