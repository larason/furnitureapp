import '../../../core/network/api_client.dart';
import '../../../core/network/api_transport.dart';
import '../../../core/network/auth_token_provider.dart';
import '../../../core/network/request_cancellation.dart';
import 'furniture_request.dart';

abstract interface class FurnitureRequestRepository {
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  });
}

class ApiFurnitureRequestRepository implements FurnitureRequestRepository {
  ApiFurnitureRequestRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<SubmittedFurnitureRequest> submit(
    FurnitureRequestDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) async {
    final response = draft.attachment == null
        ? await _apiClient.post<SubmittedFurnitureRequest>(
            '/requests',
            body: _values(draft),
            authMode: authenticated ? ApiAuthMode.required : ApiAuthMode.public,
            cancellation: cancellation,
            decoder: SubmittedFurnitureRequest.fromJson,
          )
        : await _apiClient.request<SubmittedFurnitureRequest>(
            '/requests',
            method: ApiHttpMethod.post,
            authMode: authenticated ? ApiAuthMode.required : ApiAuthMode.public,
            cancellation: cancellation,
            requestBody: _multipartBody(draft),
            decoder: SubmittedFurnitureRequest.fromJson,
          );
    if (response == null) {
      throw const FormatException('Missing request response.');
    }
    return response.data;
  }

  ApiMultipartBody _multipartBody(FurnitureRequestDraft draft) {
    final values = _values(draft);
    final attachment = draft.attachment!;
    return ApiMultipartBody(
      fields: _multipartFields(values),
      files: <ApiMultipartFile>[
        ApiMultipartFile(
          field: 'attachment',
          bytes: attachment.bytes,
          filename: attachment.name,
          contentType: attachment.contentType,
        ),
      ],
    );
  }

  Map<String, Object?> _values(FurnitureRequestDraft draft) {
    final dimensions = _dimensions(draft);
    return <String, Object?>{
      if (draft.product case final product?) 'product_id': product.id,
      if (draft.quantity.trim().isNotEmpty)
        'quantity': int.parse(draft.quantity),
      'name': draft.name.trim(),
      if (draft.phone.trim().isNotEmpty) 'phone': draft.phone.trim(),
      if (draft.email.trim().isNotEmpty) 'email': draft.email.trim(),
      ...?(dimensions == null
          ? null
          : <String, Object?>{'dimensions': dimensions}),
      if (draft.material.trim().isNotEmpty) 'material': draft.material.trim(),
      if (draft.color.trim().isNotEmpty) 'color': draft.color.trim(),
      if (draft.notes.trim().isNotEmpty) 'notes': draft.notes.trim(),
    };
  }

  Map<String, Object?>? _dimensions(FurnitureRequestDraft draft) {
    final values = <String, num>{};
    for (final entry in <String, String>{
      'length': draft.length,
      'width': draft.width,
      'height': draft.height,
    }.entries) {
      if (entry.value.trim().isNotEmpty) {
        values[entry.key] = num.parse(entry.value);
      }
    }
    return values.isEmpty ? null : <String, Object?>{...values, 'unit': 'cm'};
  }

  Map<String, String> _multipartFields(Map<String, Object?> values) {
    final fields = <String, String>{};
    for (final entry in values.entries) {
      if (entry.value is Map<String, Object?>) {
        for (final dimension
            in (entry.value! as Map<String, Object?>).entries) {
          fields['${entry.key}[${dimension.key}]'] = dimension.value.toString();
        }
      } else {
        fields[entry.key] = entry.value.toString();
      }
    }
    return fields;
  }
}
