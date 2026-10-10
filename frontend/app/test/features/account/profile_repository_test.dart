import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_client.dart';
import 'package:sl_furnitures/core/network/api_transport.dart';
import 'package:sl_furnitures/core/network/auth_token_provider.dart';
import 'package:sl_furnitures/features/account/data/profile_repository.dart';

void main() {
  test(
    'USER-001 reads the authenticated profile with a Clerk bearer token',
    () async {
      final transport = _Transport.json(_profile());
      final repository = ApiProfileRepository(_client(transport));

      final profile = await repository.getProfile();

      expect(transport.request!.uri.path, '/api/v1/me');
      expect(
        transport.request!.headers['Authorization'],
        'Bearer session-token',
      );
      expect(profile.email, 'customer@example.com');
      expect(profile.phone, isNull);
    },
  );

  test('USER-002 sends only the documented mutable fields', () async {
    final transport = _Transport.json(_profile(name: 'Asha Mushi'));
    final repository = ApiProfileRepository(_client(transport));

    await repository.updateProfile(name: ' Asha Mushi ', clearPhone: true);

    final body = jsonDecode(
      utf8.decode((transport.request!.body! as ApiJsonBody).bytes),
    );
    expect(transport.request!.method, ApiHttpMethod.patch);
    expect(body, <String, Object?>{'name': 'Asha Mushi', 'phone': null});
    expect(body, isNot(contains('role')));
    expect(body, isNot(contains('email')));
    expect(body, isNot(contains('user_id')));
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

Map<String, Object?> _profile({String? name}) => <String, Object?>{
  'data': <String, Object?>{
    'id': 'user_01h8x9j2m4k5n6p7q8r9s0t1',
    'role': 'CUSTOMER',
    'name': name,
    'email': 'customer@example.com',
    'phone': null,
    'email_verified': true,
    'created_at': '2026-01-10T08:00:00Z',
    'updated_at': '2026-01-12T10:00:00Z',
  },
};

class _TokenProvider implements AuthTokenProvider {
  @override
  Future<String?> getToken() async => 'session-token';
}

class _Transport implements ApiTransport {
  _Transport(this._response);

  factory _Transport.json(Map<String, Object?> payload) => _Transport(
    ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'Content-Type': 'application/json'},
      bodyBytes: Uint8List.fromList(utf8.encode(jsonEncode(payload))),
    ),
  );

  final ApiTransportResponse _response;
  ApiTransportRequest? request;

  @override
  Future<void> close() async {}

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest value) async {
    request = value;
    return _response;
  }
}
