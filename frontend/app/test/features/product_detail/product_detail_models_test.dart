import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/product_detail/data/product_detail.dart';

import '../../support/cat002_payload.dart';

void main() {
  group('ProductDetail decoding', () {
    test('reads every documented CAT-002 field', () {
      final detail = ProductDetail.fromJson(cat002Product());

      expect(detail.id, 'prod_01h8x9j2m4k5n6p7q8r9s0t1');
      expect(detail.slug, 'modern-3-seater-fabric-sofa');
      expect(detail.name, 'Modern 3-Seater Fabric Sofa');
      expect(detail.productType, 'MADE_TO_ORDER');
      expect(detail.price.amount, 125000000);
      expect(detail.price.currency, 'TZS');
      expect(detail.category.summary.slug, 'living-room');
      expect(detail.category.description, 'Sofas, tables, and accent seating.');
      expect(detail.availability, 'available');
      expect(detail.stockIndicator, 'MADE_TO_ORDER');
      expect(detail.description, 'Premium handcrafted living room sofa.');
      expect(detail.createdAt, DateTime.utc(2026, 1, 10, 8));
      expect(detail.updatedAt, DateTime.utc(2026, 1, 12, 10));
    });

    test('keeps money as integer minor units', () {
      final detail = ProductDetail.fromJson(cat002Product(amount: 24500000));

      expect(detail.price.amount, isA<int>());
      expect(detail.price.amount, 24500000);
    });

    test('projects a CAT-001 summary without re-parsing', () {
      final summary = ProductDetail.fromJson(cat002Product()).summary;

      expect(summary.slug, 'modern-3-seater-fabric-sofa');
      expect(summary.price.amount, 125000000);
      expect(summary.category.slug, 'living-room');
      expect(summary.primaryImage?.id, 'img_front');
    });

    test('accepts an empty gallery and no primary image', () {
      final detail = ProductDetail.fromJson(
        cat002Product(images: <Object?>[], primaryImage: null),
      );

      expect(detail.images, isEmpty);
      expect(detail.primaryImage, isNull);
      expect(detail.initialImageIndex, 0);
    });

    test(
      'preserves the backend image order and opens on the primary image',
      () {
        final detail = ProductDetail.fromJson(
          cat002Product(
            images: <Object?>[
              cat002Image(id: 'img_a', sortOrder: 1, isPrimary: false),
              cat002Image(id: 'img_b', sortOrder: 2, isPrimary: true),
              cat002Image(id: 'img_c', sortOrder: 3, isPrimary: false),
            ],
          ),
        );

        expect(detail.images.map((image) => image.id), <String>[
          'img_a',
          'img_b',
          'img_c',
        ]);
        expect(detail.initialImageIndex, 1);
      },
    );

    test('falls back to the first ordered image when none is primary', () {
      final detail = ProductDetail.fromJson(
        cat002Product(
          images: <Object?>[
            cat002Image(id: 'img_a', sortOrder: 1),
            cat002Image(id: 'img_b', sortOrder: 2),
          ],
        ),
      );

      expect(detail.initialImageIndex, 0);
    });

    test('reads embedded variant summaries without product or timestamps', () {
      final detail = ProductDetail.fromJson(cat002Product());

      expect(detail.variants.map((variant) => variant.id), <String>[
        'var_grey',
        'var_beige',
      ]);
      expect(detail.variants.first.sku, 'SOFA-MOD-3S-GRY');
      expect(detail.variants.first.price.amount, 125000000);
      expect(detail.variants.last.price.amount, 128000000);
      expect(detail.variants.last.availability, 'available');
      expect(detail.variants.last.stockIndicator, 'MADE_TO_ORDER');
    });

    test('accepts an empty variant list', () {
      final detail = ProductDetail.fromJson(
        cat002Product(variants: <Object?>[]),
      );

      expect(detail.variants, isEmpty);
    });

    test('normalizes an absent or blank description and category copy', () {
      final detail = ProductDetail.fromJson(
        cat002Product(
          description: '   ',
          category: cat002Category(description: null),
        ),
      );

      expect(detail.description, isNull);
      expect(detail.category.description, isNull);
    });

    test('ignores an unparseable timestamp instead of failing the page', () {
      final detail = ProductDetail.fromJson(
        cat002Product(createdAt: 'not-a-timestamp', updatedAt: null),
      );

      expect(detail.createdAt, isNull);
      expect(detail.updatedAt, isNull);
    });

    test('rejects malformed payloads instead of inventing values', () {
      final rejections = <String, Object?>{
        'not an object': 'nope',
        'invalid product type': cat002Product(productType: 'PRE_ORDER'),
        'invalid availability': cat002Product(availability: 'AVAILABLE'),
        'invalid stock indicator': cat002Product(stockIndicator: 'SOLD_OUT'),
        'negative price': cat002Product(amount: -1),
        'non-TZS currency': cat002Product(currency: 'USD'),
        'missing gallery': cat002Product(images: null),
        'missing variants': cat002Product(variants: <String, Object?>{}),
        'non-string description': <String, Object?>{
          ...cat002Product(),
          'description': 42,
        },
        'non-object primary image': <String, Object?>{
          ...cat002Product(),
          'primary_image': 'image.webp',
        },
      };

      for (final entry in rejections.entries) {
        expect(
          () => ProductDetail.fromJson(entry.value),
          throwsA(isA<FormatException>()),
          reason: 'Must reject ${entry.key}.',
        );
      }
    });

    test('rejects an invalid gallery image', () {
      final invalid = <String, Object?>{
        'id': 'img_a',
        'url': 'https://cdn.example.test/a.webp',
        'alt_text': 'Front',
        'sort_order': 'first',
        'is_primary': true,
      };

      expect(
        () => ProductDetail.fromJson(cat002Product(images: <Object?>[invalid])),
        throwsA(isA<FormatException>()),
      );
    });

    test('rejects an invalid variant field or enum', () {
      final missingSku = cat002Variant()..remove('sku');
      final invalidEnum = cat002Variant(stockIndicator: 'SOLD_OUT');
      final negativePrice = <String, Object?>{
        ...cat002Variant(),
        'price': <String, Object?>{'amount': -1, 'currency': 'TZS'},
      };

      for (final invalid in <Object?>[missingSku, invalidEnum, negativePrice]) {
        expect(
          () => ProductDetail.fromJson(
            cat002Product(variants: <Object?>[invalid]),
          ),
          throwsA(isA<FormatException>()),
        );
      }
    });

    test('ignores fields the public contract does not expose', () {
      final detail = ProductDetail.fromJson(<String, Object?>{
        ...cat002Product(),
        'reserved_quantity': 4,
        'physical_quantity': 9,
        'cost_price_amount': 100,
      });

      expect(detail.price.amount, 125000000);
      expect(
        detail.variants.every(
          (variant) => variant.id.isNotEmpty && variant.sku.isNotEmpty,
        ),
        isTrue,
      );
    });
  });
}
