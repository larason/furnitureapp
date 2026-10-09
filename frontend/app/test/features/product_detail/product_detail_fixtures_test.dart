import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/config/app_config.dart';
import 'package:sl_furnitures/config/app_environment.dart';
import 'package:sl_furnitures/config/config_validation.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/fixture_catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/fixture_products.dart';
import 'package:sl_furnitures/features/product_detail/data/fixture_product_detail_repository.dart';

void main() {
  group('development fixtures', () {
    test('every fixture product resolves by slug and by opaque ID', () async {
      final repository = FixtureProductDetailRepository();

      for (final summary in fixtureProductSummaries) {
        final bySlug = await repository.getProduct(summary.slug);
        final byId = await repository.getProduct(summary.id);

        expect(bySlug.id, summary.id);
        expect(byId.slug, summary.slug);
        expect(bySlug.price.amount, summary.price.amount);
        expect(
          bySlug.category.summary.slug,
          summary.category.slug,
          reason: 'A detail must open the same category as its listing.',
        );
      }
    });

    test('a fixture detail agrees with its catalog summary', () async {
      final catalog = FixtureCatalogRepository();
      final repository = FixtureProductDetailRepository();

      final page = await catalog.fetchProducts(page: 1);

      for (final summary in page.products) {
        final detail = await repository.getProduct(summary.id);
        expect(detail.summary.id, summary.id);
        expect(detail.summary.slug, summary.slug);
        expect(detail.summary.name, summary.name);
        expect(
          detail.summary.primaryImage?.altText,
          summary.primaryImage!.altText,
        );
      }
    });

    test('every fixture photograph points at a bundled asset', () async {
      final repository = FixtureProductDetailRepository();

      for (final summary in fixtureProductSummaries) {
        final detail = await repository.getProduct(summary.id);
        expect(detail.images, isNotEmpty);
        for (final image in detail.images) {
          expect(
            image.assetPath,
            isNotNull,
            reason: 'A development fixture must never request a remote URL.',
          );
          expect(image.assetPath, startsWith('assets/'));
          expect(image.url, isEmpty);
        }
        expect(detail.initialImageIndex, 0);
        expect(detail.images.first.isPrimary, isTrue);
      }
    });

    test('fixture options follow the embedded CAT-002 shape', () async {
      final repository = FixtureProductDetailRepository();

      final detail = await repository.getProduct('fixture_sofa');

      expect(detail.variants, isNotEmpty);
      for (final variant in detail.variants) {
        expect(variant.id, startsWith('fixture_'));
        expect(variant.sku, isNotEmpty);
        expect(variant.name, isNotEmpty);
        expect(variant.price.currency, 'TZS');
        expect(variant.price.amount, greaterThan(0));
        expect(variant.availability, 'available');
        expect(variant.stockIndicator, 'MADE_TO_ORDER');
      }
    });

    test('fixture prices are whole shillings with unambiguous SKUs', () async {
      final repository = FixtureProductDetailRepository();

      for (final summary in fixtureProductSummaries) {
        final detail = await repository.getProduct(summary.id);
        expect(
          summary.price.amount % 100,
          0,
          reason: 'Base price in shillings.',
        );
        for (final variant in detail.variants) {
          expect(
            variant.price.amount % 100,
            0,
            reason: '${variant.name} must not show stray cents.',
          );
          expect(variant.sku.toUpperCase(), variant.sku);
          expect(
            RegExp(r'^[A-Z0-9_-]+$').hasMatch(variant.sku),
            isTrue,
            reason: '${variant.sku} must be a readable upper-case code.',
          );
          expect(
            RegExp(r'FIXTURE').allMatches(variant.sku).length,
            1,
            reason: '${variant.sku} must not repeat the fixture prefix.',
          );
          expect(
            variant.sku.contains('--'),
            isFalse,
            reason: '${variant.sku} must not contain an empty segment.',
          );
        }
      }
    });

    test(
      'an unknown fixture product is a controlled not-found result',
      () async {
        final repository = FixtureProductDetailRepository();

        await expectLater(
          repository.getProduct('missing-room-piece'),
          throwsA(
            isA<ApiError>()
                .having((error) => error.statusCode, 'status', 404)
                .having(
                  (error) => error.errors.single.code,
                  'code',
                  'RESOURCE_NOT_FOUND',
                ),
          ),
        );
      },
    );

    test('honors cancellation like the API repository', () async {
      final repository = FixtureProductDetailRepository();
      final cancellation = RequestCancellation()..cancel();

      await expectLater(
        repository.getProduct('fixture_sofa', cancellation: cancellation),
        throwsA(
          isA<ApiTransportException>().having(
            (error) => error.kind,
            'kind',
            ApiTransportFailureKind.cancellation,
          ),
        ),
      );
    });

    test('fixture mode stays a local debug-only data source', () {
      const local = <String, Object?>{
        'APP_ENV': 'local',
        'API_BASE_URL': 'https://api.example.test',
      };
      const production = <String, Object?>{
        'APP_ENV': 'production',
        'API_BASE_URL': 'https://api.example.test',
        'CLERK_PUBLISHABLE_KEY': 'pk_test_Y2xlcmsuZGV2JA',
      };

      expect(
        ConfigValidator.validate(<String, Object?>{
          ...local,
          'CATALOG_DATA_SOURCE': 'fixtures',
        }).catalogDataSource,
        CatalogDataSource.fixtures,
      );
      expect(
        () => ConfigValidator.validate(<String, Object?>{
          ...production,
          'CATALOG_DATA_SOURCE': 'fixtures',
        }),
        throwsA(isA<ConfigValidationException>()),
        reason: 'A production build must never read fixtures.',
      );
      expect(
        () => ConfigValidator.validate(<String, Object?>{
          ...local,
          'CATALOG_DATA_SOURCE': 'fixtures',
        }, isDebugBuild: false),
        throwsA(isA<ConfigValidationException>()),
        reason: 'A release build must never read fixtures.',
      );
    });

    test('API mode is the default and never becomes fixture mode', () {
      final config = ConfigValidator.validate(<String, Object?>{
        'APP_ENV': 'local',
        'API_BASE_URL': 'https://api.example.test',
      });

      expect(config.catalogDataSource, CatalogDataSource.api);
      expect(config.environment, AppEnvironment.local);
    });
  });
}
