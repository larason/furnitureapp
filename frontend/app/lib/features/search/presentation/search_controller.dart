import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/presentation/async_view_state.dart';
import '../../catalog/data/catalog_query.dart';
import '../../catalog/data/catalog_repository.dart';
import '../../catalog/presentation/catalog_controller.dart';
import '../../categories/data/category_repository.dart';
import '../../categories/presentation/categories_controller.dart';

/// Owns the criteria and result set of the public search screen.
///
/// Text, category, and sort are three ways of changing the *same* result set, so
/// each change rebuilds the inner [CatalogController]: pagination resets, the
/// previous results are discarded, and any obsolete in-flight request is
/// cancelled by the catalog controller's own generation guard. Nothing here
/// sorts, filters, or paginates on the client — Laravel owns all of that.
///
/// Category options load independently, so a category failure leaves search
/// usable rather than blocking it.
///
/// This controller is the screen's single [Listenable]: it forwards the inner
/// result and category notifications, so a finished request or a late-arriving
/// option list reaches the UI without the screen having to subscribe to a
/// [CatalogController] that a later criteria change may replace.
class SearchController extends ChangeNotifier {
  SearchController({
    required CatalogRepository catalogRepository,
    required CategoryRepository categoryRepository,
    CatalogQuery? initialQuery,
    this.debounce = defaultDebounce,
  }) : _catalogRepository = catalogRepository,
       _categories = CategoriesController(categoryRepository) {
    _criteria = (initialQuery ?? const CatalogQuery()).forFirstPage();
    _results = CatalogController(catalogRepository, query: _criteria)..load();
    _loadCategories();
    _results.addListener(_notify);
    _categories.addListener(_notify);
  }

  /// Modest, documented pause between the last keystroke and a request.
  ///
  /// Long enough to avoid a request per character, short enough that the
  /// results still feel live. Submitting from the keyboard or the search
  /// button bypasses it entirely.
  static const Duration defaultDebounce = Duration(milliseconds: 350);

  /// Upper bound on extra category pages pulled to populate the filter.
  ///
  /// A filter dropdown must offer every published category, but it is not a
  /// browsing surface, so the walk stops at a documented ceiling rather than
  /// issuing requests forever against a hostile or misconfigured endpoint.
  static const int maxCategoryFilterPages = 10;

  final Duration debounce;

  final CatalogRepository _catalogRepository;
  final CategoriesController _categories;
  late CatalogQuery _criteria;
  late CatalogController _results;
  Timer? _debounceTimer;
  String _searchText = '';
  bool _disposed = false;

  /// The applied result-set criteria.
  CatalogQuery get criteria => _criteria;

  /// Current result set, including its pagination and refresh-failure state.
  CatalogController get results => _results;

  /// Public category options for the filter control.
  CategoriesController get categories => _categories;

  /// Loads the first category page, then every remaining page the filter needs.
  Future<void> _loadCategories() async {
    await _categories.load();
    await _loadCategoryFilter();
  }

  /// Walks forward through the remaining category pages.
  ///
  /// The filter is a complete list of published categories, so leaving later
  /// pages unloaded would silently hide categories that exist and can be
  /// filtered on. The walk stops at the first page that reports no further
  /// results, at [maxCategoryFilterPages], or when this controller is disposed.
  /// A failure keeps the categories already loaded and is never retried, so an
  /// outage cannot turn this into a burst of duplicate requests.
  Future<void> _loadCategoryFilter({int page = 0}) async {
    if (_disposed || page >= maxCategoryFilterPages) return;
    final data = _categoryPage;
    if (data == null || !data.pagination.hasNext) return;
    final requestedPage = data.pagination.currentPage + 1;
    await _categories.loadNextPage();
    // A failed or ignored page leaves `currentPage` where it was, so both
    // conditions end the walk instead of asking for the same page again.
    if (_categories.nextPageError != null ||
        _categoryPage?.pagination.currentPage != requestedPage) {
      return;
    }
    return _loadCategoryFilter(page: page + 1);
  }

  CategoryPage? get _categoryPage => switch (_categories.state) {
    AsyncContent(:final data) => data,
    AsyncRefreshing(:final data) => data,
    _ => null,
  };

  /// Raw text in the search field, which may differ from the applied criteria
  /// while a debounce is pending.
  String get searchText => _searchText;

  /// True when text, category, or sort is narrowing the result set.
  bool get hasActiveCriteria => !_criteria.isUnfiltered;

  /// Records new text and schedules the debounced request.
  ///
  /// Identical normalised text is a no-op, so typing and re-submitting the same
  /// query never issues a duplicate request.
  void onSearchTextChanged(String text) {
    if (_disposed || text == _searchText) return;
    _searchText = text;
    _schedule();
  }

  /// Applies the pending text immediately, cancelling any debounce.
  Future<void> submitSearch() => _applyPending(_criteria);

  /// Empties the search field and applies the remaining criteria.
  ///
  /// Clearing text never clears the category or sort; the customer asked to
  /// clear the query, not the whole screen.
  Future<void> clearSearch() async {
    if (_disposed) return;
    _searchText = '';
    await _applyPending(_criteria);
  }

  /// Applies a category or sort change together with any pending debounced
  /// text, so a selection made before the debounce elapses produces one
  /// request rather than one per criterion.
  Future<void> selectCategory(String? slug) =>
      _applyPending(_criteria.copyWithCategory(slug));

  Future<void> selectSort(CatalogSort sort) =>
      _applyPending(_criteria.withSort(sort));

  /// Restores the unfiltered result set. The made-to-order restriction is part
  /// of every query and is not affected by a reset.
  Future<void> reset() async {
    if (_disposed) return;
    _debounceTimer?.cancel();
    _debounceTimer = null;
    _searchText = '';
    await _apply(const CatalogQuery());
  }

  void _schedule() {
    _debounceTimer?.cancel();
    _debounceTimer = Timer(debounce, () {
      _debounceTimer = null;
      if (_disposed) return;
      unawaited(_apply(_criteria.withSearch(_searchText)));
    });
  }

  /// Cancels any pending debounce and applies [next] together with the current
  /// search text as a single result set.
  Future<void> _applyPending(CatalogQuery next) async {
    if (_disposed) return;
    _debounceTimer?.cancel();
    _debounceTimer = null;
    await _apply(next.withSearch(_searchText));
  }

  Future<void> _apply(CatalogQuery next) async {
    if (_disposed) return;
    final criteria = next.forFirstPage();
    if (criteria.hasSameCriteriaAs(_criteria)) {
      _notify();
      return;
    }
    _criteria = criteria;
    final previous = _results;
    _results = CatalogController(_catalogRepository, query: criteria)..load();
    previous
      ..removeListener(_notify)
      ..dispose();
    _results.addListener(_notify);
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _debounceTimer?.cancel();
    _results
      ..removeListener(_notify)
      ..dispose();
    _categories
      ..removeListener(_notify)
      ..dispose();
    super.dispose();
  }
}
