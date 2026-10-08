import '../../config/app_environment.dart';
import 'diagnostic_category.dart';
import 'diagnostic_code.dart';
import 'diagnostic_level.dart';
import 'diagnostic_sanitizer.dart';

enum DiagnosticHttpMethod { get, post, put, patch, delete }

extension DiagnosticHttpMethodValue on DiagnosticHttpMethod {
  String get value => name.toUpperCase();
}

enum DiagnosticOperation {
  startup,
  configuration,
  apiRequest,
  authInitialization,
  authSessionRestore,
  authSessionRenewal,
  authSignOut,
  securePersistence,
  navigation,
  framework,
}

enum DiagnosticTransportFailure {
  connection,
  timeout,
  invalidResponse,
  invalidEnvelope,
  unsupportedContentType,
  authentication,
}

enum DiagnosticRouteFailure {
  unknownRoute,
  invalidParameter,
  unsafeDestination,
}

class DiagnosticContext {
  const DiagnosticContext({
    this.environment,
    this.operation,
    this.httpMethod,
    this.statusCode,
    this.apiErrorCode,
    this.transportFailure,
    this.retryAfterSeconds,
    this.routeFailure,
    this.cancelled = false,
  });

  final AppEnvironment? environment;
  final DiagnosticOperation? operation;
  final DiagnosticHttpMethod? httpMethod;
  final int? statusCode;
  final String? apiErrorCode;
  final DiagnosticTransportFailure? transportFailure;
  final int? retryAfterSeconds;
  final DiagnosticRouteFailure? routeFailure;
  final bool cancelled;

  Map<String, Object> toJson() {
    final result = <String, Object>{};
    if (environment != null) result['environment'] = environment!.name;
    if (operation != null) result['operation'] = operation!.name;
    if (httpMethod != null) result['http_method'] = httpMethod!.value;
    if (statusCode != null) result['status_code'] = statusCode!;
    final safeApiErrorCode = DiagnosticSanitizer.apiErrorCode(apiErrorCode);
    if (safeApiErrorCode != null) {
      result['api_error_code'] = safeApiErrorCode;
    }
    if (transportFailure != null) {
      result['transport_failure'] = transportFailure!.name;
    }
    if (retryAfterSeconds != null) {
      result['retry_after_seconds'] = retryAfterSeconds!;
    }
    if (routeFailure != null) result['route_failure'] = routeFailure!.name;
    if (cancelled) result['cancelled'] = true;
    return result;
  }
}

class DiagnosticEvent {
  DiagnosticEvent({
    required this.level,
    required this.category,
    required this.code,
    required this.timestamp,
    this.requestId,
    this.context = const DiagnosticContext(),
  }) {
    if (requestId != null && !_safeRequestId.hasMatch(requestId!)) {
      throw ArgumentError.value(requestId, 'requestId', 'has an unsafe format');
    }
  }

  factory DiagnosticEvent.now({
    required DiagnosticLevel level,
    required DiagnosticCategory category,
    required DiagnosticCode code,
    String? requestId,
    DiagnosticContext context = const DiagnosticContext(),
  }) => DiagnosticEvent(
    level: level,
    category: category,
    code: code,
    timestamp: DateTime.now().toUtc(),
    requestId: requestId,
    context: context,
  );

  final DiagnosticLevel level;
  final DiagnosticCategory category;
  final DiagnosticCode code;
  final DateTime timestamp;
  final String? requestId;
  final DiagnosticContext context;

  Map<String, Object> toJson() {
    final result = <String, Object>{
      'timestamp': timestamp.toIso8601String(),
      'level': level.name,
      'category': category.name,
      'code': code.value,
    };
    if (requestId != null) result['request_id'] = requestId!;
    final safeContext = context.toJson();
    if (safeContext.isNotEmpty) result['context'] = safeContext;
    return result;
  }

  static final RegExp _safeRequestId = RegExp(
    r'^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$',
  );
}
