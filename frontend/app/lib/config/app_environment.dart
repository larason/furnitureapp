/// Closed set of deployment environments.
///
/// The set is deliberately finite: an unknown `APP_ENV` must fail validation
/// rather than fall back to a default, so no fourth value can be introduced by
/// configuration alone.
enum AppEnvironment {
  local,
  staging,
  production;

  /// Returns the environment named by [raw], or null when it is not one of the
  /// three supported names.
  ///
  /// Surrounding whitespace and letter case are normalized because both are
  /// unambiguous; any other difference is treated as unknown.
  static AppEnvironment? tryParse(String? raw) {
    if (raw == null) return null;
    final normalized = raw.trim().toLowerCase();
    for (final environment in values) {
      if (environment.name == normalized) return environment;
    }
    return null;
  }
}
