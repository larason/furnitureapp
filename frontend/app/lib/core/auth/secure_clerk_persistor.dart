import 'dart:async';
import 'dart:convert';

import 'package:clerk_auth/clerk_auth.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

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
  SecureClerkPersistor({required this._store});

  final SecureKeyValueStore _store;

  @override
  Future<void> initialize() async {}

  @override
  void terminate() {}

  @override
  Future<T?> read<T>(String key) async {
    final value = await _store.read(key);
    if (value == null) return null;

    late final Object? decoded;
    try {
      decoded = jsonDecode(value);
    } on FormatException {
      await _store.delete(key);
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
      _store.write(key, jsonEncode(value));

  @override
  Future<void> delete(String key) => _store.delete(key);
}
