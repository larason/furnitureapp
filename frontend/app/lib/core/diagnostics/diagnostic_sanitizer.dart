abstract final class DiagnosticSanitizer {
  static String? requestId(String? value) {
    if (value == null || value.isEmpty || value.length > 128) return null;
    return RegExp(r'^[A-Za-z0-9][A-Za-z0-9._:-]*$').hasMatch(value)
        ? value
        : null;
  }

  static String? apiErrorCode(String? value) {
    if (value == null || value.length > 64) return null;
    return RegExp(r'^[A-Z][A-Z0-9_]*$').hasMatch(value) ? value : null;
  }

  static String? httpMethod(String? value) {
    const allowed = <String>{'GET', 'POST', 'PUT', 'PATCH', 'DELETE'};
    return value != null && allowed.contains(value) ? value : null;
  }
}
