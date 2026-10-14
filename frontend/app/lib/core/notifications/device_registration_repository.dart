import '../network/api_client.dart';
import '../network/auth_token_provider.dart';
import '../network/request_cancellation.dart';

/// A registered device installation as returned by the server.
///
/// The registration token is never part of this model: the server does not echo
/// it, so it cannot be accidentally persisted, logged, or displayed.
class RegisteredDevice {
  const RegisteredDevice({
    required this.id,
    required this.platform,
    required this.appInstanceId,
    required this.status,
    this.appVersion,
    this.appBuild,
  });

  final String id;
  final String platform;
  final String appInstanceId;
  final String status;
  final String? appVersion;
  final String? appBuild;

  static RegisteredDevice fromJson(Map<String, Object?> json) {
    final id = json['id'];
    final platform = json['platform'];
    final appInstanceId = json['app_instance_id'];
    final status = json['status'];
    if (id is! String || platform is! String || appInstanceId is! String || status is! String) {
      throw const FormatException('Device registration response is malformed.');
    }

    final appVersion = json['app_version'];
    final appBuild = json['app_build'];

    return RegisteredDevice(
      id: id,
      platform: platform,
      appInstanceId: appInstanceId,
      status: status,
      appVersion: appVersion is String ? appVersion : null,
      appBuild: appBuild is String ? appBuild : null,
    );
  }
}

/// Registration and deactivation of this installation for the signed-in customer.
///
/// Every call is authenticated. The server derives ownership from the session,
/// so nothing here may accept or send a customer identifier.
abstract interface class DeviceRegistrationRepository {
  /// Registers or rotates this installation's token (DEV-001).
  ///
  /// Idempotent: repeating the same call converges on one server record.
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  });

  /// Deactivates a device this customer owns (DEV-002).
  ///
  /// Idempotent for the owner. A device owned by anyone else is reported as
  /// absent, never as a permission failure.
  Future<void> deactivate(String deviceId, {RequestCancellation? cancellation});
}

class ApiDeviceRegistrationRepository implements DeviceRegistrationRepository {
  ApiDeviceRegistrationRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<RegisteredDevice> register({
    required String token,
    required String appInstanceId,
    String? appVersion,
    String? appBuild,
    RequestCancellation? cancellation,
  }) async {
    final response = await _apiClient.post<Map<String, Object?>>(
      '/me/devices',
      authMode: ApiAuthMode.required,
      cancellation: cancellation,
      body: _registrationBody(token, appInstanceId, appVersion, appBuild),
      decoder: _decodeDevice,
    );

    if (response == null) {
      throw const FormatException('Device registration returned no result.');
    }

    return RegisteredDevice.fromJson(response.data);
  }

  @override
  Future<void> deactivate(
    String deviceId, {
    RequestCancellation? cancellation,
  }) async {
    await _apiClient.delete<Object?>(
      '/me/devices/${Uri.encodeComponent(deviceId)}',
      authMode: ApiAuthMode.required,
      cancellation: cancellation,
    );
  }

  /// The only body shape the API accepts. Optional fields are omitted entirely
  /// rather than sent as null, so the server never has to interpret a null.
  static Map<String, Object?> _registrationBody(
    String token,
    String appInstanceId,
    String? appVersion,
    String? appBuild,
  ) {
    final body = <String, Object?>{
      'token': token,
      'platform': 'ANDROID',
      'app_instance_id': appInstanceId,
    };

    if (appVersion != null) {
      body['app_version'] = appVersion;
    }
    if (appBuild != null) {
      body['app_build'] = appBuild;
    }

    return body;
  }

  static Map<String, Object?> _decodeDevice(Object? value) {
    if (value is! Map) {
      throw const FormatException('Device registration response is malformed.');
    }
    return Map<String, Object?>.from(value);
  }
}
