import '../../../core/identifiers/resource_identifier.dart';

/// Category image object documented by CAT-003 and CAT-004: `{url}` only.
class CategoryImage {
  const CategoryImage({required this.url, this.assetPath});

  final String url;

  /// Bundled asset used only by development fixtures. The API decoder never
  /// produces it, so a remote image is always a URL from the contract.
  final String? assetPath;

  /// Parses the documented nullable `image` object.
  ///
  /// Returns null when the API reports no image. A present image must carry a
  /// non-empty URL; malformed data is rejected instead of being replaced with a
  /// synthetic image.
  static CategoryImage? parse(Object? value) {
    if (value == null) return null;
    if (value is! Map<String, Object?>) {
      throw const FormatException('Invalid image.');
    }
    final url = value['url'];
    if (url is! String || url.trim().isEmpty) {
      throw const FormatException('Invalid image.');
    }
    return CategoryImage(url: url);
  }
}

/// Category summary representation returned by CAT-003.
///
/// The collection is flat: it holds the active storefront categories directly
/// beneath the structural root, never a nested tree, so no `children` field
/// exists to parse or to invent.
class CategorySummary {
  const CategorySummary({
    required this.id,
    required this.name,
    required this.slug,
    this.image,
  });

  final String id;
  final String name;
  final String slug;
  final CategoryImage? image;

  factory CategorySummary.fromJson(Object? value) {
    final map = _object(value);
    final id = _requiredString(map, 'id');
    if (!ResourceIdentifier.isValid(id)) {
      throw const FormatException('Invalid id.');
    }
    return CategorySummary(
      id: id,
      name: _requiredString(map, 'name'),
      slug: _slug(_requiredString(map, 'slug')),
      image: CategoryImage.parse(map['image']),
    );
  }

  /// Server-owned slug contract: kebab-case, enforced by the backend category
  /// model and documented by ADR/API-CAT-004. Slugs are never derived from
  /// display names.
  static final RegExp _slugPattern = RegExp(r'^[a-z0-9]+(?:-[a-z0-9]+)*$');

  static Map<String, Object?> _object(Object? value) {
    if (value is! Map) throw const FormatException('Invalid category.');
    return value.map((key, item) {
      if (key is! String) throw const FormatException('Invalid category.');
      return MapEntry(key, item);
    });
  }

  static String _requiredString(Map<String, Object?> map, String field) {
    final value = map[field];
    if (value is! String || value.trim().isEmpty) {
      throw FormatException('Invalid $field.');
    }
    return value;
  }

  static String _slug(String value) {
    if (!_slugPattern.hasMatch(value)) {
      throw const FormatException('Invalid slug.');
    }
    return value;
  }
}
