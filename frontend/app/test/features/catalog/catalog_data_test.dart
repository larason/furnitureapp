import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/catalog/data/fixture_catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/product_summary.dart';
import 'package:sl_furnitures/features/catalog/presentation/product_card.dart';

void main() {
  test('decodes the frozen CAT-001 summary representation', () {
    final product = ProductSummary.fromJson(<String, Object?>{
      'id': 'prod_abc',
      'slug': 'oak-chair',
      'name': 'Oak chair',
      'product_type': 'MADE_TO_ORDER',
      'price': <String, Object?>{'amount': 125000000, 'currency': 'TZS'},
      'category': <String, Object?>{
        'id': 'cat_living',
        'slug': 'living-room',
        'name': 'Living Room',
      },
      'primary_image': <String, Object?>{
        'id': 'img_abc',
        'url': 'https://cdn.example.test/chair.jpg',
        'alt_text': 'Oak chair',
      },
      'availability': 'available',
      'stock_indicator': 'MADE_TO_ORDER',
    });

    expect(product.id, 'prod_abc');
    expect(product.price.amount, 125000000);
    expect(product.primaryImage!.altText, 'Oak chair');
  });

  test('rejects malformed CAT-001 money and enums', () {
    expect(
      () => ProductSummary.fromJson(<String, Object?>{
        'id': 'prod_abc',
        'slug': 'oak-chair',
        'name': 'Oak chair',
        'product_type': 'UNKNOWN',
        'price': <String, Object?>{'amount': 12.5, 'currency': 'TZS'},
        'category': <String, Object?>{
          'id': 'cat_living',
          'slug': 'living-room',
          'name': 'Living Room',
        },
        'primary_image': null,
        'availability': 'available',
        'stock_indicator': 'MADE_TO_ORDER',
      }),
      throwsFormatException,
    );
  });

  test('formats TZS minor-unit prices without a leading separator', () {
    expect(
      formatTzs(const Money(amount: 10000000, currency: 'TZS')),
      'TZS 100,000',
    );
    expect(
      formatTzs(const Money(amount: 24500000, currency: 'TZS')),
      'TZS 245,000',
    );
  });

  test('fixture catalog is request-only and paginated', () async {
    final repository = FixtureCatalogRepository();
    final first = await repository.fetchProducts(page: 1);
    final second = await repository.fetchProducts(page: 2);

    expect(first.products, hasLength(3));
    expect(
      first.products.every((product) => product.productType == 'MADE_TO_ORDER'),
      isTrue,
    );
    expect(
      first.products.every(
        (product) => product.primaryImage!.assetPath != null,
      ),
      isTrue,
    );
    expect(
      first.products.every(
        (product) => product.primaryImage!.assetPath!.startsWith('assets/'),
      ),
      isTrue,
    );
    expect(first.pagination.hasNext, isFalse);
    expect(second.products, isEmpty);
  });
}
