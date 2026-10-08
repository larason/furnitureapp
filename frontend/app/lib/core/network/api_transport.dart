import 'dart:async';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

enum ApiHttpMethod { get, post, put, patch, delete }

extension ApiHttpMethodValue on ApiHttpMethod {
  String get value => name.toUpperCase();
}

abstract interface class ApiRequestBody {
  const ApiRequestBody();
}

class ApiJsonBody extends ApiRequestBody {
  const ApiJsonBody(this.bytes);

  final Uint8List bytes;
}

class ApiMultipartBody extends ApiRequestBody {
  const ApiMultipartBody({this.fields = const {}, this.files = const []});

  final Map<String, String> fields;
  final List<ApiMultipartFile> files;
}

class ApiMultipartFile {
  const ApiMultipartFile({
    required this.field,
    required this.bytes,
    required this.filename,
    this.contentType,
  });

  final String field;
  final Uint8List bytes;
  final String filename;
  final String? contentType;
}

class ApiTransportRequest {
  const ApiTransportRequest({
    required this.method,
    required this.uri,
    required this.headers,
    this.body,
    this.abortTrigger,
  });

  final ApiHttpMethod method;
  final Uri uri;
  final Map<String, String> headers;
  final ApiRequestBody? body;
  final Future<void>? abortTrigger;
}

class ApiTransportResponse {
  const ApiTransportResponse({
    required this.statusCode,
    required this.headers,
    required this.bodyBytes,
  });

  final int statusCode;
  final Map<String, String> headers;
  final Uint8List bodyBytes;
}

abstract interface class ApiTransport {
  Future<ApiTransportResponse> send(ApiTransportRequest request);

  Future<void> close();
}

class HttpApiTransport implements ApiTransport {
  HttpApiTransport({http.Client? client})
    : _client = client ?? http.Client(),
      _ownsClient = client == null;

  final http.Client _client;
  final bool _ownsClient;

  @override
  Future<ApiTransportResponse> send(ApiTransportRequest request) async {
    final baseRequest = await _buildRequest(request);
    final response = await _client.send(baseRequest);
    return ApiTransportResponse(
      statusCode: response.statusCode,
      headers: response.headers,
      bodyBytes: await response.stream.toBytes(),
    );
  }

  Future<http.BaseRequest> _buildRequest(ApiTransportRequest request) async {
    if (request.body is ApiMultipartBody) {
      return _buildMultipartRequest(request);
    }

    final abortable =
        http.AbortableRequest(
            request.method.value,
            request.uri,
            abortTrigger: request.abortTrigger,
          )
          ..followRedirects = false
          ..maxRedirects = 0
          ..headers.addAll(request.headers);
    if (request.body case final ApiJsonBody body) {
      abortable.bodyBytes = body.bytes;
    }
    return abortable;
  }

  Future<http.AbortableRequest> _buildMultipartRequest(
    ApiTransportRequest request,
  ) async {
    final body = request.body! as ApiMultipartBody;
    final multipart = http.MultipartRequest(request.method.value, request.uri)
      ..followRedirects = false
      ..maxRedirects = 0
      ..headers.addAll(request.headers)
      ..fields.addAll(body.fields);
    for (final file in body.files) {
      multipart.files.add(
        http.MultipartFile.fromBytes(
          file.field,
          file.bytes,
          filename: file.filename,
          contentType: file.contentType == null
              ? null
              : MediaType.parse(file.contentType!),
        ),
      );
    }
    final bytes = await multipart.finalize().toBytes();
    return http.AbortableRequest(
        request.method.value,
        request.uri,
        abortTrigger: request.abortTrigger,
      )
      ..followRedirects = false
      ..maxRedirects = 0
      ..headers.addAll(multipart.headers)
      ..bodyBytes = bytes;
  }

  @override
  Future<void> close() async {
    if (_ownsClient) _client.close();
  }
}
