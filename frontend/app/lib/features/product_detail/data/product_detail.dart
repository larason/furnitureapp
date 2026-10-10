import '../../catalog/data/product_summary.dart';

/// Product detail representation returned by `CAT-002`.
///
/// The detail response extends the `CAT-001` product summary, so identity,
/// price, availability, and the primary image are parsed by
/// [ProductSummary.fromJson] rather than a second copy of the same rules. Only
/// the fields `CAT-002` adds are modelled here: `description`, the full
/// `images[]` gallery, the embedded `variants[]` summaries, the category
/// description, and the timestamps.
///
/// Nothing is invented. There is no specification table, rating, review count,
/// discount, or stock quantity, because the frozen contract exposes none.
class ProductDetail {
  const ProductDetail({
    required this.id,
    required this.slug,
    required this.name,
    required this.productType,
    required this.price,
    required this.category,
    required this.primaryImage,
    required this.availability,
    required this.stockIndicator,
    required this.description,
    required this.images,
    required this.variants,
    required this.createdAt,
    required this.updatedAt,
  });

  final String id;
  final String slug;
  final String name;
  final String productType;
  final Money price;
  final ProductDetailCategory category;
  final ProductImageSummary? primaryImage;
  final String availability;
  final String stockIndicator;

  /// Public product copy exactly as the API returned it. An empty or
  /// whitespace-only description is normalized to null so an empty paragraph is
  /// never rendered. It is plain text: the contract documents no markup.
  final String? description;

  /// Full gallery in the backend's deterministic `sort_order ASC, id ASC`
  /// order. The received order is preserved; it is never re-derived locally.
  final List<ProductDetailImage> images;

  /// Active embedded variant summaries from `CAT-002`. Standalone `CAT-005`
  /// reads are never required for this screen.
  final List<ProductVariantSummary> variants;

  /// Publication and modification timestamps. The app does not display them, so
  /// they are parsed when valid and ignored when they are not, keeping an
  /// unused field from failing a public product page.
  final DateTime? createdAt;
  final DateTime? updatedAt;

  factory ProductDetail.fromJson(Object? value) {
    final summary = ProductSummary.fromJson(value);
    final map = value! as Map<String, Object?>;
    return ProductDetail(
      id: summary.id,
      slug: summary.slug,
      name: summary.name,
      productType: summary.productType,
      price: summary.price,
      category: ProductDetailCategory.parse(map['category'], summary.category),
      primaryImage: summary.primaryImage,
      availability: summary.availability,
      stockIndicator: summary.stockIndicator,
      description: _optionalText(map['description'], 'description'),
      images: _images(map['images']),
      variants: _variants(map['variants']),
      createdAt: _timestamp(map['created_at']),
      updatedAt: _timestamp(map['updated_at']),
    );
  }

  /// The summary projection, so any consumer that already understands a
  /// `CAT-001` summary can read a detail product without a second mapping.
  ProductSummary get summary => ProductSummary(
    id: id,
    slug: slug,
    name: name,
    productType: productType,
    price: price,
    category: category.summary,
    primaryImage: primaryImage,
    availability: availability,
    stockIndicator: stockIndicator,
  );

  /// Index of the image the contract marks as primary, or the first image.
  ///
  /// `CAT-002` identifies a cover image with `is_primary`, so the gallery opens
  /// on it when one is flagged and otherwise on the first ordered image.
  int get initialImageIndex {
    final index = images.indexWhere((image) => image.isPrimary);
    return index < 0 ? 0 : index;
  }

  static List<ProductDetailImage> _images(Object? value) {
    if (value is! List) {
      throw const FormatException('Invalid images.');
    }
    return List<ProductDetailImage>.unmodifiable(
      value.map(ProductDetailImage.fromJson),
    );
  }

  static List<ProductVariantSummary> _variants(Object? value) {
    if (value is! List) {
      throw const FormatException('Invalid variants.');
    }
    return List<ProductVariantSummary>.unmodifiable(
      value.map(ProductVariantSummary.fromJson),
    );
  }

  static String? _optionalText(Object? value, String field) {
    if (value == null) return null;
    if (value is! String) throw FormatException('Invalid $field.');
    return value.trim().isEmpty ? null : value.trim();
  }

  static DateTime? _timestamp(Object? value) =>
      value is String ? DateTime.tryParse(value)?.toUtc() : null;
}

/// Category context embedded in `CAT-002`.
///
/// `CAT-002` returns one more field than the `CAT-001` summary: the nullable
/// category `description`. Identity is not re-parsed; it comes from the summary
/// the shared product model already validated.
class ProductDetailCategory {
  const ProductDetailCategory({required this.summary, this.description});

  final ProductCategorySummary summary;

  /// Optional category landing-page copy. Absent or empty copy is normalized to
  /// null so an empty paragraph is never rendered.
  final String? description;

  static ProductDetailCategory parse(
    Object? value,
    ProductCategorySummary summary,
  ) {
    final description = value is Map ? value['description'] : null;
    if (description == null) {
      return ProductDetailCategory(summary: summary);
    }
    if (description is! String) {
      throw const FormatException('Invalid category description.');
    }
    final trimmed = description.trim();
    return ProductDetailCategory(
      summary: summary,
      description: trimmed.isEmpty ? null : trimmed,
    );
  }
}

/// Gallery image embedded in `CAT-002`.
///
/// The contract documents `id`, `url`, `alt_text`, `sort_order`, and
/// `is_primary`. No file path, width, height, or caption exists publicly, so
/// none is modelled.
class ProductDetailImage {
  const ProductDetailImage({
    required this.id,
    required this.url,
    required this.altText,
    required this.sortOrder,
    required this.isPrimary,
    this.assetPath,
  });

  final String id;
  final String url;
  final String altText;
  final int sortOrder;
  final bool isPrimary;

  /// Bundled asset used only by development fixtures. The API decoder never
  /// produces it, so a real image is always a contract URL.
  final String? assetPath;

  factory ProductDetailImage.fromJson(Object? value) {
    final map = _object(value, 'image');
    final sortOrder = map['sort_order'];
    final isPrimary = map['is_primary'];
    if (sortOrder is! int || sortOrder < 0 || isPrimary is! bool) {
      throw const FormatException('Invalid image.');
    }
    return ProductDetailImage(
      id: _requiredString(map, 'id'),
      url: _requiredString(map, 'url'),
      altText: _requiredString(map, 'alt_text'),
      sortOrder: sortOrder,
      isPrimary: isPrimary,
    );
  }
}

/// Variant summary embedded in the `CAT-002` product detail.
///
/// `product_id` and timestamps are deliberately absent from the embedded
/// representation, and no fabric, colour, dimension, material, or finish
/// attribute exists in the contract. Nothing is parsed out of `name` or `sku`.
class ProductVariantSummary {
  const ProductVariantSummary({
    required this.id,
    required this.sku,
    required this.name,
    required this.price,
    required this.availability,
    required this.stockIndicator,
  });

  final String id;
  final String sku;
  final String name;
  final Money price;

  /// Coarse public signal. Physical and reserved quantities are never exposed.
  final String availability;
  final String stockIndicator;

  factory ProductVariantSummary.fromJson(Object? value) {
    final map = _object(value, 'variant');
    final availability = _requiredString(map, 'availability');
    final stockIndicator = _requiredString(map, 'stock_indicator');
    if (availability != 'available' && availability != 'unavailable' ||
        stockIndicator != 'IN_STOCK' &&
            stockIndicator != 'LOW_STOCK' &&
            stockIndicator != 'MADE_TO_ORDER') {
      throw const FormatException('Invalid variant enum.');
    }
    return ProductVariantSummary(
      id: _requiredString(map, 'id'),
      sku: _requiredString(map, 'sku'),
      name: _requiredString(map, 'name'),
      price: Money.fromJson(map['price']),
      availability: availability,
      stockIndicator: stockIndicator,
    );
  }
}

Map<String, Object?> _object(Object? value, String label) {
  if (value is! Map) {
    throw FormatException('Invalid $label.');
  }
  return value.map((key, item) {
    if (key is! String) throw FormatException('Invalid $label.');
    return MapEntry(key, item);
  });
}

String _requiredString(Map<String, Object?> map, String field) {
  final value = map[field];
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('Invalid $field.');
  }
  return value;
}
