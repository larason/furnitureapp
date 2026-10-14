import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/core/notifications/device_registration_repository.dart';

void main() {
  test('DEV-002 encodes a device ID as one path segment', () async {
    final transport = _Transport();
    final repository = ApiDeviceRegistrationRepository(_client(transport));

    await repository.deactivate('device/with?reserved#characters');

    expect(
      transport.request!.uri.path,
      '/api/v1/me/devices/device%2Fwith%3Freserved%23characters',
    );
  });
}

ApiClient _client(_Transport transport) => ApiClient(
  config: const AppConfig(
    environment: AppEnvironment.local,
    apiBaseUrl: 'http://127.0.0.1:8000',
  ),
  transport: transport,
  authTokenProvider: _TokenProvider(),
);

class _TokenProvider implements AuthTokenProvider {
  @override
  Future<String?> getToken() async => 'session-token';
}

class _Transport implements ApiTransport {
  ApiTransportRequest? request;

  @override
  Future<void> close() async {}

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest value) async {
    request = value;

    return ApiTransportResponse(
      statusCode: 204,
      headers: const <String, String>{'Content-Type': 'application/json'},
      bodyBytes: Uint8List.fromList(utf8.encode('')),
    );
  }
}
