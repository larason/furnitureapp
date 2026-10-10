class Money {
  const Money({required this.amount, required this.currency});

  final int amount;
  final String currency;

  factory Money.fromJson(Object? value) {
    final map = _object(value, 'price');
    final amount = map['amount'];
    final currency = map['currency'];
    if (amount is! int ||
        amount < 0 ||
        currency is! String ||
        currency != 'TZS') {
      throw const FormatException('Invalid product price.');
    }
    return Money(amount: amount, currency: currency);
  }
}

class ProductCategorySummary {
  const ProductCategorySummary({
    required this.id,
    required this.slug,
    required this.name,
  });

  final String id;
  final String slug;
  final String name;

  factory ProductCategorySummary.fromJson(Object? value) {
    final map = _object(value, 'category');
    return ProductCategorySummary(
      id: _requiredString(map, 'id'),
      slug: _requiredString(map, 'slug'),
      name: _requiredString(map, 'name'),
    );
  }
}

class ProductImageSummary {
  const ProductImageSummary({
    required this.id,
    required this.url,
    required this.altText,
    this.assetPath,
  });

  final String id;
  final String url;
  final String altText;
  final String? assetPath;

  factory ProductImageSummary.fromJson(Object? value) {
    final map = _object(value, 'primary_image');
    return ProductImageSummary(
      id: _requiredString(map, 'id'),
      url: _requiredString(map, 'url'),
      altText: _requiredString(map, 'alt_text'),
    );
  }
}

class ProductSummary {
  const ProductSummary({
    required this.id,
    required this.slug,
    required this.name,
    required this.productType,
    required this.price,
    required this.category,
    required this.primaryImage,
    required this.availability,
    required this.stockIndicator,
  });

  final String id;
  final String slug;
  final String name;
  final String productType;
  final Money price;
  final ProductCategorySummary category;
  final ProductImageSummary? primaryImage;
  final String availability;
  final String stockIndicator;

  factory ProductSummary.fromJson(Object? value) {
    final map = _object(value, 'product');
    final productType = _requiredString(map, 'product_type');
    final availability = _requiredString(map, 'availability');
    final stockIndicator = _requiredString(map, 'stock_indicator');
    if (productType != 'IN_STOCK' && productType != 'MADE_TO_ORDER' ||
        availability != 'available' && availability != 'unavailable' ||
        stockIndicator != 'IN_STOCK' &&
            stockIndicator != 'LOW_STOCK' &&
            stockIndicator != 'MADE_TO_ORDER') {
      throw const FormatException('Invalid product enum.');
    }
    final image = map['primary_image'];
    if (image != null && image is! Map) {
      throw const FormatException('Invalid primary image.');
    }
    return ProductSummary(
      id: _requiredString(map, 'id'),
      slug: _requiredString(map, 'slug'),
      name: _requiredString(map, 'name'),
      productType: productType,
      price: Money.fromJson(map['price']),
      category: ProductCategorySummary.fromJson(map['category']),
      primaryImage: image == null ? null : ProductImageSummary.fromJson(image),
      availability: availability,
      stockIndicator: stockIndicator,
    );
  }
}

Map<String, Object?> _object(Object? value, String label) {
  if (value is! Map) {
    throw FormatException('Invalid $label.');
  }
  return value.map((key, value) {
    if (key is! String) throw FormatException('Invalid $label.');
    return MapEntry(key, value);
  });
}

String _requiredString(Map<String, Object?> map, String field) {
  final value = map[field];
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('Invalid $field.');
  }
  return value;
}
