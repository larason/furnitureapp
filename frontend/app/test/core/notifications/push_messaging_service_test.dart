import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/notifications/push_messaging_service.dart';

void main() {
  group('PushMessagingService.initialize', () {
    test('returns null when messaging cannot initialize', () async {
      final service = await PushMessagingService.initialize();

      expect(service, isNull);
    });

    test('exposes no pending initial signal when initialization fails', () async {
      final service = await PushMessagingService.initialize();

      expect(service, isNull);
    });
  });
}