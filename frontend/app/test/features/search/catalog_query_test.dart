import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/features/catalog/data/catalog_query.dart';

/// Every wire value here was read from the frozen contract and confirmed
/// against live Laravel CAT-001 responses. Do not change one without
/// re-inspecting `docs/api/api-contract.md §21.2`, `ProductIndexRequest`, and
/// `ProductCatalogQuery`.
void main() {
  group('CAT-001 query construction', () {
    test('always fixes product_type=MADE_TO_ORDER', () {
      const query = CatalogQuery();

      expect(
        query.toQueryParameters()['product_type'],
        'MADE_TO_ORDER',
        reason: 'The request-only restriction is not an optional filter.',
      );
      expect(
        const CatalogQuery(
          search: 'sofa',
          categorySlug: 'sofas',
          sort: CatalogSort.priceLowToHigh,
        ).toQueryParameters()['product_type'],
        'MADE_TO_ORDER',
      );
    });

    test('emits the documented search and category parameters', () {
      final parameters = const CatalogQuery(
        search: 'sofa',
        categorySlug: 'living-room',
      ).toQueryParameters();

      expect(parameters['search'], 'sofa');
      expect(parameters['category'], 'living-room');
    });

    test('omits blank search instead of sending an empty value', () {
      for (final value in <String?>[null, '', '   ']) {
        expect(
          CatalogQuery(search: value).toQueryParameters(),
          isNot(contains('search')),
          reason: 'Laravel ignores an empty search; sending it is noise.',
        );
      }
    });

    test('trims surrounding whitespace but never rewrites the text', () {
      expect(
        const CatalogQuery(search: '  walnut sofa  ').normalizedSearch,
        'walnut sofa',
      );
      expect(
        const CatalogQuery(search: 'MADE to Order').normalizedSearch,
        'MADE to Order',
        reason: 'Case and wording are the server\'s business, not ours.',
      );
    });

    test('maps every sort option to the allow-listed wire pair', () {
      expect(CatalogSort.newest.toQuery(), <String, String>{
        'sort': 'created_at',
        'sort_direction': 'desc',
      });
      expect(CatalogSort.priceLowToHigh.toQuery(), <String, String>{
        'sort': 'price',
        'sort_direction': 'asc',
      });
      expect(CatalogSort.priceHighToLow.toQuery(), <String, String>{
        'sort': 'price',
        'sort_direction': 'desc',
      });
      expect(CatalogSort.nameAToZ.toQuery(), <String, String>{
        'sort': 'name',
        'sort_direction': 'asc',
      });
    });

    test('only ever uses the three allow-listed sort fields', () {
      final allowed = <String>{'created_at', 'price', 'name'};

      for (final option in CatalogSort.values) {
        expect(allowed, contains(option.sortField));
        expect(<String>{'asc', 'desc'}, contains(option.direction));
        expect(
          option.direction,
          option.direction.toLowerCase(),
          reason: 'The backend validates sort_direction case-sensitively.',
        );
      }
    });

    test('reproduces the contract default ordering', () {
      final parameters = const CatalogQuery().toQueryParameters();

      expect(
        <String, Object?>{
          'sort': parameters['sort'],
          'sort_direction': parameters['sort_direction'],
        },
        <String, String>{'sort': 'created_at', 'sort_direction': 'desc'},
        reason: 'An unsorted CAT-001 request resolves to created_at DESC.',
      );
      expect(const CatalogQuery().sort.isDefault, isTrue);
      expect(
        CatalogSort.values.where((option) => option.isDefault),
        <CatalogSort>[CatalogSort.newest],
      );
    });

    test('clamps pagination to the documented bounds', () {
      expect(
        const CatalogQuery(page: 0, perPage: 5000).toQueryParameters(),
        containsPair('page', 1),
      );
      expect(
        const CatalogQuery(page: 0, perPage: 5000).toQueryParameters(),
        containsPair('per_page', CatalogQuery.maxPerPage),
      );
      expect(
        const CatalogQuery(page: 3, perPage: 0).toQueryParameters(),
        containsPair('per_page', 1),
      );
      expect(CatalogQuery.maxPerPage, 100);
      expect(CatalogQuery.maxSearchLength, 100);
      expect(CatalogQuery.defaultPerPage, 20);
    });

    test('emits exactly the allow-listed keys and nothing else', () {
      const allowed = <String>{
        'search',
        'category',
        'product_type',
        'sort',
        'sort_direction',
        'page',
        'per_page',
      };
      final keys = const CatalogQuery(
        search: 'sofa',
        categorySlug: 'sofas',
        page: 2,
        perPage: 10,
      ).toQueryParameters().keys.toSet();

      expect(keys.where((key) => !allowed.contains(key)), isEmpty);
      expect(
        const CatalogQuery().toQueryParameters().keys.where(
          (key) => !allowed.contains(key),
        ),
        isEmpty,
      );
    });

    test('preserves criteria while paging and resets them explicitly', () {
      const query = CatalogQuery(
        search: 'sofa',
        categorySlug: 'sofas',
        sort: CatalogSort.priceLowToHigh,
        page: 4,
      );

      final page2 = query.withPage(2);
      expect(page2.page, 2);
      expect(page2.normalizedSearch, 'sofa');
      expect(page2.categorySlug, 'sofas');
      expect(page2.sort, CatalogSort.priceLowToHigh);

      final reset = query.forFirstPage();
      expect(reset.page, CatalogQuery.firstPage);
      expect(reset.hasSameCriteriaAs(query), isTrue);
    });

    test('compares result sets by criteria, not by page', () {
      const base = CatalogQuery(search: 'sofa', categorySlug: 'sofas');
      const other = CatalogQuery(search: 'sofa', categorySlug: 'bedroom');
      const sorted = CatalogQuery(
        search: 'sofa',
        categorySlug: 'sofas',
        sort: CatalogSort.nameAToZ,
      );

      expect(base.hasSameCriteriaAs(base.withPage(7)), isTrue);
      expect(base.hasSameCriteriaAs(other), isFalse);
      expect(base.hasSameCriteriaAs(sorted), isFalse);
      expect(
        const CatalogQuery().hasSameCriteriaAs(const CatalogQuery(search: ' ')),
        isTrue,
        reason: 'Whitespace-only text is not a different result set.',
      );
    });

    test('supports replacing one criterion at a time', () {
      const base = CatalogQuery(search: 'sofa', sort: CatalogSort.newest);

      expect(base.withSearch('table').normalizedSearch, 'table');
      expect(base.withSearch('table').categorySlug, isNull);
      expect(base.copyWithCategory('sofas').sort, CatalogSort.newest);
      expect(base.copyWithCategory(null).categorySlug, isNull);
      expect(
        base.withSort(CatalogSort.priceHighToLow).sort,
        CatalogSort.priceHighToLow,
      );
      expect(
        base.withSort(CatalogSort.priceHighToLow).normalizedSearch,
        'sofa',
      );
    });

    test('knows when nothing is narrowing the catalog', () {
      expect(const CatalogQuery().isUnfiltered, isTrue);
      expect(const CatalogQuery(search: '  ').isUnfiltered, isTrue);
      expect(const CatalogQuery(categorySlug: 'sofas').isUnfiltered, isFalse);
      expect(
        const CatalogQuery(sort: CatalogSort.nameAToZ).isUnfiltered,
        isFalse,
        reason: 'A non-default sort is an active choice.',
      );
    });

    test('never exposes an unsupported filter', () {
      final parameters = const CatalogQuery().toQueryParameters();

      for (final forbidden in <String>[
        'availability',
        'min_price',
        'max_price',
        'color',
        'material',
        'rating',
        'discount',
      ]) {
        expect(
          parameters,
          isNot(contains(forbidden)),
          reason: '$forbidden is not implemented and must not be sent.',
        );
      }
    });
  });
}

extension on CatalogSort {
  Map<String, String> toQuery() => <String, String>{
    'sort': sortField,
    'sort_direction': direction,
  };
}
