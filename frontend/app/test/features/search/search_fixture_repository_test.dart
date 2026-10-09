import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';
import 'package:sl_furnitures/features/catalog/data/fixture_catalog_repository.dart';
import 'package:sl_furnitures/features/catalog/data/fixture_products.dart';

void main() {
  group('fixture CAT-001 behaviour', () {
    final repository = FixtureCatalogRepository();

    test('returns the whole fixture catalog by default', () async {
      final page = await repository.fetchProducts(const CatalogQuery());

      expect(page.products, hasLength(fixtureProductSummaries.length));
      expect(page.pagination.total, fixtureProductSummaries.length);
    });

    test('keeps the made-to-order restriction', () async {
      final page = await repository.fetchProducts(const CatalogQuery());

      expect(
        page.products.every(
          (product) => product.productType == 'MADE_TO_ORDER',
        ),
        isTrue,
      );
      expect(
        page.products.map((product) => product.id).toSet(),
        fixtureProductSummaries.map((product) => product.id).toSet(),
      );
    });

    test('matches product name case-insensitively', () async {
      final byName = await repository.fetchProducts(
        const CatalogQuery(search: 'lounge'),
      );
      final upper = await repository.fetchProducts(
        const CatalogQuery(search: 'LOUNGE'),
      );

      expect(byName.products.map((product) => product.slug), <String>[
        'fixture-open-frame-armchair',
      ]);
      expect(upper.products, hasLength(1));
    });

    test('matches nothing for an unmatched term', () async {
      final page = await repository.fetchProducts(
        const CatalogQuery(search: 'zzzz-no-such-furniture'),
      );

      expect(page.products, isEmpty);
      expect(page.pagination.total, 0);
      expect(page.pagination.hasNext, isFalse);
    });

    test('ignores a whitespace-only search', () async {
      final page = await repository.fetchProducts(
        const CatalogQuery(search: '   '),
      );

      expect(page.products, hasLength(fixtureProductSummaries.length));
    });

    test('filters by category slug', () async {
      final page = await repository.fetchProducts(
        const CatalogQuery(categorySlug: 'living-room'),
      );

      expect(page.products, isNotEmpty);
      expect(
        page.products.every(
          (product) => product.category.slug == 'living-room',
        ),
        isTrue,
      );
    });

    test('returns empty for a category with no fixture products', () async {
      final page = await repository.fetchProducts(
        const CatalogQuery(categorySlug: 'bedroom'),
      );

      expect(page.products, isEmpty);
      expect(page.pagination.total, 0);
    });

    test('combines search and category', () async {
      final matching = await repository.fetchProducts(
        const CatalogQuery(search: 'lounge', categorySlug: 'living-room'),
      );
      final conflicting = await repository.fetchProducts(
        const CatalogQuery(search: 'sofa', categorySlug: 'bedroom'),
      );

      expect(matching.products, isNotEmpty);
      expect(conflicting.products, isEmpty);
    });

    test('sorts by price in both directions', () async {
      final ascending = await repository.fetchProducts(
        const CatalogQuery(sort: CatalogSort.priceLowToHigh),
      );
      final descending = await repository.fetchProducts(
        const CatalogQuery(sort: CatalogSort.priceHighToLow),
      );

      final low = ascending.products.map((p) => p.price.amount).toList();
      expect(low, <int>[10000000, 10000000, 24500000]);
      expect(descending.products.map((p) => p.price.amount).toList(), <int>[
        24500000,
        10000000,
        10000000,
      ]);
    });

    test('sorts by name and is stable for equal keys', () async {
      final page = await repository.fetchProducts(
        const CatalogQuery(sort: CatalogSort.nameAToZ),
      );
      final names = page.products.map((p) => p.name.toLowerCase()).toList();

      expect(names, <String>[...names]..sort());
      expect(
        page.products.map((p) => p.id).toSet().length,
        page.products.length,
        reason: 'Equal sort keys must not collapse or duplicate products.',
      );
    });

    test('paginates without losing or repeating a product', () async {
      final first = await repository.fetchProducts(
        const CatalogQuery(page: 1, perPage: 2),
      );
      final second = await repository.fetchProducts(
        const CatalogQuery(page: 2, perPage: 2),
      );

      expect(first.products, hasLength(2));
      expect(first.pagination.hasNext, isTrue);
      expect(first.pagination.lastPage, 2);
      expect(second.products, hasLength(1));
      expect(second.pagination.hasPrevious, isTrue);
      expect(
        first.products
            .map((p) => p.id)
            .toSet()
            .intersection(second.products.map((p) => p.id).toSet()),
        isEmpty,
      );
      expect(<String>{
        ...first.products.map((p) => p.slug),
        ...second.products.map((p) => p.slug),
      }, fixtureProductSummaries.map((p) => p.slug).toSet());
    });

    test('clamps a page beyond the last one', () async {
      final page = await repository.fetchProducts(
        const CatalogQuery(page: 99, perPage: 2),
      );

      expect(page.products, isEmpty);
      expect(
        page.pagination.currentPage,
        page.pagination.lastPage,
        reason: 'Mirrors Laravel clamping current_page to last_page.',
      );
    });

    test('honors cancellation before returning anything', () async {
      await expectLater(
        repository.fetchProducts(
          const CatalogQuery(),
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
}
