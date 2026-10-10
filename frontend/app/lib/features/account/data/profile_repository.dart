import '../../../core/network/api_client.dart';
import '../../../core/network/auth_token_provider.dart';
import 'customer_profile.dart';

abstract interface class ProfileRepository {
  Future<CustomerProfile> getProfile();

  Future<CustomerProfile> updateProfile({
    String? name,
    String? phone,
    bool clearPhone = false,
  });
}

class ApiProfileRepository implements ProfileRepository {
  ApiProfileRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<CustomerProfile> getProfile() => _getProfile();

  @override
  Future<CustomerProfile> updateProfile({
    String? name,
    String? phone,
    bool clearPhone = false,
  }) async {
    final values = <String, Object?>{};
    final trimmedName = name?.trim();
    final trimmedPhone = phone?.trim();
    if (trimmedName != null && trimmedName.isNotEmpty) {
      values['name'] = trimmedName;
    }
    if (clearPhone) {
      values['phone'] = null;
    }
    if (trimmedPhone != null && trimmedPhone.isNotEmpty) {
      values['phone'] = trimmedPhone;
    }
    final response = await _apiClient.patch<CustomerProfile>(
      '/me',
      body: values,
      authMode: ApiAuthMode.required,
      decoder: CustomerProfile.fromJson,
    );
    if (response == null) {
      throw const FormatException('Missing profile response.');
    }
    return response.data;
  }

  Future<CustomerProfile> _getProfile() async {
    final response = await _apiClient.get<CustomerProfile>(
      '/me',
      authMode: ApiAuthMode.required,
      decoder: CustomerProfile.fromJson,
    );
    if (response == null) {
      throw const FormatException('Missing profile response.');
    }
    return response.data;
  }
}
