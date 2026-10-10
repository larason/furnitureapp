import 'category_summary.dart';

/// Category detail representation returned by CAT-004.
///
/// Only fields the frozen contract documents are modelled: there is no product
/// count, subcategory count, popularity score, or availability flag.
class CategoryDetail {
  const CategoryDetail({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.image,
    this.createdAt,
  });

  final String id;
  final String name;
  final String slug;

  /// Optional landing-page copy. An absent, empty, or whitespace-only value is
  /// normalized to null so an empty paragraph is never rendered.
  final String? description;

  final CategoryImage? image;

  /// Category publication timestamp. The app does not display it, so it is
  /// parsed when valid and ignored when it is not a timestamp, keeping an
  /// unused field from ever failing a public category page.
  final DateTime? createdAt;

  factory CategoryDetail.fromJson(Object? value) {
    if (value is! Map<String, Object?>) {
      throw const FormatException('Invalid category.');
    }
    final summary = CategorySummary.fromJson(value);
    final description = value['description'];
    if (description != null && description is! String) {
      throw const FormatException('Invalid description.');
    }
    return CategoryDetail(
      id: summary.id,
      name: summary.name,
      slug: summary.slug,
      description: description is String && description.trim().isNotEmpty
          ? description.trim()
          : null,
      image: summary.image,
      createdAt: _timestamp(value['created_at']),
    );
  }

  /// The summary projection used for navigation, so the index, the detail
  /// screen, and any later consumer always agree on identity.
  CategorySummary get summary =>
      CategorySummary(id: id, name: name, slug: slug, image: image);

  static DateTime? _timestamp(Object? value) =>
      value is String ? DateTime.tryParse(value)?.toUtc() : null;
}
