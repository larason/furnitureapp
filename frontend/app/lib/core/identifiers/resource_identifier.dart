/// Validation for public catalog identifiers.
///
/// Category and product routes accept either the server's stable opaque ID
/// (`cat_...`, `prod_...`) or the server's kebab-case slug. The pattern is
/// deliberately permissive about which of the two is supplied: it rejects
/// malformed values (spaces, separators, empties) without deciding whether an
/// identifier is a slug or an ID, because only the server owns that definition.
abstract final class ResourceIdentifier {
  static final RegExp _pattern = RegExp(r'^[A-Za-z0-9][A-Za-z0-9_-]*$');

  static bool isValid(String? value) =>
      value != null && _pattern.hasMatch(value);

  static String? sanitized(String? value) => isValid(value) ? value : null;
}
