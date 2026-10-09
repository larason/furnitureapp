import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/fixture_catalog_repository.dart';
import 'package:sl_furnitures/features/categories/data/category_summary.dart';
import 'package:sl_furnitures/features/categories/data/fixture_categories.dart';
import 'package:sl_furnitures/features/categories/data/fixture_category_repository.dart';

void main() {
  const repository = FixtureCategoryRepository();

  group('fixture category collection', () {
    test('returns the approved storefront categories only', () async {
      final page = await repository.getCategories(page: 1);

      expect(
        page.categories.map((category) => category.slug),
        <String>[
          'living-room',
          'bedroom',
          'dining-room-kitchen',
          'home-office-corporate-workspaces',
        ],
        reason:
            'The fixture taxonomy must match the approved website fixtures.',
      );
      expect(page.pagination.total, fixtureCategoryDetails.length);
      expect(page.pagination.hasNext, isFalse);
      expect(page.pagination.hasPrevious, isFalse);
    });

    test('never fabricates nested subcategory navigation', () {
      const flat = <String, Object?>{
        'id': 'cat_living',
        'name': 'Living Room',
        'slug': 'living-room',
        'image': <String, Object?>{
          'url': 'https://cdn.example.test/categories/living-room.webp',
        },
      };
      const withUndeclaredChildren = <String, Object?>{
        ...flat,
        'children': <Object?>[
          <String, Object?>{
            'id': 'cat_sofas',
            'name': 'Sofas',
            'slug': 'sofas',
          },
        ],
      };

      final summary = CategorySummary.fromJson(flat);
      final withExtra = CategorySummary.fromJson(withUndeclaredChildren);

      expect(
        withExtra.id,
        summary.id,
        reason:
            'CAT-003 returns a flat storefront collection. An undeclared '
            '"children" key must not become part of the model.',
      );
      expect(withExtra.name, summary.name);
      expect(withExtra.slug, summary.slug);
      expect(withExtra.image!.url, summary.image!.url);
      expect(
        fixtureCategoryDetails.every(
          (category) => !category.slug.contains('/'),
        ),
        isTrue,
        reason: 'Fixture slugs are canonical routes, not taxonomy paths.',
      );
    });

    test('supplies bundled imagery for every category', () async {
      final page = await repository.getCategories(page: 1);

      for (final category in page.categories) {
        final image = category.image;
        expect(image, isNotNull);
        expect(image!.assetPath, isNotNull);
        expect(image.assetPath, startsWith('assets/'));
      }
    });

    test('holds a stable identity between summary and detail', () async {
      final page = await repository.getCategories(page: 1);

      for (final summary in page.categories) {
        final detail = await repository.getCategory(summary.slug);
        expect(detail.id, summary.id);
        expect(detail.name, summary.name);
        expect(detail.slug, summary.slug);
      }
    });
  });

  group('fixture category detail', () {
    test('resolves a category by slug and by opaque ID', () async {
      final bySlug = await repository.getCategory('living-room');
      final byId = await repository.getCategory('fixture_living');

      expect(bySlug.id, 'fixture_living');
      expect(byId.slug, bySlug.slug);
      expect(byId.name, bySlug.name);
    });

    test('reports a missing category as RESOURCE_NOT_FOUND', () async {
      await expectLater(
        repository.getCategory('not-a-room'),
        throwsA(
          isA<ApiError>()
              .having((error) => error.statusCode, 'statusCode', 404)
              .having(
                (error) => error.errors.single.code,
                'code',
                'RESOURCE_NOT_FOUND',
              ),
        ),
      );
    });

    test('reports cancellation instead of returning data', () async {
      await expectLater(
        repository.getCategories(
          page: 1,
          cancellation: RequestCancellation()..cancel(),
        ),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.cancellation,
          ),
        ),
      );
      await expectLater(
        repository.getCategory(
          'living-room',
          cancellation: RequestCancellation()..cancel(),
        ),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.cancellation,
          ),
        ),
      );
    });
  });

  group('fixture category product relationships', () {
    test('every fixture product belongs to a listed fixture category', () async {
      final catalog = FixtureCatalogRepository();
      final categories = await repository.getCategories(page: 1);
      final slugs = categories.categories.map((c) => c.slug).toSet();

      final page = await catalog.fetchProducts(const CatalogQuery());
      expect(page.products, isNotEmpty);
      for (final product in page.products) {
        expect(
          slugs,
          contains(product.category.slug),
          reason:
              'A fixture product must map to a category the fixture collection '
              'actually returns.',
        );
      }
    });

    test('every fixture category is browsable, even without products', () async {
      final catalog = FixtureCatalogRepository();
      final page = await repository.getCategories(page: 1);

      for (final category in page.categories) {
        final products = await catalog.fetchProducts(
          CatalogQuery(categorySlug: category.slug),
        );
        expect(
          products.products.every(
            (product) => product.category.slug == category.slug,
          ),
          isTrue,
          reason:
              'A category listing must never show furniture from another room.',
        );
      }
    });

    test('fixture category browsing stays request-only', () async {
      final catalog = FixtureCatalogRepository();
      final page = await catalog.fetchProducts(
        const CatalogQuery(categorySlug: 'living-room'),
      );

      expect(
        page.products.every(
          (product) => product.productType == 'MADE_TO_ORDER',
        ),
        isTrue,
        reason: 'Fixture browsing must not expose purchasable stock furniture.',
      );
    });
  });

  group('repository source selection', () {
    test('fixtures are opt-in and default to the real API', () {
      const apiConfig = AppConfig(
        environment: AppEnvironment.local,
        apiBaseUrl: 'http://127.0.0.1:8000',
      );

      expect(apiConfig.catalogDataSource, CatalogDataSource.api);
      expect(
        apiConfig.catalogDataSource == CatalogDataSource.fixtures,
        isFalse,
        reason: 'Staging and production must always read the real API.',
      );
    });
  });
}
