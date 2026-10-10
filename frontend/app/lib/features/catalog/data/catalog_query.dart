/// Sorting choices the frozen `CAT-001` contract actually supports.
///
/// The contract allow-lists three sort fields (`created_at`, `price`, `name`)
/// and two lowercase directions (`asc`, `desc`). The wire syntax is a
/// **separate** `sort` and `sort_direction` pair — never a `-price` style
/// prefix, which the backend rejects with `INVALID_VALUE`.
///
/// Defaults are resolved server-side, not here: omitting `sort` yields
/// `created_at DESC` with an `id ASC` tie-breaker, which is exactly
/// [CatalogSort.newest].
enum CatalogSort {
  newest(sortField: 'created_at', direction: 'desc', label: 'Newest'),
  priceLowToHigh(
    sortField: 'price',
    direction: 'asc',
    label: 'Price: Low to High',
  ),
  priceHighToLow(
    sortField: 'price',
    direction: 'desc',
    label: 'Price: High to Low',
  ),
  nameAToZ(sortField: 'name', direction: 'asc', label: 'Name: A to Z');

  const CatalogSort({
    required this.sortField,
    required this.direction,
    required this.label,
  });

  /// Wire value for the CAT-001 `sort` parameter.
  final String sortField;

  /// Wire value for the CAT-001 `sort_direction` parameter.
  ///
  /// Always lowercase: the backend validates this against `asc`/`desc`
  /// case-sensitively and answers `INVALID_VALUE` for `ASC`.
  final String direction;

  /// Customer-facing label.
  final String label;

  /// Whether anything other than the product's base price is being changed.
  bool get isDefault => this == CatalogSort.newest;
}

/// A typed, deterministic `CAT-001` product-collection query.
///
/// Only the parameters the frozen contract allow-lists are ever emitted. The
/// backend answers any unrecognised query parameter with `422
/// INVALID_VALUE` (`_unknown_parameter`), so this object is the single place
/// that decides what goes on the wire.
///
/// The request-only launch fixes `product_type=MADE_TO_ORDER` permanently: it
/// is not an optional filter and no reset can remove it.
class CatalogQuery {
  const CatalogQuery({
    this.search,
    this.categorySlug,
    this.sort = CatalogSort.newest,
    this.page = firstPage,
    this.perPage = defaultPerPage,
  });

  /// Default `per_page`, matching the contract.
  static const int defaultPerPage = 20;

  /// Contract maximum for `per_page`.
  static const int maxPerPage = 100;

  /// Contract maximum length of `search`.
  static const int maxSearchLength = 100;

  /// The first page is `1`.
  static const int firstPage = 1;

  /// The request-only product type. Never user-selectable.
  static const String madeToOrderProductType = 'MADE_TO_ORDER';

  /// Raw customer search text. Trimming happens in [normalizedSearch]; the text
  /// itself is never stemmed, fuzzy-matched, or rewritten.
  final String? search;

  /// Canonical server-returned category slug, or an opaque category ID.
  final String? categorySlug;

  final CatalogSort sort;

  /// Requested page, clamped to `>= 1`.
  final int page;

  /// Requested page size, clamped to `1..[maxPerPage]`.
  final int perPage;

  /// Search text after trimming, or null when nothing meaningful remains.
  ///
  /// The backend ignores an empty or whitespace-only `search`, so sending one
  /// would only add noise to the request.
  String? get normalizedSearch {
    final trimmed = search?.trim() ?? '';
    return trimmed.isEmpty ? null : trimmed;
  }

  int get _safePage => page < firstPage ? firstPage : page;

  int get _safePerPage =>
      perPage < 1 ? 1 : (perPage > maxPerPage ? maxPerPage : perPage);

  /// True when this query has no text, category, or non-default sort applied.
  bool get isUnfiltered =>
      normalizedSearch == null && categorySlug == null && sort.isDefault;

  /// The same criteria on a different page. Pagination must preserve every
  /// filter while changing only the page.
  CatalogQuery withPage(int nextPage) => CatalogQuery(
    search: search,
    categorySlug: categorySlug,
    sort: sort,
    page: nextPage,
    perPage: perPage,
  );

  /// The same criteria with replacement search text, on the same page.
  CatalogQuery withSearch(String? nextSearch) => CatalogQuery(
    search: nextSearch,
    categorySlug: categorySlug,
    sort: sort,
    page: page,
    perPage: perPage,
  );

  /// The same criteria with a replacement category, on the same page.
  CatalogQuery copyWithCategory(String? nextCategorySlug) => CatalogQuery(
    search: search,
    categorySlug: nextCategorySlug,
    sort: sort,
    page: page,
    perPage: perPage,
  );

  /// The same criteria with a replacement sort, on the same page.
  CatalogQuery withSort(CatalogSort nextSort) => CatalogQuery(
    search: search,
    categorySlug: categorySlug,
    sort: nextSort,
    page: page,
    perPage: perPage,
  );

  /// Criteria pinned back to the first page, for a fresh result set.
  CatalogQuery forFirstPage() => withPage(firstPage);

  /// Whether two queries describe the same result set regardless of page.
  ///
  /// Every criteria change is a new result set and must reset pagination;
  /// loading another page of the same set must not.
  bool hasSameCriteriaAs(CatalogQuery other) =>
      normalizedSearch == other.normalizedSearch &&
      categorySlug == other.categorySlug &&
      sort == other.sort &&
      perPage == other.perPage;

  /// Deterministic `CAT-001` query parameters.
  ///
  /// Empty criteria are omitted rather than sent blank. Nothing outside the
  /// contract allow-list is ever added.
  Map<String, Object?> toQueryParameters() => <String, Object?>{
    'search': ?normalizedSearch,
    'category': ?categorySlug,
    'sort': sort.sortField,
    'sort_direction': sort.direction,
    'product_type': madeToOrderProductType,
    'page': _safePage,
    'per_page': _safePerPage,
  };
}
