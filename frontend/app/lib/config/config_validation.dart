import 'package:flutter/foundation.dart';

import 'app_config.dart';
import 'app_environment.dart';

/// Raised when configuration cannot be validated.
///
/// The [message] names fields and rules only, never a configuration value, so
/// it is safe to render on screen or write to a log.
class ConfigValidationException implements Exception {
  const ConfigValidationException(this.message);

  final String message;

  @override
  String toString() => 'ConfigValidationException: $message';
}

/// Compile-time defines read through `--dart-define-from-file`.
abstract final class CompileTimeDefines {
  // Dart can detect named defines, but it cannot enumerate arbitrary defines.
  static const String forbiddenSecretField = 'CLERK_SECRET_KEY';
  static const bool _hasForbiddenSecret = bool.hasEnvironment(
    forbiddenSecretField,
  );

  static const Map<String, Object?> values = <String, Object?>{
    'APP_ENV': String.fromEnvironment('APP_ENV'),
    'API_BASE_URL': String.fromEnvironment('API_BASE_URL'),
    'CLERK_PUBLISHABLE_KEY': String.fromEnvironment('CLERK_PUBLISHABLE_KEY'),
    if (bool.hasEnvironment('CATALOG_DATA_SOURCE'))
      'CATALOG_DATA_SOURCE': String.fromEnvironment('CATALOG_DATA_SOURCE'),
    if (bool.hasEnvironment('ENABLE_DIAGNOSTICS'))
      'ENABLE_DIAGNOSTICS': String.fromEnvironment('ENABLE_DIAGNOSTICS'),
    if (_hasForbiddenSecret)
      forbiddenSecretField: String.fromEnvironment(forbiddenSecretField),
  };
}

/// Deterministic validation of the environment-configuration contract.
///
/// Parsing and validation are separated from compile-time access so every rule
/// can be tested with controlled values, without touching process environment
/// variables or the network.
abstract final class ConfigValidator {
  static const String _environmentField = 'APP_ENV';
  static const String _apiOriginField = 'API_BASE_URL';
  static const String _clerkField = 'CLERK_PUBLISHABLE_KEY';
  static const String _diagnosticsField = 'ENABLE_DIAGNOSTICS';
  static const String _catalogDataSourceField = 'CATALOG_DATA_SOURCE';

  /// Fields the application reads. Anything else is rejected so a mistyped or
  /// secret field can never be silently ignored.
  static const Set<String> _acceptedFields = <String>{
    _environmentField,
    _apiOriginField,
    _clerkField,
    _diagnosticsField,
    _catalogDataSourceField,
  };

  /// Rejected with a specific diagnostic rather than as a generic unknown field.
  static const String _forbiddenSecretField =
      CompileTimeDefines.forbiddenSecretField;

  /// Tracked example values that must never validate as runnable config.
  static const List<String> _placeholderMarkers = <String>[
    'example.invalid',
    'example.com',
    'example.org',
    'example.net',
    'changeme',
    'change-me',
    'your-staging',
    'your-production',
    'your-api',
    'placeholder',
  ];

  /// Loads the build-time configuration and validates it.
  static AppConfig loadCompileTime() =>
      validate(CompileTimeDefines.values, isDebugBuild: kDebugMode);

  /// Validates [raw] and returns an immutable configuration.
  static AppConfig validate(
    Map<String, Object?> raw, {
    bool isDebugBuild = true,
  }) {
    _rejectUnacceptedFields(raw);
    final environment = _requireEnvironment(raw);
    final apiBaseUrl = _requireApiOrigin(raw, environment);
    return AppConfig(
      environment: environment,
      apiBaseUrl: apiBaseUrl,
      catalogDataSource: _resolveCatalogDataSource(
        raw,
        environment,
        isDebugBuild,
      ),
      clerkPublishableKey: _resolvePublishableKey(raw, environment),
      enableDiagnostics: _resolveDiagnostics(raw),
    );
  }

  static CatalogDataSource _resolveCatalogDataSource(
    Map<String, Object?> raw,
    AppEnvironment environment,
    bool isDebugBuild,
  ) {
    final value = raw[_catalogDataSourceField];
    if (value == null || value == 'api') return CatalogDataSource.api;
    if (value != 'fixtures') {
      throw const ConfigValidationException(
        'CATALOG_DATA_SOURCE must be api or fixtures.',
      );
    }
    if (environment != AppEnvironment.local || !isDebugBuild) {
      throw const ConfigValidationException(
        'CATALOG_DATA_SOURCE fixtures is allowed only in local debug builds.',
      );
    }
    return CatalogDataSource.fixtures;
  }

  static void _rejectUnacceptedFields(Map<String, Object?> raw) {
    for (final key in raw.keys) {
      if (key == _forbiddenSecretField) {
        throw const ConfigValidationException(
          'CLERK_SECRET_KEY must never be supplied to the Flutter application.',
        );
      }
      if (!_acceptedFields.contains(key)) {
        throw const ConfigValidationException(
          'Unknown configuration field. Accepted fields are APP_ENV, '
          'API_BASE_URL, CLERK_PUBLISHABLE_KEY, ENABLE_DIAGNOSTICS, '
          'CATALOG_DATA_SOURCE.',
        );
      }
    }
  }

  static AppEnvironment _requireEnvironment(Map<String, Object?> raw) {
    final value = raw[_environmentField];
    if (value is! String || value.trim().isEmpty) {
      throw const ConfigValidationException(
        'APP_ENV is required and must be one of: local, staging, production.',
      );
    }
    final environment = AppEnvironment.tryParse(value);
    if (environment == null) {
      throw const ConfigValidationException(
        'APP_ENV must be one of: local, staging, production.',
      );
    }
    return environment;
  }

  static String _requireApiOrigin(
    Map<String, Object?> raw,
    AppEnvironment environment,
  ) {
    final value = raw[_apiOriginField];
    if (value is! String || value.trim().isEmpty) {
      throw const ConfigValidationException('API_BASE_URL is required.');
    }
    final origin = value.trim();
    _rejectPlaceholder(_apiOriginField, origin);
    final uri = Uri.tryParse(origin);
    if (uri == null || !uri.hasAuthority || uri.host.isEmpty) {
      throw const ConfigValidationException(
        'API_BASE_URL must be an absolute URL with a host.',
      );
    }
    _rejectUnsupportedScheme(uri);
    _rejectOriginDecoration(uri);
    _rejectPathPrefix(uri);
    _rejectInsecureTransport(uri, environment);
    _rejectDevelopmentHost(uri, environment);
    return _normalizeOrigin(uri);
  }

  static void _rejectUnsupportedScheme(Uri uri) {
    final scheme = uri.scheme.toLowerCase();
    if (scheme != 'http' && scheme != 'https') {
      throw const ConfigValidationException(
        'API_BASE_URL must use http or https.',
      );
    }
  }

  static void _rejectOriginDecoration(Uri uri) {
    if (uri.userInfo.isNotEmpty) {
      throw const ConfigValidationException(
        'API_BASE_URL must not contain userinfo.',
      );
    }
    if (uri.hasQuery) {
      throw const ConfigValidationException(
        'API_BASE_URL must not contain a query string.',
      );
    }
    if (uri.hasFragment) {
      throw const ConfigValidationException(
        'API_BASE_URL must not contain a fragment.',
      );
    }
  }

  static void _rejectPathPrefix(Uri uri) {
    if (uri.path.isNotEmpty && uri.path != '/') {
      throw const ConfigValidationException(
        'API_BASE_URL must be an origin without a path prefix such as /api/v1.',
      );
    }
  }

  static void _rejectInsecureTransport(Uri uri, AppEnvironment environment) {
    final isHttp = uri.scheme.toLowerCase() == 'http';
    if (isHttp && environment != AppEnvironment.local) {
      throw ConfigValidationException(
        'API_BASE_URL must use HTTPS for ${environment.name}.',
      );
    }
  }

  static void _rejectDevelopmentHost(Uri uri, AppEnvironment environment) {
    if (environment == AppEnvironment.local) return;
    if (_isDevelopmentHost(uri.host)) {
      throw ConfigValidationException(
        'API_BASE_URL must not use a development-only host for '
        '${environment.name}.',
      );
    }
  }

  /// Strips the empty path so `http://host:8000/` and `http://host:8000`
  /// normalize identically. Scheme and host case are already normalized by
  /// [Uri.parse].
  static String _normalizeOrigin(Uri uri) => uri.replace(path: '').toString();

  static String? _resolvePublishableKey(
    Map<String, Object?> raw,
    AppEnvironment environment,
  ) {
    final value = raw[_clerkField];
    if (value != null && value is! String) {
      throw const ConfigValidationException(
        'CLERK_PUBLISHABLE_KEY must be a string.',
      );
    }
    final key = (value as String? ?? '').trim();
    if (key.isEmpty) return _missingKeyIsAcceptable(environment);
    _rejectClerkPlaceholder(key);
    if (key.startsWith('sk_')) {
      throw const ConfigValidationException(
        'CLERK_PUBLISHABLE_KEY must be a publishable key; secret keys are '
        'never accepted.',
      );
    }
    if (!key.startsWith('pk_')) {
      throw const ConfigValidationException(
        'CLERK_PUBLISHABLE_KEY must start with pk_.',
      );
    }
    return key;
  }

  static String? _missingKeyIsAcceptable(AppEnvironment environment) {
    if (environment == AppEnvironment.local) return null;
    throw const ConfigValidationException(
      'CLERK_PUBLISHABLE_KEY is required for staging and production.',
    );
  }

  static void _rejectClerkPlaceholder(String key) {
    if (key.contains('XXXX') || _hasMarker(key)) {
      throw const ConfigValidationException(
        'CLERK_PUBLISHABLE_KEY contains a placeholder value and cannot be used.',
      );
    }
  }

  static void _rejectPlaceholder(String field, String value) {
    if (!_hasMarker(value)) return;
    throw ConfigValidationException(
      '$field contains a placeholder value and cannot be used.',
    );
  }

  static bool _hasMarker(String value) {
    final normalized = value.toLowerCase();
    for (final marker in _placeholderMarkers) {
      if (normalized.contains(marker)) return true;
    }
    return false;
  }

  static bool _resolveDiagnostics(Map<String, Object?> raw) {
    final value = raw[_diagnosticsField];
    if (value == null) return false;
    if (value is bool) return value;
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      if (normalized == 'true') return true;
      if (normalized == 'false') return false;
    }
    throw const ConfigValidationException(
      'ENABLE_DIAGNOSTICS must be true or false.',
    );
  }

  /// True for loopback, Android emulator, and private-network development hosts.
  static bool _isDevelopmentHost(String host) {
    final normalized = _unbracket(host.toLowerCase());
    if (normalized == 'localhost' || normalized.endsWith('.localhost')) {
      return true;
    }
    if (normalized == '::1' || normalized == '0.0.0.0') return true;
    if (normalized == '10.0.2.2' || normalized == '10.0.3.2') return true;
    if (normalized.endsWith('.local') || normalized.endsWith('.internal')) {
      return true;
    }
    return _isLoopbackIpv4(normalized) || _isPrivateIpv4(normalized);
  }

  /// Strips IPv6 square brackets, which [Uri.host] may or may not retain.
  static String _unbracket(String host) =>
      (host.startsWith('[') && host.endsWith(']'))
      ? host.substring(1, host.length - 1)
      : host;

  static bool _isLoopbackIpv4(String host) {
    final octets = _ipv4Octets(host);
    return octets != null && octets[0] == 127;
  }

  static bool _isPrivateIpv4(String host) {
    final octets = _ipv4Octets(host);
    if (octets == null) return false;
    if (octets[0] == 10) return true;
    if (octets[0] == 172 && octets[1] >= 16 && octets[1] <= 31) return true;
    if (octets[0] == 192 && octets[1] == 168) return true;
    return octets[0] == 169 && octets[1] == 254;
  }

  static List<int>? _ipv4Octets(String host) {
    final parts = host.split('.');
    if (parts.length != 4) return null;
    final octets = <int>[];
    for (final part in parts) {
      if (!RegExp(r'^\d{1,3}$').hasMatch(part)) return null;
      final value = int.tryParse(part);
      if (value == null || value > 255) return null;
      octets.add(value);
    }
    return octets;
  }
}
