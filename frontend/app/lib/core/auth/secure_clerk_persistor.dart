import 'dart:async';
import 'dart:convert';

import 'package:clerk_auth/clerk_auth.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../diagnostics/app_diagnostics.dart';
import '../diagnostics/diagnostic_category.dart';
import '../diagnostics/diagnostic_code.dart';
import '../diagnostics/diagnostic_event.dart';
import '../diagnostics/diagnostic_level.dart';

abstract interface class SecureKeyValueStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class FlutterSecureKeyValueStore implements SecureKeyValueStore {
  FlutterSecureKeyValueStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

class SecureClerkPersistor implements Persistor {
  SecureClerkPersistor({
    required this._store,
    this.diagnostics = const NoopAppDiagnostics(),
  });

  final SecureKeyValueStore _store;
  final AppDiagnostics diagnostics;

  @override
  Future<void> initialize() async {}

  @override
  void terminate() {}

  @override
  Future<T?> read<T>(String key) async {
    final value = await _runStorage(() => _store.read(key));
    if (value == null) return null;

    late final Object? decoded;
    try {
      decoded = jsonDecode(value);
    } on FormatException {
      await _runStorage(() => _store.delete(key));
      return null;
    }
    return decoded is T
        ? decoded
        : value is T
        ? value as T
        : null;
  }

  @override
  Future<void> write<T>(String key, T value) =>
      _runStorage(() => _store.write(key, jsonEncode(value)));

  @override
  Future<void> delete(String key) => _runStorage(() => _store.delete(key));

  Future<T> _runStorage<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } catch (_) {
      diagnostics.record(
        DiagnosticEvent.now(
          level: DiagnosticLevel.error,
          category: DiagnosticCategory.authentication,
          code: DiagnosticCode.securePersistenceFailed,
          context: const DiagnosticContext(
            operation: DiagnosticOperation.securePersistence,
          ),
        ),
      );
      rethrow;
    }
  }
}
