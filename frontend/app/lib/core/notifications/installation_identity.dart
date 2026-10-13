import 'dart:math';

import '../auth/secure_clerk_persistor.dart';

/// A stable identity for this app installation.
///
/// The value must survive a token rotation, otherwise every rotation looks like
/// a brand-new installation and the server accumulates an active device row per
/// rotation. It must never be derived from the registration token: a token is
/// rotated, deleted, and is secret-adjacent, so using one as an identity both
/// leaks the token into a second field and defeats rotation convergence.
abstract interface class InstallationIdentityStore {
  /// Returns this installation's identity, creating and persisting one the
  /// first time. Repeated calls return the same value.
  Future<String> getOrCreate();
}

/// Stores the installation identity in the existing secure key/value store.
final class SecureStorageInstallationIdentityStore
    implements InstallationIdentityStore {
  SecureStorageInstallationIdentityStore(SecureKeyValueStore store)
    : _store = store;

  /// Namespaced so it cannot collide with the Clerk session keys.
  static const storageKey = 'push.installation_identity';

  final SecureKeyValueStore _store;

  @override
  Future<String> getOrCreate() async {
    try {
      final existing = await _store.read(storageKey);
      if (existing != null && existing.isNotEmpty) {
        return existing;
      }
    } catch (_) {
      // An unavailable store must not stop registration. A value regenerated on
      // this launch costs one extra device row; throwing would cost the ability
      // to register at all.
    }

    final created = generateInstallationId();
    try {
      await _store.write(storageKey, created);
    } catch (_) {
      // Same reasoning: registration continues without a persisted identity.
    }

    return created;
  }
}

/// Generates a RFC 4122 version 4 identifier.
///
/// Uses [Random.secure] because this value identifies an installation, so it
/// must not be predictable from anything an attacker can observe.
String generateInstallationId() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));

  // Version 4, RFC 4122 variant.
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;

  final hex = bytes
      .map((byte) => byte.toRadixString(16).padLeft(2, '0'))
      .join();

  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
      '${hex.substring(12, 16)}-${hex.substring(16, 20)}-'
      '${hex.substring(20, 32)}';
}
