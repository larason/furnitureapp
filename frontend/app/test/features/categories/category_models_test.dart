import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/categories/data/category_detail.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';

void main() {
  group('CategorySummary (CAT-003)', () {
    test('decodes the frozen summary representation', () {
      final category = CategorySummary.fromJson(<String, Object?>{
        'id': 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
        'name': 'Living Room',
        'slug': 'living-room',
        'image': <String, Object?>{
          'url': 'https://cdn.example.test/categories/living-room.webp',
        },
      });

      expect(category.id, 'cat_01h8x8a1b2c3d4e5f6g7h8j9');
      expect(category.name, 'Living Room');
      expect(category.slug, 'living-room');
      expect(
        category.image!.url,
        'https://cdn.example.test/categories/living-room.webp',
      );
      expect(category.image!.assetPath, isNull);
    });

    test('rejects missing required fields', () {
      for (final field in <String>['id', 'name', 'slug']) {
        final payload = <String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
        }..remove(field);

        expect(
          () => CategorySummary.fromJson(payload),
          throwsFormatException,
          reason: 'A missing $field must be rejected.',
        );
      }

      expect(
        () => CategorySummary.fromJson(<String, Object?>{
          'id': 'cat_living',
          'name': '   ',
          'slug': 'living-room',
        }),
        throwsFormatException,
      );
    });

    test('rejects a slug that is not the server kebab-case contract', () {
      for (final slug in <String>[
        'Living Room',
        'living_room',
        'LIVING-ROOM',
        '-living-room',
        'living--room',
        'living room',
      ]) {
        expect(
          () => CategorySummary.fromJson(<String, Object?>{
            'id': 'cat_living',
            'name': 'Living Room',
            'slug': slug,
          }),
          throwsFormatException,
          reason: 'Slug "$slug" is outside the documented contract.',
        );
      }
    });

    test('accepts an absent or null image without synthesizing one', () {
      final absent = CategorySummary.fromJson(<String, Object?>{
        'id': 'cat_bedroom',
        'name': 'Bedroom',
        'slug': 'bedroom',
      });
      final explicit = CategorySummary.fromJson(<String, Object?>{
        'id': 'cat_bedroom',
        'name': 'Bedroom',
        'slug': 'bedroom',
        'image': null,
      });

      expect(absent.image, isNull);
      expect(explicit.image, isNull);
    });

    test('rejects malformed image data', () {
      final base = <String, Object?>{
        'id': 'cat_living',
        'name': 'Living Room',
        'slug': 'living-room',
      };
      for (final image in <Object?>[
        'living-room.webp',
        <String, Object?>{},
        <String, Object?>{'url': ''},
        <String, Object?>{'url': null},
        <String, Object?>{'url': 42},
        <String, Object?>{'src': 'living-room.webp'},
      ]) {
        expect(
          () => CategorySummary.fromJson({...base, 'image': image}),
          throwsFormatException,
          reason: 'Image "$image" must be rejected rather than replaced.',
        );
      }
    });

    test('accepts opaque category IDs and rejects malformed ones', () {
      const valid = <String>[
        'cat_01h8x8a1b2c3d4e5f6g7h8j9',
        'cat_living',
        'fixture_living',
        '42',
      ];
      for (final id in valid) {
        expect(
          CategorySummary.fromJson(<String, Object?>{
            'id': id,
            'name': 'Living Room',
            'slug': 'living-room',
          }).id,
          id,
          reason: 'Opaque ID "$id" must be preserved as returned.',
        );
      }

      for (final id in <String>['', 'cat living', 'cat/living', '../cat']) {
        expect(
          () => CategorySummary.fromJson(<String, Object?>{
            'id': id,
            'name': 'Living Room',
            'slug': 'living-room',
          }),
          throwsFormatException,
          reason: 'Malformed ID "$id" must be rejected.',
        );
      }
    });

    test('rejects a non-object payload', () {
      expect(
        () => CategorySummary.fromJson('living-room'),
        throwsFormatException,
      );
      expect(
        () => CategorySummary.fromJson(<Object?>[]),
        throwsFormatException,
      );
      expect(
        () => CategorySummary.fromJson(<Object?, Object?>{
          42: 'cat_living',
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
        }),
        throwsFormatException,
        reason: 'Non-string keys are rejected by the typed decoder.',
      );
    });
  });

  group('CategoryDetail (CAT-004)', () {
    test('decodes the documented detail representation', () {
      final detail = CategoryDetail.fromJson(<String, Object?>{
        'id': 'cat_01h8x8a1b2c3d4e5f6g7h8j9',
        'name': 'Living Room',
        'slug': 'living-room',
        'description': 'Sofas, coffee tables, and accent seating.',
        'image': <String, Object?>{
          'url': 'https://cdn.example.test/categories/living-room.webp',
        },
        'created_at': '2026-08-20T08:00:00Z',
      });

      expect(detail.name, 'Living Room');
      expect(detail.slug, 'living-room');
      expect(detail.description, 'Sofas, coffee tables, and accent seating.');
      expect(detail.image!.url, contains('living-room.webp'));
      expect(detail.createdAt, DateTime.utc(2026, 8, 20, 8));
      expect(detail.summary.slug, detail.slug);
      expect(detail.summary.id, detail.id);
      expect(detail.summary.name, detail.name);
      expect(detail.summary.image, same(detail.image));
    });

    test('normalizes optional descriptions', () {
      CategoryDetail decode(Object? description) => CategoryDetail.fromJson({
        'id': 'cat_living',
        'name': 'Living Room',
        'slug': 'living-room',
        'description': description,
      });

      expect(decode(null).description, isNull);
      expect(decode('Room pieces.').description, 'Room pieces.');
      expect(decode('').description, isNull);
      expect(decode('   ').description, isNull);
      expect(() => decode(42), throwsFormatException);
    });

    test('keeps an unusable created_at from failing the page', () {
      expect(
        CategoryDetail.fromJson(<String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
          'created_at': 'not-a-timestamp',
        }).createdAt,
        isNull,
      );
      expect(
        CategoryDetail.fromJson(<String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
          'slug': 'living-room',
        }).createdAt,
        isNull,
      );
    });

    test('rejects missing required detail fields', () {
      expect(
        () => CategoryDetail.fromJson(<String, Object?>{
          'name': 'Living Room',
          'slug': 'living-room',
        }),
        throwsFormatException,
      );
      expect(
        () => CategoryDetail.fromJson(<String, Object?>{
          'id': 'cat_living',
          'name': 'Living Room',
        }),
        throwsFormatException,
      );
      expect(() => CategoryDetail.fromJson(null), throwsFormatException);
    });
  });
}
