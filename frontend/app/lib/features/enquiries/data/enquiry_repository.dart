import '../../../core/attachments/pending_attachment.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_transport.dart';
import '../../../core/network/auth_token_provider.dart';
import '../../../core/network/request_cancellation.dart';
import 'enquiry_draft.dart';

/// ENQ-001 `POST /api/v1/enquiries`.
///
/// [authenticated] selects the auth mode only. Ownership is always derived by
/// Laravel from the verified Clerk token; no user identifier is ever sent.
abstract interface class EnquiryRepository {
  Future<SubmittedEnquiry> submit(
    EnquiryDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  });
}

class ApiEnquiryRepository implements EnquiryRepository {
  ApiEnquiryRepository(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<SubmittedEnquiry> submit(
    EnquiryDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) async {
    final authMode = authenticated ? ApiAuthMode.required : ApiAuthMode.public;
    final attachment = draft.attachment;
    final response = attachment == null
        ? await _apiClient.post<SubmittedEnquiry>(
            '/enquiries',
            body: _values(draft),
            authMode: authMode,
            cancellation: cancellation,
            decoder: SubmittedEnquiry.fromJson,
          )
        : await _apiClient.request<SubmittedEnquiry>(
            '/enquiries',
            method: ApiHttpMethod.post,
            requestBody: _multipartBody(draft),
            authMode: authMode,
            cancellation: cancellation,
            decoder: SubmittedEnquiry.fromJson,
          );
    if (response == null) {
      throw const FormatException('Missing enquiry response.');
    }
    return response.data;
  }

  /// Allow-listed ENQ-001 body. Server-controlled and prohibited fields such as
  /// `user_id`, `enquiry_status`, `order_id`, and `attachment` cannot appear
  /// here, so Laravel's strict unknown-field rejection is never tripped by
  /// this client. Empty optional values are omitted rather than sent as `""`.
  Map<String, Object?> _values(EnquiryDraft draft) {
    final values = <String, Object?>{
      'subject': draft.subject.trim(),
      'message': draft.message.trim(),
    };
    _putIfNotEmpty(values, 'name', draft.name);
    _putIfNotEmpty(values, 'phone', draft.phone);
    _putIfNotEmpty(values, 'email', draft.email);
    _putIfNotEmpty(values, 'product_id', draft.product?.id);
    return values;
  }

  void _putIfNotEmpty(
    Map<String, Object?> values,
    String field,
    String? value,
  ) {
    final trimmed = value?.trim();
    if (trimmed != null && trimmed.isNotEmpty) values[field] = trimmed;
  }

  ApiMultipartBody _multipartBody(EnquiryDraft draft) {
    final attachment = draft.attachment!;
    final fields = <String, String>{
      for (final entry in _values(draft).entries)
        entry.key: entry.value.toString(),
    };
    return ApiMultipartBody(
      fields: fields,
      files: <ApiMultipartFile>[
        ApiMultipartFile(
          field: attachmentMultipartField,
          bytes: attachment.bytes,
          filename: attachment.name,
          contentType: attachment.contentType,
        ),
      ],
    );
  }
}
